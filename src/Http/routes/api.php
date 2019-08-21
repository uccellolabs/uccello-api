<?php

Route::middleware('api')
->namespace('\Uccello\Api\Http\Controllers')
->name('api.uccello.')
->prefix('api')
->group(function() {

    // Adapt params if we use or not multi domains
    if (!uccello()->useMultiDomains()) {
        $domainAndModuleParams = '{module}';
    } else {
        $domainAndModuleParams = '{domain}/{module}';
    }

    // Auth
    Route::post('auth/login', 'ApiAuthController@login')->name('auth.login');
    Route::get('auth/logout', 'ApiAuthController@logout')->name('auth.logout');
    Route::get('auth/me', 'ApiAuthController@me')->name('auth.me');
    Route::get('auth/refresh', 'ApiAuthController@refresh')->name('auth.refresh');

    // CRUD
    Route::get($domainAndModuleParams, 'ApiController@index')->name('index')->middleware('uccello.permissions:retrieve');
    Route::get($domainAndModuleParams.'/{id}', 'ApiController@show')->name('show')->middleware('uccello.permissions:retrieve');
    Route::post($domainAndModuleParams, 'ApiController@store')->name('store')->middleware('uccello.permissions:create');
    Route::match(['put', 'patch'], $domainAndModuleParams, 'ApiController@update')->name('update')->middleware('uccello.permissions:update');
    Route::delete($domainAndModuleParams.'/{id}', 'ApiController@destroy')->name('destroy')->middleware('uccello.permissions:delete');

    // Sync
    Route::get($domainAndModuleParams.'/sync/download', 'SyncController@download')->name('sync.download')->middleware('uccello.permissions:retrieve');
    Route::post($domainAndModuleParams.'/sync/upload', 'SyncController@upload')->name('sync.upload')->middleware('uccello.permissions:create');
});
