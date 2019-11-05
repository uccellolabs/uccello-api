<?php

namespace Uccello\Api\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
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
     * Adds formatted record's data to display
     *
     * @param mixed $record
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @return mixed
     */
    protected function getFormattedRecordToDisplay($record, Domain $domain, Module $module)
    {
        foreach ($module->fields as $field) {
            $uitype = uitype($field->uitype_id);

            // If field name is not defined, it could be because the coloumn name is different.
            // Adds field name as a key of the record
            if (!$record->getAttributeValue($field->name) && $field->column !== $field->name) {
                $record->setAttribute($field->name, $record->getAttributeValue($field->column));
            }

            // // If a special template exists, add it.
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
}