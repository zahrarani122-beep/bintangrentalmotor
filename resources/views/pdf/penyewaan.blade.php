<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penyewaan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h2 { text-align: center; margin-bottom: 4px; }
        p.subtitle { text-align: center; margin: 0 0 16px; font-size: 11px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #2d6a4f; color: #fff; padding: 7px 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) td { background-color: #f4f4f4; }
        .badge-lunas    { color: #1a7a3c; font-weight: bold; }
        .badge-belum    { color: #c0392b; font-weight: bold; }
        .text-right     { text-align: right; }
        .footer { margin-top: 24px; font-size: 10px; color: #999; text-align: right; }
    </style>
</head>
<body>

    <h2>Laporan Penyewaan Motor</h2>
    <p class="subtitle">Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>No Faktur</th>
                <th>Pelanggan</th>
                <th>Tgl Sewa</th>
                <th>Tgl Kembali</th>
                <th>Motor</th>
                <th class="text-right">Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($penyewaan as $row)
                <tr>
                    <td>{{ $row->no_faktur }}</td>
                    <td>{{ $row->pelanggan?->nama_pelanggan ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->tgl_sewa)->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->tgl_kembali)->format('d/m/Y') }}</td>
                    <td>
                        @foreach ($row->penyewaanMotor as $pm)
                            {{ $pm->motor?->nama_motor ?? '-' }}
                            ({{ $pm->jml }} hari)<br>
                        @endforeach
                    </td>
                    <td class="text-right">
                        Rp {{ number_format((float) $row->total_harga, 0, ',', '.') }}
                    </td>
                    <td>
                        @if ($row->status_bayar === 'lunas')
                            <span class="badge-lunas">Lunas</span>
                        @else
                            <span class="badge-belum">Belum Bayar</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding: 16px;">
                        Tidak ada data penyewaan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total {{ $penyewaan->count() }} transaksi &nbsp;|&nbsp;
        Total Pendapatan: Rp {{ number_format($penyewaan->sum('total_harga'), 0, ',', '.') }}
    </div>

</body>
</html>