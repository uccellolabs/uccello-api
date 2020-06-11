<?php

namespace Uccello\Api\Http\Controllers;

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Uccello\Api\Notifications\SyncErrorNotification;
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
        // Get model class
        $modelClass = $module->model_class;

        // Get current datetime
        $syncedAt = Carbon::now()->format('Y-m-d H:i:s');

        // Throws exception if needed
        if (config('uccello.api.throws_exception')) {
            try {
                $records = $this->__download($domain, $module, $request);
            } catch (\Exception $e) {
                $this->sendExceptionByEmail($module, $e);
            }
        } else {
            $records = $this->__download($domain, $module, $request);
        }

        // Get nextPageUrl
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
        if (!isset($request->records)) {
            return $this->errorResponse(406, 'You must defined a list of records. e.g: {"records": [...]}');
        }

        // Get current datetime
        $syncedAt = Carbon::now()->format('Y-m-d H:i:s');

        // Get model model class
        $modelClass = $module->model_class;
        $primaryKeyName = (new $modelClass)->getKeyName();

        // Throws exception if needed
        if (config('uccello.api.throws_exception')) {
            try {
                $records = $this->__upload($domain, $module, $request);
            } catch (\Exception $e) {
                $this->sendExceptionByEmail($module, $e);
            }
        } else {
            $records = $this->__upload($domain, $module, $request);
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
     * Retrieves all records ids for all records with a more recent version.
     * It retrieves all records by their ids and compares updated_at values.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function latest(Domain $domain, Module $module, Request $request)
    {
        if (!isset($request->records)) {
            return $this->errorResponse(406, 'You must defined a list of records with updated dates. e.g: {"records": [{"id":1,"updated_at":"2018-04-01T15:31:52.859Z"},{"id":2,"updated_at":"2019-11-01T14:26:46"}]}');
        }

        // Throws exception if needed
        if (config('uccello.api.throws_exception')) {
            try {
                $latest = $this->__latest($domain, $module, $request);
            } catch (\Exception $e) {
                $this->sendExceptionByEmail($module, $e);
            }
        } else {
            $latest = $this->__latest($domain, $module, $request);
        }

        return $latest;
    }

    /**
     * Deletes records by uuids.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function delete(Domain $domain, Module $module, Request $request)
    {
        if (!isset($request->uuids)) {
            return $this->errorResponse(406, 'You must defined a list of uuids. e.g: {"uuids": ["597eece0-ffd8-11e9-b1d2-5d4a94ec6c2b"]}');
        }

        // Throws exception if needed
        if (config('uccello.api.throws_exception')) {
            try {
                $deletedUuids = $this->__delete($domain, $module, $request);
            } catch (\Exception $e) {
                $this->sendExceptionByEmail($module, $e);
            }
        } else {
            $deletedUuids = $this->__delete($domain, $module, $request);
        }

        return response()->json([
            'deleted_uuids' => $deletedUuids,
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

        foreach (request()->query() as $param => $value) { // Only query params (from url)
            if ($param === 'page') {
                continue;
            }

            $url .= "&$param=$value";
        }

        return $url;
    }

    /**
     * Create new record.
     * domain_id is fillable if user can create by API on the domain defined.
     *
     * @param Domain $domain
     * @param Module $module
     * @param [type] $recordFromRequest
     * @return void
     */
    protected function createNewRecord(Domain $domain, Module $module, $recordFromRequest)
    {
        // Get model model class
        $modelClass = $module->model_class;
        $record = new $modelClass();

        if (Schema::hasColumn((new $modelClass)->getTable(), 'domain_id')) {
            if (isset($recordFromRequest->domain_id)) {
                $_domain = ucdomain($recordFromRequest->domain_id);
            }

            if (isset($_domain) && auth()->user()->canCreateByApi($_domain, $module)) {
                $record->domain_id = $recordFromRequest->domain_id;
            } else {
                $record->domain_id = $domain->id;
            }
        }

        return $record;
    }

    /**
     * Prepare record to save.
     * Search in request if param exists.
     * Priority:
     *  1. Column name
     *  2. Field name
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
        foreach ($module->fields as $field) {
            // Ignore domain id (for security)
            if ($field->column === 'domain_id') {
                continue;
            }

            $changeValue = false;

            // We use property_exists() because we want to change value even if is null
            if (property_exists($recordFromRequest, $field->column)) {
                $value = $recordFromRequest->{$field->column};
                $changeValue = true;
            } elseif (property_exists($recordFromRequest, $field->name)) {
                $value = $recordFromRequest->{$field->name};
                $changeValue = true;
            } else {
                $value = null;
            }

            if ($changeValue) {
                $record->{$field->column} = $value;
            }
        }

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

    protected function sendExceptionByEmail($module, $e)
    {
        if (config('uccello.api.username_to_notify_on_exception')) {
            $usernames = explode(';', config('uccello.api.username_to_notify_on_exception'));
        }

        if (!empty($usernames)) {
            foreach ($usernames as $username) {
                $user = User::where('username', $username)->first();
                if ($user) {
                    $user->notify(new SyncErrorNotification(
                        uctrans($module->name, $module),
                        $e->getMessage(),
                        $e->getTraceAsString()
                    ));
                }
            }
        }
    }

    /**
     * Download records.
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function __download(Domain $domain, Module $module, Request $request)
    {
        $modelClass = $module->model_class;
        $primaryKeyName = (new $modelClass)->getKeyName();

        // Prepare query
        $query = $this->prepareQueryForApi($domain, $module);

        // Filter results on the update_at date if necessary
        if ($request->date) {
            $date = new Carbon($request->date);
            $query->where('created_at', '>=', $date)
                ->orWhere('updated_at', '>=', $date);

                //TODO: Add list of deleted records
        }

        // Filter results on primary key
        if ($request->ids) {
            $filteredIds = (array) $request->ids;
            if ($filteredIds) {
                $query->whereIn($primaryKeyName, $filteredIds);
            }
        }

        // Add eventualy deleted record
        if ($request->only_deleted == 1) {
            $query->onlyTrashed();
        } elseif ($request->with_deleted == 1) {
            $query->withTrashed();
        }

        // Get pagination length
        $length = $this->getPaginationLength();

        // Launch query (retrieve also deleted record)
        $records = $query->paginate($length);

        // Get formatted records
        $records->getCollection()->transform(function ($record) use ($domain, $module) {
            return $this->getFormattedRecordToDisplay($record, $domain, $module);
        });

        return $records;
    }

    /**
     * Upload records
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function __upload(Domain $domain, Module $module, Request $request)
    {
        // Get model model class
        $modelClass = $module->model_class;
        $primaryKeyName = (new $modelClass)->getKeyName();

        $records = collect();

        foreach ((array) $request->records as $recordFromRequest) {
            $recordFromRequest = json_decode(json_encode($recordFromRequest)); // To transform into an object

            if (!empty($recordFromRequest->{$primaryKeyName})) {
                $record = $modelClass::find($recordFromRequest->{$primaryKeyName});
            }

            if (empty($record)) {
                $record = $this->createNewRecord($domain, $module, $recordFromRequest);
            }

            // Prepare record to save
            $record = $this->getPreparedRecordToSave($domain, $module, $request, $record, $recordFromRequest);

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
            $this->afterRecordSave($domain, $module, $request, $record, $recordFromRequest);

            $record = $modelClass::find($record->getKey()); // We do this to display also empty fields

            // Get formatted record -> create an exception, because columns do not exist !!!
            $record = $this->getFormattedRecordToDisplay($record, $domain, $module);

            $records[] = $record;

            // Unset $record else it is used in the next loop and id is defined
            unset($record);
        }

        return $records;
    }

    /**
     * Retrieves all records ids for all records with a more recent version.
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function __latest(Domain $domain, Module $module, Request $request)
    {
        // Get model model class
        $modelClass = $module->model_class;
        $primaryKeyName = (new $modelClass)->getKeyName();

        $query = $modelClass::query();
        $query = $this->addDomainsConditions($domain, $module, $query);

        // Can override updated_at column name
        $updatedAtColumn = $request->updated_at ?? 'updated_at';

        $latest = collect();

        foreach ((array) $request->records as $_record) {
            $_record = json_decode(json_encode($_record)); // To transform into an object

            $updatedDate = Carbon::parse($_record->updated_at);
            $record = (clone $query)->where($primaryKeyName, $_record->{$primaryKeyName})
                ->where($updatedAtColumn, '>', $updatedDate)
                ->first();

            if ($record) {
                $latest->push($record->getKey());
            }
        }

        return $latest;
    }

    /**
     * Deletes recoreds by uuid
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function __delete(Domain $domain, Module $module, Request $request)
    {
        // Get model model class
        $deletedUuids = collect();

        foreach ((array) $request->uuids as $uuid) {
            $record = ucrecord($uuid);
            if ($record && $record->module->id === $module->id) {
                $record->delete();
                $deletedUuids->push($uuid);
            }
        }

        return $deletedUuids;
    }
}
