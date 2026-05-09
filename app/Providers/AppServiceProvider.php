<?php

namespace App\Providers;

use App\Services\Metadata\CompositeMetadataProvider;
use App\Services\Metadata\MetadataProvider;
use App\Services\Metadata\MovistarMetadataProvider;
use App\Services\Metadata\OmdbMetadataProvider;
use App\Services\Metadata\TmdbMetadataProvider;
use App\Services\Metadata\TvmazeMetadataProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Chain: TMDB → OMDb → TVmaze → Movistar+. Movistar is last because it
        // scrapes a public sitemap + ficha pages (no API), so it's the slowest
        // and least precise — but it covers titles only available on the Spanish
        // premium tier that the upstream providers tend to miss.
        $this->app->singleton(MetadataProvider::class, function ($app) {
            return new CompositeMetadataProvider([
                $app->make(TmdbMetadataProvider::class),
                $app->make(OmdbMetadataProvider::class),
                $app->make(TvmazeMetadataProvider::class),
                $app->make(MovistarMetadataProvider::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
