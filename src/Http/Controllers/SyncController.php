<?php

namespace Uccello\Api\Http\Controllers;

use Carbon\Carbon;
use Schema;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;
use Uccello\Core\Events\BeforeSaveEvent;
use Uccello\Core\Events\AfterSaveEvent;
class SyncController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resources.
     * Filter on domain if domain_id column exists.
     * Filter on updated_at if date param exists. This allows not to download all records.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function download(Domain $domain, Module $module, Request $request)
    {
        // Get current datetime
        $syncedAt = Carbon::now()->format('Y-m-d H:i:s');

        // Get model model class
        $modelClass = $module->model_class;

        $query = $modelClass::query();

        // Filter on domain if column exists
        if (Schema::hasColumn((new $modelClass)->getTable(), 'domain_id')) {
            // Paginate results
            $query = $query->where('domain_id', $domain->id);
        }

        // Filter results on the update_at date if necessary
        if ($request->date) {
            $date = new Carbon($request->date);
            $query = $query->where('created_at', '>=', $date)
                ->orWhere('updated_at', '>=', $date);

                //TODO: Add list of deleted records
        }

        // Launch query
        $records = $query->get();

        // Get formatted records
        $records->transform(function ($record) use ($domain, $module) {
            return $this->getFormattedRecord($record, $domain, $module);
        });

        return response()->json([
            'app_url' => env('APP_URL'),
            'primary_key' => (new $modelClass)->getKeyName(),
            'count' => $records->count(),
            'records' => $records,
            'synced_at' => $syncedAt,
        ]);
    }

    /**
     * Adds to the database a list of new records and returns an array with all of them.
     * This allows to get all fields like id or created_at.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Domain $domain, Module $module, Request $request)
    {
        if (!$request->records) {
            return $this->errorResponse(406, 'You must defined a list of records.');
        }

        // Get model model class
        $modelClass = $module->model_class;

        $records = collect();

        foreach ((array) $request->records as $_record) {
            $record = new $modelClass();

            if (Schema::hasColumn((new $modelClass)->getTable(), 'domain_id')) {
                // Paginate results
                $record->domain_id = $domain->id;
            }

            foreach ($_record as $fieldName => $value) {
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

            // Add formatted values
            foreach ($module->fields as $field) {
                // If a special template exists, use it. Else use the generic template
                $uitype = uitype($field->uitype_id);
                $record->{$field->name} = $uitype->getFormattedValueToDisplay($field, $record);
            }

            // Get formatted record
            $record = $this->getFormattedRecord($record, $domain, $module);

            $records[] = $record;
        }

        return $records;
    }

    protected function getFormattedRecord($record, $domain, $module)
    {
        foreach ($module->fields as $field) {
            // If a special template exists, use it. Else use the generic template
            $uitype = uitype($field->uitype_id);
            $record->{$field->name} = $uitype->getFormattedValueToDisplay($field, $record);
        }

        return $record;
    }

    protected function errorResponse($statusCode, $message)
    {
        return response()->json(['message' => $message], $statusCode);
    }
}