<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenyewaanResource\Pages;
use App\Models\Penyewaan;
use App\Models\PenyewaanMotor;
use App\Models\Pembayaran;
use App\Models\Pelanggan;
use App\Models\Motor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\HtmlString;

class PenyewaanResource extends Resource
{
    protected static ?string $model = Penyewaan::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Penyewaan';

    protected static ?string $navigationGroup = 'Transaksi';

    // =========================================================================
    // FORM
    // =========================================================================
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([

                    // =========================================================
                    // STEP 1 — DATA PENYEWAAN
                    // =========================================================
                    Wizard\Step::make('Penyewaan')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([

                            Section::make('Informasi Penyewaan')
                                ->icon('heroicon-m-document-text')
                                ->collapsible()
                                ->schema([
                                    TextInput::make('no_faktur')
                                        ->label('No Faktur')
                                        ->default(fn () => Penyewaan::getKodeFaktur())
                                        ->required()
                                        ->readonly(),

                                    DatePicker::make('tgl_sewa')
                                        ->label('Tanggal Sewa')
                                        ->default(today())
                                        ->required(),

                                    DatePicker::make('tgl_kembali')
                                        ->label('Tanggal Kembali (Estimasi)')
                                        ->required(),

                                    Select::make('pelanggan_id')
                                        ->label('Pelanggan')
                                        ->options(
                                            Pelanggan::query()
                                                ->pluck('nama_pelanggan', 'id')
                                                ->toArray()
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->placeholder('Pilih atau tambah pelanggan baru')
                                        ->createOptionForm([
                                            TextInput::make('nama_pelanggan')
                                                ->label('Nama Pelanggan')
                                                ->required()
                                                ->maxLength(255),
                                            TextInput::make('no_telepon')
                                                ->label('No. Telepon')
                                                ->required()
                                                ->tel()
                                                ->maxLength(20),
                                            Forms\Components\Textarea::make('alamat')
                                                ->label('Alamat')
                                                ->required()
                                                ->rows(3),
                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            return Pelanggan::firstOrCreate(
                                                ['no_telepon' => $data['no_telepon']],
                                                $data
                                            )->id;
                                        }),

                                    // Hidden — diisi via recalcTotal
                                    Hidden::make('total_harga')->default(0),
                                    Hidden::make('status_bayar')->default('belum_bayar'),
                                ])
                                ->columns(3),

                            // -------------------------------------------------
                            // Daftar Motor — Repeater
                            // -------------------------------------------------
                            Section::make('Daftar Motor')
                                ->icon('heroicon-m-list-bullet')
                                ->description('Tambahkan satu atau lebih motor untuk disewa.')
                                ->schema([
                                    Repeater::make('penyewaanMotor')
                                        // ->relationship('penyewaanMotor') // DIHAPUS — insert manual via simpanPenyewaan()
                                        ->label('')
                                        ->schema([
                                            Select::make('motor_id')
                                                ->label('Motor')
                                                ->options(
                                                    Motor::where('status', 'tersedia')
                                                        ->pluck('nama_motor', 'id')
                                                        ->toArray()
                                                )
                                                ->required()
                                                ->reactive()
                                                ->searchable()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->placeholder('Pilih Motor')
                                                ->columnSpan(2)
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    $motor = Motor::find($state);
                                                    $set('nama_motor',         $motor?->nama_motor ?? '');
                                                    $set('harga_sewa_perhari', $motor?->harga_sewa_perhari ?? 0);
                                                    $set('jml', 1);
                                                    $set('subtotal', (int) ($motor?->harga_sewa_perhari ?? 0));
                                                }),

                                            Hidden::make('nama_motor')->dehydrated(false),
                                            Hidden::make('harga_sewa_perhari')->dehydrated(false),

                                            // Durasi Sewa
                                            TextInput::make('jml')
                                                ->label('Durasi Sewa (Hari)')
                                                ->numeric()
                                                ->default(1)
                                                ->required()
                                                ->minValue(1)
                                                ->step(1)
                                                ->reactive()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                    $harga  = (float) ($get('harga_sewa_perhari') ?? 0);
                                                    $durasi = (int) ($state ?? 1);
                                                    $set('subtotal', (int) ($harga * $durasi));
                                                }),

                                            Placeholder::make('subtotal_display')
                                                ->label('Subtotal')
                                                ->content(fn (Get $get): string =>
                                                    'Rp ' . number_format(
                                                        (int) ($get('subtotal') ?? 0),
                                                        0, ',', '.'
                                                    )
                                                ),

                                            Hidden::make('subtotal')->dehydrated(),
                                            Hidden::make('tgl')->default(now()->toDateString()),
                                        ])
                                        ->columns(['md' => 4])
                                        ->addActionLabel('+ Tambah Motor')
                                        ->minItems(1)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalcTotal($get, $set);
                                        }),
                                ]),

                            // -------------------------------------------------
                            // Grand Total
                            // -------------------------------------------------
                            Section::make('Ringkasan Biaya')
                                ->icon('heroicon-m-calculator')
                                ->schema([
                                    Placeholder::make('grand_total_display')
                                        ->label('Grand Total Sewa')
                                        ->content(function (Get $get): string {
                                            $items = $get('penyewaanMotor') ?? [];
                                            $total = collect($items)->sum(fn ($i) => (int) ($i['subtotal'] ?? 0));
                                            return 'Rp ' . number_format($total, 0, ',', '.');
                                        }),
                                ])
                                ->columns(1),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('proses_penyewaan')
                                    ->label('Proses Penyewaan')
                                    ->color('primary')
                                    ->icon('heroicon-o-check-circle')
                                    ->action(function (Get $get) {
                                        self::simpanPenyewaan($get);
                                    }),
                            ]),
                        ]),

                    // =========================================================
                    // STEP 2 — PROSES
                    // =========================================================
                    Wizard\Step::make('Proses')
                        ->icon('heroicon-o-arrow-path')
                        ->schema([
                            Section::make('Status Penyewaan')
                                ->icon('heroicon-m-tag')
                                ->schema([
                                    Select::make('status_bayar')
                                        ->label('Status Pembayaran')
                                        ->options([
                                            'belum_bayar' => 'Belum Bayar',
                                            'lunas'       => 'Lunas',
                                        ])
                                        ->default('belum_bayar')
                                        ->required()
                                        ->native(false),

                                    Placeholder::make('info_proses')
                                        ->label('')
                                        ->content('Ubah status penyewaan sesuai kondisi saat ini. Ubah status ke "Lunas" jika pembayaran sudah diterima.'),
                                ])
                                ->columns(2),
                        ]),

                    // =========================================================
                    // STEP 3 — PEMBAYARAN
                    // =========================================================
                    Wizard\Step::make('Pembayaran')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make('Detail Pembayaran')
                                ->icon('heroicon-m-credit-card')
                                ->schema([
                                    Placeholder::make('total_tagihan_display')
                                        ->label('Total Tagihan')
                                        ->content(function (Get $get): string {
                                            $noFaktur  = $get('no_faktur');
                                            $penyewaan = Penyewaan::where('no_faktur', $noFaktur)->first();
                                            $nominal   = $penyewaan ? (float) $penyewaan->total_harga : 0;
                                            return 'Rp ' . number_format($nominal, 0, ',', '.');
                                        }),

                                    Select::make('metode')
                                        ->label('Metode Pembayaran')
                                        ->options([
                                            'tunai'    => 'Tunai',
                                            'midtrans' => 'Pembayaran Lain (Midtrans)',
                                        ])
                                        ->required()
                                        ->reactive()
                                        ->live()
                                        ->native(false)
                                        ->placeholder('Pilih Metode')
                                        ->dehydrated(false),

                                    TextInput::make('nominal_bayar')
                                        ->label('Nominal Uang Diterima (Rp)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required()
                                        ->live()
                                        ->minValue(0)
                                        ->dehydrated(false)
                                        ->visible(fn (Get $get) => $get('metode') === 'tunai')
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            $noFaktur  = $get('no_faktur');
                                            $penyewaan = Penyewaan::where('no_faktur', $noFaktur)->first();
                                            $tagihan   = $penyewaan ? (float) $penyewaan->total_harga : 0;
                                            $set('kembalian', max(0, (float) $state - $tagihan));
                                        }),

                                    Placeholder::make('metode_pembayaran_tersimpan')
                                        ->label('Metode Pembayaran Tersimpan')
                                        ->content(function (Get $get): string {
                                            $noFaktur  = $get('no_faktur');
                                            $penyewaan = Penyewaan::where('no_faktur', $noFaktur)->first();
                                            if (! $penyewaan) return '-';

                                            $pembayaran = \App\Models\Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();
                                            if (! $pembayaran) return 'Belum ada pembayaran';

                                            return match ($pembayaran->metode) {
                                                'tunai'    => 'Tunai',
                                                'midtrans' => 'Pembayaran Lain (Midtrans)',
                                                default    => $pembayaran->metode ?? '-',
                                            };
                                        })
                                        ->visible(fn (Get $get): bool =>
                                            \App\Models\Pembayaran::whereHas('penyewaan', fn ($q) =>
                                                $q->where('no_faktur', $get('no_faktur'))
                                            )->exists()
                                        ),

                                    Placeholder::make('kembalian')
                                        ->label('Kembalian')
                                        ->content(function (Get $get): string {
                                            $noFaktur  = $get('no_faktur');
                                            $penyewaan = Penyewaan::where('no_faktur', $noFaktur)->first();
                                            $tagihan   = $penyewaan ? (float) $penyewaan->total_harga : 0;
                                            $nominal   = (float) ($get('nominal_bayar') ?? 0);
                                            return 'Rp ' . number_format(max(0, $nominal - $tagihan), 0, ',', '.');
                                        })
                                        ->visible(fn (Get $get) => $get('metode') === 'tunai'),

                                    // ── MIDTRANS: auto-trigger snap popup ──────
                                    // Begitu pilih Midtrans, x-init Alpine langsung
                                    // fetch snap token & buka popup otomatis
                                    Placeholder::make('midtrans_snap')
                                        ->label('')
                                        ->columnSpan(2)
                                        ->content(function (Get $get): HtmlString {
                                            if ($get('metode') !== 'midtrans') {
                                                return new HtmlString('');
                                            }

                                            $noFaktur = e($get('no_faktur'));

                                            return new HtmlString(<<<HTML
                                            <div
                                                x-data="{
                                                    status: '⏳ Membuka halaman pembayaran Midtrans...',
                                                    statusClass: 'text-gray-500',
                                                    init() {
                                                        setTimeout(() => this.openSnap(), 700);
                                                    },
                                                    openSnap() {
                                                        fetch('/midtrans/snap-token', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                            },
                                                            body: JSON.stringify({ no_faktur: '{$noFaktur}' }),
                                                        })
                                                        .then(r => r.json())
                                                        
                                                        .then(data => {
                                                            if (!data.success) {
                                                                this.status = '❌ Gagal: ' + (data.message || 'Terjadi kesalahan.');
                                                                this.statusClass = 'text-red-600';
                                                                return;
                                                            }
                                                            this.status = '⏳ Menunggu pembayaran...';
                                                            snap.pay(data.snap_token, {
                                                                onSuccess: (result) => {
                                                                    this.status = '✅ Pembayaran berhasil! Order: ' + result.order_id;
                                                                    this.statusClass = 'text-green-600';
                                                                },
                                                                onPending: (result) => {
                                                                    this.status = '⏳ Menunggu pembayaran... Order: ' + result.order_id;
                                                                    this.statusClass = 'text-yellow-600';
                                                                },
                                                                onError: (result) => {
                                                                    this.status = '❌ Gagal: ' + result.status_message;
                                                                    this.statusClass = 'text-red-600';
                                                                },
                                                                onClose: () => {
                                                                    this.status = '💬 Popup ditutup. Pilih metode lain atau klik Midtrans lagi untuk membuka ulang.';
                                                                    this.statusClass = 'text-gray-500';
                                                                },
                                                            });
                                                        })
                                                        .catch(err => {
                                                            this.status = '❌ Error: ' + err.message;
                                                            this.statusClass = 'text-red-600';
                                                        });
                                                    }
                                                }"
                                            >
                                                <p class="text-sm font-medium" :class="statusClass" x-text="status"></p>
                                            </div>
                                            HTML);
                                        })
                                        ->visible(fn (Get $get) => $get('metode') === 'midtrans'),
                                ])
                                ->columns(2),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('simpan_pembayaran')
                                    ->label('Simpan Pembayaran')
                                    ->color('success')
                                    ->icon('heroicon-o-check-badge')
                                    ->action(function (Get $get) {
                                        $noFaktur  = $get('no_faktur');
                                        $penyewaan = Penyewaan::where('no_faktur', $noFaktur)->first();

                                        if (! $penyewaan) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Proses penyewaan terlebih dahulu!')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        $metode = $get('metode');
                                        if (! $metode) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Pilih metode pembayaran!')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        \App\Models\Pembayaran::updateOrCreate(
                                            ['penyewaan_id' => $penyewaan->id_sewa],
                                            [
                                                'tgl_bayar'        => now()->toDateString(),
                                                'metode'           => $metode,
                                                'transaction_time' => now(),
                                                'total_harga'      => $penyewaan->total_harga,
                                                'order_id'         => $penyewaan->no_faktur,
                                                'payment_type'     => $metode === 'tunai' ? 'cash' : 'pg',
                                                // Tunai: langsung 200. Midtrans: 201 pending (diupdate via webhook)
                                                'status_code'      => $metode === 'tunai' ? '200' : '201',
                                                'status_message'   => $metode === 'tunai'
                                                    ? 'Pembayaran tunai berhasil.'
                                                    : 'Pending payment via Midtrans.',
                                            ]
                                        );

                                        // Hanya tunai yang langsung lunas.
                                        // Midtrans: status diupdate via webhook handleCallback()
                                        if ($metode === 'tunai') {
                                            $penyewaan->update(['status_bayar' => 'lunas']);
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->title('Pembayaran berhasil disimpan!')
                                            ->success()
                                            ->send();
                                    }),
                            ]),
                        ]),

                ])->columnSpan('full'),
            ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_faktur')
                    ->label('No Faktur')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pelanggan.nama_pelanggan')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tgl_sewa')
                    ->label('Tgl Sewa')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('tgl_kembali')
                    ->label('Tgl Kembali')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('pembayaran.metode')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'tunai'    => 'info',
                        'transfer' => 'warning',
                        'midtrans' => 'primary',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'tunai'    => 'Tunai',
                        'transfer' => 'Transfer',
                        'midtrans' => 'Midtrans',
                        default    => ucfirst($state ?? '-'),
                    }),

                TextColumn::make('total_harga')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignment('end'),

                TextColumn::make('status_bayar')
                    ->label('Status Bayar')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'lunas'       => 'success',
                        'belum_bayar' => 'danger',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'lunas'       => 'Lunas',
                        'belum_bayar' => 'Belum Bayar',
                        default       => ucfirst($state ?? '-'),
                    }),
            ])
            ->filters([
                SelectFilter::make('status_bayar')
                    ->label('Status Bayar')
                    ->options([
                        'lunas'       => 'Lunas',
                        'belum_bayar' => 'Belum Bayar',
                    ]),

                SelectFilter::make('pembayaran_metode')
                    ->label('Metode Pembayaran')
                    ->options([
                        'tunai'    => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'midtrans' => 'Midtrans',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value']
                            ? $query->whereHas('pembayaran', fn ($q) => $q->where('metode', $data['value']))
                            : $query
                    ),
            ])
            ->actions([
                Action::make('tandaiLunas')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn ($record) =>
                        $record->status_bayar !== 'lunas' &&
                        in_array($record->metode, ['tunai', 'transfer'])
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status_bayar' => 'lunas',
                            'tgl_bayar'    => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Pembayaran ' . strtoupper($record->metode) . ' Berhasil!')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $penyewaan = Penyewaan::with(['pelanggan', 'penyewaanMotor.motor'])->get();
                        $pdf = Pdf::loadView('pdf.penyewaan', ['penyewaan' => $penyewaan]);
                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'penyewaan-list.pdf'
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['pelanggan', 'penyewaanMotor.motor']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPenyewaans::route('/'),
            'create' => Pages\CreatePenyewaan::route('/create'),
            'edit'   => Pages\EditPenyewaan::route('/{record}/edit'),
            'view'   => Pages\ViewPenyewaan::route('/{record}'),
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Recalculate total_harga = subtotal semua motor.
     */
    public static function recalcTotal(Get $get, Set $set): void
    {
        $items = $get('penyewaanMotor') ?? [];
        $total = collect($items)->sum(fn ($i) => (int) ($i['subtotal'] ?? 0));
        $set('total_harga', $total);
    }

    /**
     * Simpan / update penyewaan + penyewaan_motor dari tombol "Proses Penyewaan".
     */
    public static function simpanPenyewaan(Get $get): void
    {
        $noFaktur = trim($get('no_faktur'));

        if (empty($noFaktur)) {
            \Filament\Notifications\Notification::make()
                ->title('Nomor faktur tidak ditemukan!')
                ->danger()
                ->send();
            return;
        }

        $items = $get('penyewaanMotor') ?? [];

        // Hitung total harga dari database agar akurat
        $totalHarga = 0;
        foreach ($items as $item) {
            if (! empty($item['motor_id'])) {
                $motor        = Motor::find($item['motor_id']);
                $hargaPerHari = (float) ($motor?->harga_sewa_perhari ?? 0);
                $durasi       = (int) ($item['jml'] ?? 1);
                $totalHarga  += ($hargaPerHari * $durasi);
            }
        }

        \DB::transaction(function () use ($noFaktur, $get, $totalHarga, $items) {

            $penyewaan = Penyewaan::updateOrCreate(
                ['no_faktur' => $noFaktur],
                [
                    'pelanggan_id' => $get('pelanggan_id'),
                    'tgl_sewa'     => $get('tgl_sewa') ?? today(),
                    'tgl_kembali'  => $get('tgl_kembali'),
                    'total_harga'  => $totalHarga,
                    'status_bayar' => $get('status_bayar') ?? 'belum_bayar',
                ]
            );

            // Hapus detail lama lalu insert ulang agar tidak duplikat
            PenyewaanMotor::where('penyewaan_id', $penyewaan->id_sewa)->delete();

            foreach ($items as $item) {
                if (empty($item['motor_id'])) {
                    continue;
                }

                $motor        = Motor::find($item['motor_id']);
                $harga        = (float) ($motor?->harga_sewa_perhari ?? $item['harga_sewa_perhari'] ?? 0);
                $durasi       = (int) ($item['jml'] ?? 1);

                PenyewaanMotor::create([
                    'penyewaan_id'       => $penyewaan->id_sewa,
                    'motor_id'           => $item['motor_id'],
                    'harga_sewa_perhari' => $harga,
                    'jml'                => $durasi,
                    'subtotal'           => (int) ($harga * $durasi),
                    'tgl'                => $item['tgl'] ?? now()->toDateString(),
                ]);
            }
        });

        \Filament\Notifications\Notification::make()
            ->title('Penyewaan berhasil diproses!')
            ->success()
            ->send();
    }
}