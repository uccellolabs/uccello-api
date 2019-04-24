<?php

Route::middleware('api')
->namespace('Uccello\Api\Http\Controllers')
->name('api.uccello.')
->prefix('api')
->group(function() {

    // Adapt params if we use or not multi domains
    if (!uccello()->useMultiDomains()) {
        $domainAndModuleParams = '{module}';
    } else {
        $domainAndModuleParams = '{domain}/{module}';
    }

    Route::post('login', 'ApiAuthController@login')->name('login');
    Route::get('logout', 'ApiAuthController@logout')->name('logout');
    Route::get('me', 'ApiAuthController@me')->name('me');
    Route::get('refresh', 'ApiAuthController@refresh')->name('refresh');

    Route::resource($domainAndModuleParams, 'ApiController')->middleware('auth.api');
});
