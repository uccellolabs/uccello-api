<?php

namespace Uccello\Api\Support;

use Illuminate\Http\Request;
use Uccello\Core\Events\AfterSaveEvent;
use Uccello\Core\Events\BeforeSaveEvent;
use Uccello\Core\Fields\Uitype;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;

trait ImageUploadTrait
{
    /**
     * Upload an image and modify related record.
     *
     * @param  \Uccello\Core\Models\Domain $domain
     * @param  \Uccello\Core\Models\Module $module
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function uploadImage(Domain $domain, Module $module, Request $request)
    {
        // Retrieve record
        $modelClass = $module->model_class;
        $record = $modelClass::find($request->id);

        if (!$record) {
            return $this->errorResponse(404, 'Record not found');
        }

        // Retrieve field from fieldName
        $fieldName = $request->field;
        $field = $module->fields->where('name', $fieldName)->first();

        if (!$record) {
            return $this->errorResponse(400, 'Field not found');
        }

        if ($record && $field && $field->uitype_id === uitype('image')->id) {
            $uitype = new Uitype\Image();
            $value = $uitype->getFormattedValueToSave($request, $field, null, $record, $domain, $module);

            // Update record
            $record->{$field->column} = $value;

            // Dispatch before save event
            event(new BeforeSaveEvent($domain, $module, $request, $record, 'create', true));

            $record->save();

            // Dispatch after save event
            event(new AfterSaveEvent($domain, $module, $request, $record, 'create', true));

            // After save
            $this->afterImgSave($domain, $module, $request, $record);
        }

        return $record;
    }



    /**
     * Specific after save for images
     *
     * @param \Uccello\Core\Models\Domain $domain
     * @param \Uccello\Core\Models\Module $module
     * @param \Illuminate\Http\Request $request
     * @param mixed $record
     * @param Stdclass $recordFromRequest
     * @return void
     */
    protected function afterImgSave(Domain $domain, Module $module, Request $request, $record)
    {
        // Can be overrided
    }
}