<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Data Pengembalian</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        h2 {
            text-align: center;
            margin-bottom: 5px;
        }

        .tanggal-cetak {
            text-align: center;
            font-size: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .detail-denda {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

    <h2>Laporan Data Pengembalian</h2>

    <div class="tanggal-cetak">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Pengembalian</th>
                <th>ID Sewa</th>
                <th>No Faktur</th>
                <th>Pelanggan</th>
                <th>Motor</th>
                <th>Tanggal Pengembalian</th>
                <th>Status Denda</th>
                <th>Detail Denda</th>
                <th>Total Denda</th>
                <th>Keterangan</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($pengembalian as $index => $item)
                @php
                    $detailDenda = $item->detail_denda ?? [];

                    if (is_string($detailDenda)) {
                        $detailDenda = json_decode($detailDenda, true) ?? [];
                    }

                    if ($detailDenda instanceof \Illuminate\Support\Collection) {
                        $detailDenda = $detailDenda->toArray();
                    }

                    if (! is_array($detailDenda)) {
                        $detailDenda = [];
                    }
                @endphp

                <tr>
                    <td class="text-center">
                        {{ $index + 1 }}
                    </td>

                    <td class="text-center">
                        {{ $item->id_pengembalian ?? '-' }}
                    </td>

                    <td class="text-center">
                        {{ $item->id_sewa ?? '-' }}
                    </td>

                    <td>
                        {{ $item->penyewaan->no_faktur ?? '-' }}
                    </td>

                    <td>
                        {{ $item->penyewaan->pelanggan->nama_pelanggan ?? '-' }}
                    </td>

                    <td>
                        @forelse ($item->penyewaan->penyewaanMotor ?? [] as $detailMotor)
                            {{ $detailMotor->motor->nama_motor ?? '-' }} <br>
                        @empty
                            -
                        @endforelse
                    </td>

                    <td>
                        @if ($item->tgl_pengembalian)
                            {{ \Carbon\Carbon::parse($item->tgl_pengembalian)->format('d/m/Y') }}
                        @else
                            -
                        @endif
                    </td>

                    <td>
                        {{ $item->denda ?? '-' }}
                    </td>

                    <td>
                        @if (($item->denda ?? '') === 'Ada Denda' && count($detailDenda) > 0)
                            @foreach ($detailDenda as $i => $denda)
                                @php
                                    $nominal = preg_replace('/[^0-9]/', '', (string) ($denda['nominal'] ?? 0));
                                @endphp

                                <div class="detail-denda">
                                    <strong>Denda {{ $i + 1 }}:</strong><br>
                                    Jenis Denda: {{ $denda['jenis_denda'] ?? '-' }} <br>
                                    Nama Denda: {{ $denda['nama_denda'] ?? '-' }} <br>
                                    Nominal: Rp {{ number_format((float) $nominal, 0, ',', '.') }}
                                </div>
                            @endforeach
                        @else
                            Tidak ada denda
                        @endif
                    </td>

                    <td class="text-right">
                        Rp {{ number_format((float) ($item->total ?? 0), 0, ',', '.') }}
                    </td>

                    <td>
                        {{ $item->keterangan ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">
                        Tidak ada data pengembalian.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>