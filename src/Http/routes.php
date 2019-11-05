<?php

Route::name('api.uccello.')
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
    Route::get($domainAndModuleParams, 'ApiController@index')->name('index')->middleware('uccello.permissions:api-retrieve');
    Route::get($domainAndModuleParams.'/{id}', 'ApiController@show')->name('show')->middleware('uccello.permissions:api-retrieve');
    Route::post($domainAndModuleParams, 'ApiController@store')->name('store')->middleware('uccello.permissions:create');
    Route::match(['put', 'patch'], $domainAndModuleParams, 'ApiController@update')->name('update')->middleware('uccello.permissions:api-update');
    Route::delete($domainAndModuleParams.'/{id}', 'ApiController@destroy')->name('destroy')->middleware('uccello.permissions:api-delete');
    Route::post($domainAndModuleParams.'/upload_img', 'ApiController@uploadImage')->name('upload_image')->middleware('uccello.permissions:api-update');

    // Sync
    Route::match(['get', 'post'], $domainAndModuleParams.'/sync/download', 'SyncController@download')->name('sync.download')->middleware('uccello.permissions:api-retrieve');
    Route::post($domainAndModuleParams.'/sync/upload', 'SyncController@upload')->name('sync.upload')->middleware('uccello.permissions:api-create');
    Route::post($domainAndModuleParams.'/sync/upload_img', 'SyncController@uploadImage')->name('sync.upload_image')->middleware('uccello.permissions:api-update');
    Route::post($domainAndModuleParams.'/sync/delete', 'SyncController@delete')->name('sync.delete')->middleware('uccello.permissions:api-delete');
    Route::post($domainAndModuleParams.'/sync/latest', 'SyncController@latest')->name('sync.latest')->middleware('uccello.permissions:api-retrieve');
});