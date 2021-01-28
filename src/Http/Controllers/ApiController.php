<?php

namespace Uccello\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Uccello\Api\Support\ApiTrait;
use Uccello\Api\Support\ImageUploadTrait;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;
use Uccello\Core\Events\BeforeSaveEvent;
use Uccello\Core\Events\AfterSaveEvent;
use Uccello\Core\Events\BeforeDeleteEvent;
use Uccello\Core\Events\AfterDeleteEvent;
use Uccello\Core\Facades\Uccello;

class ApiController extends Controller
{
    use ApiTrait;
    use ImageUploadTrait;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of the resources.
     * Filter on domain if domain_id column exists.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function describe(?Domain $domain, Module $module, Request $request)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        $description = [
            'module' => $module,
            'fields' => []
        ];

        foreach ($module->fields()->get() as $field) {
            $fieldData = $field;

            $uitype = uitype($field->uitype_id);
            $fieldData->column = $field->column;
            $fieldData->uitype = $uitype->name ?? null;
            $fieldData->required = $field->required;
            $description["fields"][] = $fieldData;
        }

        return response()->json([
            'success' => true,
            'description' => $description
        ]);
    }

    /**
     * Display a listing of the resources.
     * Filter on domain if domain_id column exists.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(?Domain $domain, Module $module, Request $request)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Get pagination length
        $length = $this->getPaginationLength();

        // Prepare query
        $query = $this->prepareQueryForApi($domain, $module);

        // Add conditions
        if ($request->has('conditions')) {
            foreach ((array) $request->conditions as $key => $value) {
                $query->where($key, $value);
            }
        }

        // Add eventualy deleted record
        if ($request->only_deleted == 1) {
            $query->onlyTrashed();
        } elseif ($request->with_deleted == 1) {
            $query->withTrashed();
        }

        $records = $query->paginate($length);

        // Get formatted records
        $records->getCollection()->transform(function ($record) use ($domain, $module) {
            return $this->getFormattedRecordToDisplay($record, $domain, $module);
        });

        return $records;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(?Domain $domain, Module $module, Request $request)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Get model model class
        $modelClass = $module->model_class;
        $record = new $modelClass();

        if (Schema::hasColumn((new $modelClass)->getTable(), 'domain_id')) {
            // Paginate results
            $record->domain_id = $domain->id;
        }

        foreach ($request->all() as $fieldName => $value) {
            $field = $module->getField($fieldName);

            // If the field exists format the value and store it in the good model column
            if (!is_null($field)) {
                $column = $field->column;
                $record->$column = $field->uitype->getFormattedValueToSave($request, $field, $value, $record, $domain, $module);
            }
        }

        // Dispatch before save event
        event(new BeforeSaveEvent($domain, $module, $request, $record, 'create', true));

        // Save
        $record->save();

        // Dispatch after save event
        event(new AfterSaveEvent($domain, $module, $request, $record, 'create', true));

        $record = $modelClass::find($record->getKey()); // We do this to display also empty fields

        // Get formatted record
        return $this->getFormattedRecordToDisplay($record, $domain, $module);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(?Domain $domain, Module $module, int $id)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Get model model class
        $modelClass = $module->model_class;

        $record = $modelClass::find($id);

        if (!$record) {
            return $this->errorResponse(404, 'Record not found');
        }

        // Get formatted record
        return $this->getFormattedRecordToDisplay($record, $domain, $module);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Domain $domain, Module $module, int $id, Request $request)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Get model model class
        $modelClass = $module->model_class;
        $record = $modelClass::find($id);

        if (!$record) {
            return $this->errorResponse(404, 'Record not found');
        }

        foreach ($request->all() as $fieldName => $value) {
            $field = $module->getField($fieldName);

            // If the field exists format the value and store it in the good model column
            if (!is_null($field)) {
                $column = $field->column;
                $record->$column = $field->uitype->getFormattedValueToSave($request, $field, $value, $record, $domain, $module);
            }
        }

        // Dispatch before save event
        event(new BeforeSaveEvent($domain, $module, $request, $record, 'edit', true));

        // Save
        $record->save();

        // Dispatch after save event
        event(new AfterSaveEvent($domain, $module, $request, $record, 'edit', true));

        // Get formatted record
        return $this->getFormattedRecordToDisplay($record, $domain, $module);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Domain $domain, Module $module, $id, Request $request)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Get model model class
        $modelClass = $module->model_class;

        $record = $modelClass::find($id);

        if (!$record) {
            return $this->errorResponse(404, 'Record not found');
        }

        // Dispatch before delete event
        event(new BeforeDeleteEvent($domain, $module, $request, $record, true));

        // Delete
        $record->delete();

        // Dispatch after delete event
        event(new AfterDeleteEvent($domain, $module, $request, $record, true));

        return response()->json([
            "success" => true,
            "message" => 'Record deleted',
            "id" => $id
        ]);
    }
}
