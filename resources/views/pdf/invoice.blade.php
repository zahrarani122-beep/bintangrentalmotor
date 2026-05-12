<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 20px; }
        .divider { border-top: 2px solid #333; margin: 10px 0; }
        .divider-light { border-top: 1px dashed #aaa; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f0f0f0; padding: 6px; text-align: left; font-size: 12px; }
        td { padding: 6px; font-size: 12px; border-bottom: 1px solid #eee; white-space: nowrap; }
        .label { color: #666; width: 20%; }
        .value { width: 30%; }
        .total-box { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-top: 15px; }
        .grand-total { font-size: 16px; font-weight: bold; color: #c00; }
        .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>BINTANG RENTAL MOTOR</h2>
        <p>Invoice Penyewaan Motor</p>
    </div>
    <div class="divider"></div>

    <table>
        <tr>
            <td class="label">No Faktur</td>
            <td class="value">: {{ $penyewaan->no_faktur ?? '-' }}</td>
            <td class="label">Tgl Pengembalian</td>
            <td class="value">: {{ $pengembalian->tgl_pengembalian?->format('d M Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pelanggan</td>
            <td class="value">: {{ $penyewaan->pelanggan->nama_pelanggan ?? '-' }}</td>
            <td class="label">No Telepon</td>
            <td class="value">: {{ $penyewaan->pelanggan->no_telepon ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tgl Sewa</td>
            <td class="value">: {{ $penyewaan->tgl_sewa ?? '-' }}</td>
            <td class="label">Tgl Kembali</td>
            <td class="value">: {{ $penyewaan->tgl_kembali ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Metode Bayar</td>
            <td class="value">: {{ strtoupper($penyewaan->metode ?? '-') }}</td>
            <td></td><td></td>
        </tr>
    </table>

    <div class="divider-light"></div>
    <p><strong>Detail Motor yang Disewa</strong></p>
    <table>
        <thead>
            <tr>
                <th>Motor</th>
                <th>Harga/Hari</th>
                <th>Durasi</th>
                <th style="text-align:right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($penyewaan->penyewaanMotor as $item)
            <tr>
                <td>{{ $item->motor->nama_motor ?? '-' }}</td>
                <td>Rp{{ number_format($item->harga_sewa_perhari, 0, ',', '.') }}</td>
                <td>{{ $item->jml }} Hari</td>
                <td style="text-align:right">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($pengembalian->denda === 'Ada Denda')
    <div class="divider-light"></div>
    <p><strong>Detail Denda</strong></p>
    <table>
        <thead>
            <tr>
                <th>Jenis</th>
                <th>Item</th>
                <th style="text-align:right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pengembalian->detail_denda ?? [] as $denda)
            <tr>
                <td>{{ $denda['jenis_denda'] ?? '-' }}</td>
                <td>{{ $denda['nama_denda'] ?? '-' }}</td>
                <td style="text-align:right">Rp{{ number_format($denda['nominal'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="divider-light"></div>
    <div class="total-box">
        <table>
            <tr>
                <td class="label">Total Sewa</td>
                <td style="text-align:right">Rp{{ number_format($penyewaan->total_harga, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Total Denda</td>
                <td style="text-align:right">Rp{{ number_format($pengembalian->total, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="grand-total">GRAND TOTAL</td>
                <td style="text-align:right" class="grand-total">
                    Rp{{ number_format($penyewaan->total_harga + $pengembalian->total, 0, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Terima kasih telah menggunakan layanan Bintang Rental Motor</p>
        <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>
</body>
</html>