<?php

use Illuminate\Support\Facades\Route;
use Uccello\Core\Facades\Uccello;

Route::name('api.uccello.')
->group(function () {

    $domainAndModuleParams = '{domain}/{module}';

    // Auth
    Route::post('auth/login', 'ApiAuthController@login')->name('auth.login');
    Route::get('auth/logout', 'ApiAuthController@logout')->name('auth.logout');
    Route::get('auth/me', 'ApiAuthController@me')->name('auth.me');
    Route::get('auth/domains', 'ApiAuthController@domains')->name('auth.domains');
    Route::get('auth/{domain}/modules', 'ApiAuthController@modules')->name('auth.modules');
    Route::get('auth/{domain}/capabilities', 'ApiAuthController@capabilities')->name('auth.capabilities');

    // CRUD
    Route::get($domainAndModuleParams.'/describe', 'ApiController@describe')->name('describe')->middleware('uccello-api.permissions:retrieve');
    Route::get($domainAndModuleParams, 'ApiController@index')->name('index')->middleware('uccello-api.permissions:retrieve');
    Route::post($domainAndModuleParams.'/search', 'ApiController@search')->name('search')->middleware('uccello-api.permissions:retrieve');
    Route::get($domainAndModuleParams.'/{id}', 'ApiController@show')->name('show')->middleware('uccello-api.permissions:retrieve');
    Route::post($domainAndModuleParams, 'ApiController@store')->name('store')->middleware('uccello-api.permissions:create');
    Route::match(['post','put', 'patch'], $domainAndModuleParams.'/{id}', 'ApiController@update')->name('update')->middleware('uccello-api.permissions:update');
    Route::delete($domainAndModuleParams.'/{id}', 'ApiController@destroy')->name('destroy')->middleware('uccello-api.permissions:delete');
    Route::post($domainAndModuleParams.'/upload_img', 'ApiController@uploadImage')->name('upload_image')->middleware('uccello-api.permissions:update');

    // Sync
    Route::match(['get', 'post'], $domainAndModuleParams.'/sync/download', 'SyncController@download')->name('sync.download')->middleware('uccello-api.permissions:retrieve');
    Route::post($domainAndModuleParams.'/sync/upload', 'SyncController@upload')->name('sync.upload')->middleware('uccello-api.permissions:create');
    Route::post($domainAndModuleParams.'/sync/upload_img', 'SyncController@uploadImage')->name('sync.upload_image')->middleware('uccello-api.permissions:update');
    Route::post($domainAndModuleParams.'/sync/delete', 'SyncController@delete')->name('sync.delete')->middleware('uccello-api.permissions:delete');
    Route::post($domainAndModuleParams.'/sync/latest', 'SyncController@latest')->name('sync.latest')->middleware('uccello-api.permissions:retrieve');
});
