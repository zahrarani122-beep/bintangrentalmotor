<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                // tambahan untuk dashboardstats
                \App\Filament\Widgets\DashboardStatCards::class,
                // tambahan untuk total penyewaan bar chart
                \App\Filament\Widgets\TotalPenyewaanChart::class,
                // tambahan untuk total penyewaan per bulan di tahun ini
                \App\Filament\Widgets\PenyewaanPerBulanChart::class,
                // tambahan untuk total penjualan tiap barang
                // \App\Filament\Widgets\PersentasePenjualanBarangChart::class,
                //// tambahan untuk total penjualan per pembeli tahun ini
                //\App\Filament\Widgets\TotalPenjualanPerPembeliChart::class,
                // tambahan untuk total debit vs kredit per bulan
                \App\Filament\Widgets\JurnalChartWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('
                    <script
                        src="https://app.sandbox.midtrans.com/snap/snap.js"
                        data-client-key="{{ config("midtrans.client_key") }}">
                    </script>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            Livewire.on("open-midtrans", function(event) {
                                if (typeof snap === "undefined") {
                                    console.error("Midtrans Snap belum ter-load");
                                    return;
                                }

                                let token = event.token || event.detail?.token;

                                if (!token) {
                                    console.error("Token tidak ditemukan");
                                    return;
                                }

                                snap.pay(token, {
                                    onSuccess: function(result) {
                                        console.log("Success:", result);
                                        setTimeout(() => window.location.reload(), 1500);
                                    },
                                    onPending: function(result) {
                                        console.log("Pending:", result);
                                        alert("Pembayaran sedang menunggu.");
                                    },
                                    onError: function(result) {
                                        console.log("Error:", result);
                                        alert("Pembayaran gagal.");
                                    },
                                    onClose: function() {
                                        console.log("Popup ditutup");
                                        alert("Pembayaran dibatalkan.");
                                    }
                                });
                            });
                        });
                    </script>
                ')
            );
    }
}