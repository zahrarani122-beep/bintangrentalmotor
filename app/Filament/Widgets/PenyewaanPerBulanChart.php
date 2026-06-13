<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Penyewaan;
use Carbon\Carbon;

class PenyewaanPerBulanChart extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return 'Pendapatan Penyewaan Per Bulan ' . date('Y');
    }

    protected function getData(): array
    {
        $year = now()->year;

        $orders = Penyewaan::query()
            ->join(
                'penyewaan_motor',
                'penyewaan.id_sewa',
                '=',
                'penyewaan_motor.penyewaan_id'
            )
            ->whereYear('penyewaan.tgl_sewa', $year)
            ->selectRaw('
                MONTH(penyewaan.tgl_sewa) as month,
                SUM(penyewaan_motor.subtotal) as total_pendapatan
            ')
            ->groupBy('month')
            ->pluck('total_pendapatan', 'month');

        $allMonths = collect(range(1, 12));

        $data = $allMonths->map(function ($month) use ($orders) {
            return (float) $orders->get($month, 0);
        });

        $labels = $allMonths->map(function ($month) {
            return Carbon::create()
                ->month($month)
                ->locale('id')
                ->translatedFormat('F');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan Penyewaan (Rp)',
                    'data' => $data,
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#36A2EB',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}