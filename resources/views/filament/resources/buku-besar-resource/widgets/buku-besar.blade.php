<x-filament-widgets::widget>
    <x-filament::section>
        
        <div class="overflow-x-auto">

            <!-- Filter Periode Jurnal -->
            <!-- Akhir Filter Periode Jurnal-->

            <!-- Tambahan filter -->
            <div class="row">

          
                    <form wire:submit.prevent="filterJurnal" class="flex gap-4 items-center">
                        <div>
                            <label for="periode_awal">Periode Awal:</label>
                            <input type="month" wire:model="periode_awal" id="periode_awal" class="border rounded px-2 py-1">
                        </div>
                        <div>
                            <label for="periode_akhir">Periode Akhir:</label>
                            <input type="month" wire:model="periode_akhir" id="periode_akhir" class="border rounded px-2 py-1">
                        </div>
                        <div>
                            <label for="coa_id">Akun COA:</label>
                            <select wire:model="coa_id" id="coa_id" class="border rounded px-2 py-1">
                                <option value="">-- Pilih Akun --</option>
                                @foreach (\App\Models\Akun::all() as $akun)
                                    <option value="{{ $akun->id }}">{{ $akun->kode_akun }} - {{ $akun->nama_akun }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-green-500 text-black px-3 py-1 rounded mt-4">
                                Filter
                            </button>
                        </div>
                    </form>

            
                <br><br>
               
                <div class="col-sm-12" style="background-color:white;" align="center">
                    <b>Toko Mukena</b><br>
                    <b>Buku Besar</b><br>
                    <b>
                    Periode 
                        @if($periode_awal && $periode_akhir)
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $periode_awal)->translatedFormat('F Y') }}
                            -
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $periode_akhir)->translatedFormat('F Y') }}
                        @else
                            {{ now()->translatedFormat('F Y') }}
                        @endif
                </div>
               <br>
            </div>
            <!-- Akhir Tambahan Filter -->

            <table class="w-full text-sm text-left border border-gray-200 font-sans">
                <thead class="bg-gray-100 text-xs uppercase">
                    <tr class="font-semibold bg-gray-200">
                            <td colspan="4" class="text-right px-4 py-2 border">Saldo Awal</td>
                            <td colspan="2" class="text-right px-4 py-2 border">{{ rupiah($saldoAwal) }}</td>
                        </tr>
                    <tr>
                        <th class="px-4 py-2 border">ID Jurnal</th>
                        <th class="px-4 py-2 border">Tanggal</th>
                        <th class="px-4 py-2 border">Akun</th>
                        <th class="px-4 py-2 border">Reff</th>
                        <th class="px-4 py-2 border">Debet</th>
                        <th class="px-4 py-2 border">Kredit</th>
                    </tr>
                </thead>
                <tbody class="text-xs uppercase">
                    @foreach($jurnals as $jurnal)
                        @foreach($jurnal->jurnaldetail as $detail)
                            <tr>
                                <td class="px-4 py-2 border">{{ $jurnal->id }}</td>
                                <td class="px-4 py-2 border">{{ \Carbon\Carbon::parse($jurnal->tgl)->format('Y-m-d') }}</td>
                                
                                {{-- Hanya tampilkan kolom jika debit ≠ 0 --}}
                                @if($detail->debit != 0)
                                    <td class="px-4 py-2 border">{{ $detail->coa->nama_akun ?? '-' }}</td>
                                    <td class="px-4 py-2 border">{{ $jurnal->no_referensi }}</td>
                                    <td class="px-4 py-2 border text-right">{{ rupiah($detail->debit) }}</td>
                                @else
                                    <td class="px-4 py-2 border">&nbsp;&nbsp;&nbsp;{{ $detail->coa->nama_akun ?? '-' }}</td>
                                    <td class="px-4 py-2 border">{{ $jurnal->no_referensi }}</td>
                                    <td class="px-4 py-2 border text-right"></td>
                                @endif

                                {{-- Hanya tampilkan kolom jika credit ≠ 0 --}}
                                @if($detail->credit != 0)
                                    <td class="px-4 py-2 border text-right">{{ rupiah($detail->credit) }}</td>
                                @else
                                    <td class="px-4 py-2 border text-right"></td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                    <tfoot class="font-semibold text-xs bg-gray-200">
                        @php
                            $totalDebit = $jurnals->flatMap->jurnaldetail->sum('debit');
                            $totalKredit = $jurnals->flatMap->jurnaldetail->sum('credit');
                            $saldoAkhir = $saldoAwal + ($totalDebit - $totalKredit);
                        @endphp
                        <tr class="font-semibold bg-gray-100">
                            <td colspan="4" class="text-right px-4 py-2 border">Total</td>
                            <td class="text-right px-4 py-2 border">{{ rupiah($totalDebit) }}</td>
                            <td class="text-right px-4 py-2 border">{{ rupiah($totalKredit) }}</td>
                        </tr>
                        <tr class="font-semibold bg-gray-200">
                            <td colspan="4" class="text-right px-4 py-2 border">Saldo Akhir</td>
                            <td colspan="2" class="text-right px-4 py-2 border">{{ rupiah($saldoAkhir) }}</td>
                        </tr>
                    </tfoot>

            </table>
        </div>


    </x-filament::section>
</x-filament-widgets::widget>