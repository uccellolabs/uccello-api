<?php

namespace Uccello\Api\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\Searchable\Search;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;

trait ApiTrait
{
    /**
     * Prepare query.
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @return \Illuminate\Database\Eloquent\Builder;
     */
    protected function prepareQueryForApi(Domain $domain, Module $module): Builder
    {
        $modelClass = $module->model_class;
        $query = $modelClass::query();

        $query = $this->addDomainsConditions($domain, $module, $query);
        $query = $this->addOrderByClause($domain, $module, $query);
        $query = $this->addWithClause($domain, $module, $query);
        $query = $this->addWhereClause($domain, $module, $query);
        $query = $this->addSelectClause($domain, $module, $query); // The last one else all columns are retrieved

        return $query;
    }

    /**
     * Adds select into the query according to request params
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addSelectClause(Domain $domain, Module $module, Builder $query): Builder
    {
        $columnsToSelect = $this->getColumnsToSelect();

        if ($columnsToSelect) {
            $query->select($columnsToSelect);
        }

        return $query;
    }

    /**
     * Adds domains filter into the query according to request params and user's roles
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addDomainsConditions(Domain $domain, Module $module, Builder $query): Builder
    {
        $modelClass = $module->model_class;

        // Filter on domain if column exists
        $query = $modelClass::inDomain($domain, request('descendants'));

        return $query;
    }

    /**
     * Adds order by clause into the query according to request params
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addOrderByClause(Domain $domain, Module $module, Builder $query): Builder
    {
        if (request()->has('order')) {
            $orderByParams = explode(';', request('order'));

            foreach ($orderByParams as $orderByParam) {
                $orderBy = explode(',', $orderByParam);
                if (count($orderBy) === 1) {
                    $orderBy[] = 'asc';
                }

                $query->orderBy($orderBy[0], $orderBy[1]);
            }
        }

        return $query;
    }

    /**
     * Adds with clause into the query according to request params
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addWithClause(Domain $domain, Module $module, Builder $query): Builder
    {
        if (request()->has('with')) {
            $withParams = explode(';', request('with'));

            foreach ($withParams as $withParam) {
                $query->with($withParam);
            }
        }

        return $query;
    }

    /**
     * Adds where clause into the query according to request params
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addWhereClause(Domain $domain, Module $module, Builder $query): Builder
    {
        if (request()->has('q')) {
            $q = request('q');

            $modelClass = $module->model_class;

            if (method_exists($modelClass, 'getSearchResult') && property_exists($modelClass, 'searchableColumns')) {
                // Search related records and get all ids
                $searchResults = new Search();
                $searchResults->registerModel($modelClass, (array) (new $modelClass)->searchableColumns);
                $recordIds = $searchResults->search($q)->pluck('searchable.id');

                // Search records linked to record ids got previously
                $query->whereIn((new $modelClass)->getKeyName(), $recordIds);
            }
        }

        return $query;
    }

    /**
     * Adds formatted record's data to display
     *
     * @param mixed $record
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @return mixed
     */
    protected function getFormattedRecordToDisplay($record, Domain $domain, Module $module)
    {
        $columnsToSelect = $this->getColumnsToSelect();

        foreach ($module->fields as $field) {
            // We don't want to get formatted values if the field is ignored
            if (!empty($columnsToSelect) && !in_array($field->column, $columnsToSelect)) {
                continue;
            }

            $uitype = uitype($field->uitype_id);

            // If field name is not defined, it could be because the coloumn name is different.
            // Adds field name as a key of the record
            if ($uitype->name !== 'entity' && !$record->getAttributeValue($field->name) && $field->column !== $field->name) {
                $record->setAttribute($field->name, $record->getAttributeValue($field->column));
            }

            // If a special template exists, add it.
            $formattedValue = $uitype->getFormattedValueToDisplay($field, $record);
            if ($formattedValue && $formattedValue !== $record->getAttributeValue($field->name)) {
                $record->setAttribute($field->name.'_formatted', $formattedValue);
            }
        }

        return $record;
    }

    /**
     * Returns a json formatted error reponse
     *
     * @param [type] $statusCode
     * @param string $message
     * @return \Illuminate\Http\Response
     */
    protected function errorResponse($statusCode, string $message)
    {
        return response()->json(['message' => $message], $statusCode);
    }

    /**
     * Returns length to use with pagination.
     * We can define the length with a request param or in the .env file.
     * If the length is greater than the max allowed length, the max length is used.
     *
     * @return int
     */
    protected function getPaginationLength()
    {
        $length = request('length', config('uccello.api.items_per_page', 100));
        $maxLength = config('uccello.api.max_items_per_page', 100);

        if ($length >  $maxLength) {
            $length = $maxLength;
        }

        return $length;
    }

    protected function getColumnsToSelect()
    {
        $selectParams = [];

        if (request()->has('select')) {
            $selectParams = explode(';', request('select'));
        }

        return $selectParams;
    }
}
