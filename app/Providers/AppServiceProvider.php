<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure morph map for polymorphic relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'quote' => \App\Models\Quote::class,
            'jobcard' => \App\Models\Jobcard::class,
        ]);
    }
}
