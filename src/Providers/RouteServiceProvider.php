<?php

namespace Uccello\Api\Providers;

use App\Providers\RouteServiceProvider as DefaultRouteServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Route Service Provider
 */
class RouteServiceProvider extends DefaultRouteServiceProvider
{
    /**
     * @inheritDoc
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * @inheritDoc
     */
    public function map()
    {
        parent::map();

        $this->mapUccelloRoutes();
    }

    /**
     * Define "uccello" routes for the application.
     *
     * @return void
     */
    protected function mapUccelloRoutes()
    {
        // Web
        Route::middleware('web', 'auth')
            ->namespace('Uccello\Api\Http\Controllers')
            ->group(__DIR__.'/../Http/routes-web.php');

        // Api
        Route::middleware('api')
            ->namespace('Uccello\Api\Http\Controllers')
            ->prefix('api')
            ->group(__DIR__.'/../Http/routes.php');
    }
}