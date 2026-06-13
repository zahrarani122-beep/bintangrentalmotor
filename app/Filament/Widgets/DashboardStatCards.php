<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use App\Models\Pelanggan;
use App\Models\Penyewaan;
use App\Models\Pembayaran;
use App\Models\Motor;

class DashboardStatCards extends BaseWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make('Total Pelanggan', Pelanggan::count())
                ->description('Jumlah pelanggan terdaftar'),

            Stat::make('Total Penyewaan', Penyewaan::count())
                ->description('Jumlah transaksi penyewaan'),

            Stat::make(
                'Total Pendapatan',
                'Rp ' . number_format(
                    Pembayaran::sum('total_harga'),
                    0,
                    ',',
                    '.'
                )
            )
                ->description('Total pendapatan penyewaan'),

        ];
    }
}