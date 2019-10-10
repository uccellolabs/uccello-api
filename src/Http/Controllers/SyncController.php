<?php

namespace Uccello\Api\Http\Controllers;

use Carbon\Carbon;
use Schema;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Uccello\Api\Support\ApiTrait;
use Uccello\Api\Support\ImageUploadTrait;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;
use Uccello\Core\Events\BeforeSaveEvent;
use Uccello\Core\Events\AfterSaveEvent;

class SyncController extends Controller
{
    use ApiTrait;
    use ImageUploadTrait;

    //TODO: Without multi domains (domain = null)

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

        $modelClass = $module->model_class;

        // Prepare query
        $query = $this->prepareQueryForApi($domain, $module);

        // Filter results on the update_at date if necessary
        if ($request->date) {
            $date = new Carbon($request->date);
            $query = $query->where('created_at', '>=', $date)
                ->orWhere('updated_at', '>=', $date);

                //TODO: Add list of deleted records
        }

        // Launch query
        $records = $query->paginate(env('UCCELLO_API_SYNC_PAGINATE_PER_PAGE', 100));

        // Get formatted records
        $records->getCollection()->transform(function ($record) use ($domain, $module) {
            return $this->getFormattedRecordToDisplay($record, $domain, $module);
        });

        $nextPageUrl = $this->getDownloadNextPageUrl($records->nextPageUrl());

        return response()->json([
            'app_url' => env('APP_URL'),
            'primary_key' => (new $modelClass)->getKeyName(),
            'records' => $records->getCollection(),
            'current_page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'next_page_url' => $nextPageUrl,
            'synced_at' => $syncedAt,
            'from' => $records->firstItem(),
            'to' => $records->lastItem() ?? 0,
            'total' => $records->total(),
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

        // Get current datetime
        $syncedAt = Carbon::now()->format('Y-m-d H:i:s');

        // Get model model class
        $modelClass = $module->model_class;
        $primaryKeyName = (new $modelClass)->getKeyName();

        $records = collect();

        foreach ((array) $request->records as $_record) {
            $_record = json_decode(json_encode($_record)); // To transform into an object

            if (!empty($_record->{$primaryKeyName})) {
                $record = $modelClass::find($_record->{$primaryKeyName});
            }

            if (empty($record)) {
                $record = new $modelClass();

                if (Schema::hasColumn((new $modelClass)->getTable(), 'domain_id')) {
                    if (!empty($_record->domain_id)) {
                        $record->domain_id = $_record->domain_id; //TODO: Check if user has create capability on this domain (security lack)
                    } else {
                        $record->domain_id = $domain->id;
                    }
                }
            }

            // Prepare record to save
            $record = $this->getPreparedRecordToSave($domain, $module, $request, $record, $_record);

            foreach ($record as $fieldName => $value) {
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

            // After save
            $this->afterRecordSave($domain, $module, $request, $record, $_record);

            $record = $modelClass::find($record->getKey()); // We do this to display also empty fields

            // Get formatted record
            // $record = $this->getFormattedRecordToDisplay($record, $domain, $module);

            $records[] = $record;
        }

        return response()->json([
            'app_url' => env('APP_URL'),
            'primary_key' => $primaryKeyName,
            'count' => $records->count(),
            'records' => $records,
            'synced_at' => $syncedAt,
        ]);
    }

    /**
     * Returns next page url with request params.
     * Givent that $url already contains 'page' param, don't add it twice.
     *
     * @param string $url
     * @return string
     */
    protected function getDownloadNextPageUrl($url)
    {
        if (!$url) {
            return null;
        }

        foreach (request()->all() as $param => $value) {
            if ($param === 'page') {
                continue;
            }

            $url .= "&$param=$value";
        }

        return $url;
    }

    /**
     * Prepare record to save
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     * @param mixed $record
     * @param Stdclass $recordFromRequest
     * @return mixed
     */
    protected function getPreparedRecordToSave(Domain $domain, Module $module, Request $request, $record, $recordFromRequest)
    {
        // Can be overrided
        return $record;
    }

    /**
     * Specific after save
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     * @param mixed $record
     * @param Stdclass $recordFromRequest
     * @return void
     */
    protected function afterRecordSave(Domain $domain, Module $module, Request $request, $record, $recordFromRequest)
    {
        // Can be overrided
    }
}