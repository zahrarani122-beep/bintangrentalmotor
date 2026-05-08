<table class="table-auto w-full border-collapse border border-gray-300">
    <thead>
        <tr class="bg-gray-200">
            <th class="border border-gray-300 px-4 py-2">
                No Faktur
            </th>

            <th class="border border-gray-300 px-4 py-2">
                Pelanggan
            </th>

            <th class="border border-gray-300 px-4 py-2">
                Tanggal Sewa
            </th>

            <th class="border border-gray-300 px-4 py-2">
                Tanggal Kembali
            </th>

            <th class="border border-gray-300 px-4 py-2">
                Durasi
            </th>
        </tr>
    </thead>

    <tbody>

        @foreach($penyewaans as $penyewaan)

            <tr>

                <td class="border border-gray-300 px-4 py-2">
                    {{ $penyewaan->no_faktur }}
                </td>

                <td class="border border-gray-300 px-4 py-2">
                    {{ $penyewaan->pelanggan->nama_pelanggan ?? '-' }}
                </td>

                <td class="border border-gray-300 px-4 py-2">
                    {{ $penyewaan->tgl_sewa }}
                </td>

                <td class="border border-gray-300 px-4 py-2">
                    {{ $penyewaan->tgl_kembali }}
                </td>

                <td class="border border-gray-300 px-4 py-2">
                    {{ $penyewaan->durasi_sewa }} Hari
                </td>

            </tr>

        @endforeach

    </tbody>
</table>