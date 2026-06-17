<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

// tambahan akses ke model
use App\Models\JurnalDetail;
// menggunakan komponen carbon
use Carbon\Carbon;

class JurnalChartWidget extends ChartWidget
{
    protected static ?string $heading = null; // akan di-override di bawah

    public function getHeading(): string
    {
        return 'Debit & Kredit Per Bulan ' . date('Y');
    }

    protected function getData(): array
    {
        // Tahun yang ingin ditampilkan
        $year = now()->year;

        // Ambil total debit per bulan
        $debits = JurnalDetail::query()
            ->join('jurnal', 'jurnal_detail.jurnal_id', '=', 'jurnal.id')
            ->whereYear('jurnal.tgl', $year)
            ->selectRaw('MONTH(jurnal.tgl) as month, SUM(jurnal_detail.debit) as total_debit')
            ->groupBy('month')
            ->pluck('total_debit', 'month');

        // Ambil total kredit per bulan
        $credits = JurnalDetail::query()
            ->join('jurnal', 'jurnal_detail.jurnal_id', '=', 'jurnal.id')
            ->whereYear('jurnal.tgl', $year)
            ->selectRaw('MONTH(jurnal.tgl) as month, SUM(jurnal_detail.credit) as total_credit')
            ->groupBy('month')
            ->pluck('total_credit', 'month');

        // Siapkan semua bulan (1–12)
        $allMonths = collect(range(1, 12));

        // Gabungkan semua bulan dengan hasil query
        $dataDebit = $allMonths->map(fn ($month) => $debits->get($month, 0));
        $dataCredit = $allMonths->map(fn ($month) => $credits->get($month, 0));

        $labels = $allMonths->map(function ($month) {
            return Carbon::create()->month($month)->locale('id')->translatedFormat('F');
        });

        return [
            'datasets' => [
                [
                    'label'           => 'Total Debit',
                    'data'            => $dataDebit,
                    'backgroundColor' => '#36A2EB', // biru
                    'borderColor'     => '#36A2EB',
                ],
                [
                    'label'           => 'Total Kredit',
                    'data'            => $dataCredit,
                    'backgroundColor' => '#FF6384', // merah
                    'borderColor'     => '#FF6384',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // bar chart untuk perbandingan debit vs kredit
    }
}