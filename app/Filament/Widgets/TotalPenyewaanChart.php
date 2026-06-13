<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Penyewaan;

class TotalPenyewaanChart extends ChartWidget
{
    protected static ?string $heading = 'Pendapatan Penyewaan Motor';

    protected function getData(): array
    {
        $data = Penyewaan::query()
        ->join(
            'penyewaan_motor',
            'penyewaan.id_sewa',
            '=',
            'penyewaan_motor.penyewaan_id'
        )
        ->join(
            'motor',
            'penyewaan_motor.motor_id',
            '=',
            'motor.id'
        )
        ->selectRaw('
            motor.nama_motor,
            SUM(penyewaan_motor.subtotal) as total_pendapatan
        ')
        ->groupBy('motor.nama_motor')
        ->get();

        if ($data->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Penyewaan',
                    'data' => $data->pluck('total_pendapatan')->toArray(),
                    'backgroundColor' => '#36A2EB',
                ],
            ],
            'labels' => $data->pluck('nama_motor')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    protected function getOptions(): array
{
    return [
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'max' => 10,
                'ticks' => [
                    'stepSize' => 1,
                    'precision' => 0,
                    'autoSkip' => false,
                ],
            ],
        ],
    ];
}
}