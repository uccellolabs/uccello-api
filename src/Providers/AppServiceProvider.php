<?php

namespace Uccello\Api\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * App Service Provider
 */
class AppServiceProvider extends ServiceProvider
{
  /**
   * Indicates if loading of the provider is deferred.
   *
   * @var bool
   */
  protected $defer = false;

  public function boot()
  {
    // Translations
    $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'uccello-api');

    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
  }
}