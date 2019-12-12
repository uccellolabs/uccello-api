<?php

Route::name('api.uccello.')
->group(function() {

    // Adapt params if we use or not multi domains
    if (!uccello()->useMultiDomains()) {
        $domainParam = '';
    } else {
        $domainParam = '{domain}';
    }

    // Doc
    Route::get($domainParam.'/api/doc/json', 'SwaggerController@docs')->name('doc.json');
    Route::get($domainParam.'/api/doc', 'SwaggerController@api')->name('doc');
});