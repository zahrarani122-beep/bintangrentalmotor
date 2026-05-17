<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

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
//     public function boot(): void
//     {
//         // Untuk ngrok (https lokal)
//         if (config('app.env') === 'local') {
//             \URL::forceScheme('https');
//         }

//         // Inject Midtrans Snap.js ke head Filament admin panel
//         FilamentView::registerRenderHook(
//             'panels::head.end',
//             fn () => Blade::render(
//                 '<script src="https://app.sandbox.midtrans.com/snap/snap.js" 
//                     data-client-key="{{ config(\'midtrans.client_key\') }}"></script>'
//             ),
//         );
//     }
}