<?php



namespace App\Filament\Resources;



use App\Filament\Resources\PenyewaanResource\Pages;

use App\Models\Motor;

use App\Models\Pelanggan;

use App\Models\Pembayaran;

use App\Models\Penyewaan;

use App\Models\PenyewaanMotor;

use Barryvdh\DomPDF\Facade\Pdf;

use Filament\Forms;

use Filament\Forms\Components\DatePicker;

use Filament\Forms\Components\Hidden;

use Filament\Forms\Components\Placeholder;

use Filament\Forms\Components\Repeater;

use Filament\Forms\Components\Section;

use Filament\Forms\Components\Select;

use Filament\Forms\Components\TextInput;

use Filament\Forms\Components\Wizard;

use Filament\Forms\Form;

use Filament\Forms\Get;

use Filament\Forms\Set;

use Filament\Resources\Resource;

use Filament\Tables;

use Filament\Tables\Actions\Action;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Table;

use Illuminate\Support\HtmlString;



class PenyewaanResource extends Resource

{

    protected static ?string $model = Penyewaan::class;



    protected static ?string $navigationIcon  = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Penyewaan';

    protected static ?string $navigationGroup = 'Transaksi';



    // =========================================================================

    // FORM

    // =========================================================================



    public static function form(Form $form): Form

    {

        return $form->schema([

            Wizard::make([

                self::stepPenyewaan(),

                self::stepProses(),

                self::stepPembayaran(),

            ])->columnSpan('full'),

        ]);

    }



    // -------------------------------------------------------------------------

    // STEP 1 — DATA PENYEWAAN

    // -------------------------------------------------------------------------



    private static function stepPenyewaan(): Wizard\Step

    {

        return Wizard\Step::make('Penyewaan')

            ->icon('heroicon-o-clipboard-document-list')

            ->schema([

                self::sectionInformasiPenyewaan(),

                self::sectionDaftarMotor(),

                self::sectionRingkasanBiaya(),

                self::actionProsesPenyewaan(),

            ]);

    }



    private static function sectionInformasiPenyewaan(): Section

    {

        return Section::make('Informasi Penyewaan')

            ->icon('heroicon-m-document-text')

            ->collapsible()

            ->columns(3)

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

                    ->options(Pelanggan::query()->pluck('nama_pelanggan', 'id')->toArray())

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

                    ->createOptionUsing(fn (array $data): int =>

                        Pelanggan::firstOrCreate(['no_telepon' => $data['no_telepon']], $data)->id

                    ),



                Hidden::make('total_harga')->default(0),

                Hidden::make('status_bayar')->default('belum_bayar'),

            ]);

    }



    private static function sectionDaftarMotor(): Section

    {

        return Section::make('Daftar Motor')

            ->icon('heroicon-m-list-bullet')

            ->description('Tambahkan satu atau lebih motor untuk disewa.')

            ->schema([

                Repeater::make('penyewaanMotor')

                    ->label('')

                    ->columns(['md' => 4])

                    ->addActionLabel('+ Tambah Motor')

                    ->minItems(1)

                    ->required()

                    ->live()

                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcTotal($get, $set))

                    ->schema([

                        Select::make('motor_id')

                            ->label('Motor')

                            ->options(Motor::where('status', 'tersedia')->pluck('nama_motor', 'id')->toArray())

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

                                $set('jml',                1);

                                $set('subtotal',           (int) ($motor?->harga_sewa_perhari ?? 0));

                            }),



                        Hidden::make('nama_motor')->dehydrated(false),

                        Hidden::make('harga_sewa_perhari')->dehydrated(false),



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

                                $durasi = (int)   ($state ?? 1);

                                $set('subtotal', (int) ($harga * $durasi));

                            }),



                        Placeholder::make('subtotal_display')

                            ->label('Subtotal')

                            ->content(fn (Get $get): string =>

                                'Rp ' . number_format((int) ($get('subtotal') ?? 0), 0, ',', '.')

                            ),



                        Hidden::make('subtotal')->dehydrated(),

                        Hidden::make('tgl')->default(now()->toDateString()),

                    ]),

            ]);

    }



    private static function sectionRingkasanBiaya(): Section

    {

        return Section::make('Ringkasan Biaya')

            ->icon('heroicon-m-calculator')

            ->columns(1)

            ->schema([

                Placeholder::make('grand_total_display')

                    ->label('Grand Total Sewa')

                    ->content(function (Get $get): string {

                        $total = collect($get('penyewaanMotor') ?? [])

                            ->sum(fn ($i) => (int) ($i['subtotal'] ?? 0));

                        return 'Rp ' . number_format($total, 0, ',', '.');

                    }),

            ]);

    }



    private static function actionProsesPenyewaan(): Forms\Components\Actions

    {

        return Forms\Components\Actions::make([

            Forms\Components\Actions\Action::make('proses_penyewaan')

                ->label('Proses Penyewaan')

                ->color('primary')

                ->icon('heroicon-o-check-circle')

                ->action(fn (Get $get) => self::simpanPenyewaan($get)),

        ]);

    }



    // -------------------------------------------------------------------------

    // STEP 2 — PROSES

    // -------------------------------------------------------------------------



    private static function stepProses(): Wizard\Step

    {

        return Wizard\Step::make('Proses')

            ->icon('heroicon-o-arrow-path')

            ->schema([

                Section::make('Status Penyewaan')

                    ->icon('heroicon-m-tag')

                    ->columns(2)

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

                    ]),

            ]);

    }



    // -------------------------------------------------------------------------

    // STEP 3 — PEMBAYARAN

    // -------------------------------------------------------------------------



    private static function stepPembayaran(): Wizard\Step

    {

        return Wizard\Step::make('Pembayaran')

            ->icon('heroicon-o-banknotes')

            ->schema([

                Section::make('Detail Pembayaran')

                    ->icon('heroicon-m-credit-card')

                    ->columns(2)

                    ->schema([

                        Placeholder::make('total_tagihan_display')

                            ->label('Total Tagihan')

                            ->content(function (Get $get): string {

                                $penyewaan = Penyewaan::where('no_faktur', $get('no_faktur'))->first();

                                return 'Rp ' . number_format((float) ($penyewaan?->total_harga ?? 0), 0, ',', '.');

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



                        // Tunai: input nominal & kembalian

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

                                $penyewaan = Penyewaan::where('no_faktur', $get('no_faktur'))->first();

                                $tagihan   = (float) ($penyewaan?->total_harga ?? 0);

                                $set('kembalian', max(0, (float) $state - $tagihan));

                            }),



                        Placeholder::make('kembalian')

                            ->label('Kembalian')

                            ->content(function (Get $get): string {

                                $penyewaan = Penyewaan::where('no_faktur', $get('no_faktur'))->first();

                                $tagihan   = (float) ($penyewaan?->total_harga ?? 0);

                                $nominal   = (float) ($get('nominal_bayar') ?? 0);

                                return 'Rp ' . number_format(max(0, $nominal - $tagihan), 0, ',', '.');

                            })

                            ->visible(fn (Get $get) => $get('metode') === 'tunai'),



                        // Info metode yang sudah tersimpan

                        Placeholder::make('metode_pembayaran_tersimpan')

                            ->label('Metode Pembayaran Tersimpan')

                            ->content(function (Get $get): string {

                                $penyewaan  = Penyewaan::where('no_faktur', $get('no_faktur'))->first();

                                if (! $penyewaan) return '-';



                                $pembayaran = Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();

                                if (! $pembayaran) return 'Belum ada pembayaran';



                                return match ($pembayaran->metode) {

                                    'tunai'    => 'Tunai',

                                    'midtrans' => 'Pembayaran Lain (Midtrans)',

                                    default    => $pembayaran->metode ?? '-',

                                };

                            })

                            ->visible(fn (Get $get): bool =>

                                Pembayaran::whereHas('penyewaan', fn ($q) =>

                                    $q->where('no_faktur', $get('no_faktur'))

                                )->exists()

                            ),



                        // Midtrans Snap popup

                        Placeholder::make('midtrans_snap')

                            ->label('')

                            ->columnSpan(2)

                            ->content(fn (Get $get): HtmlString => self::midtransSnapHtml($get))

                            ->visible(fn (Get $get) => $get('metode') === 'midtrans'),

                    ]),



                self::actionSimpanPembayaran(),

            ]);

    }



    /**

     * Generate HTML Alpine.js untuk Midtrans Snap popup.

     *

     * Fix utama:

     * - Gunakan localStorage per no_faktur agar state `paid` bertahan

     *   meski Livewire/Filament re-render component (Alpine reset ulang).

     * - Guard di init() cek localStorage sebelum buka popup.

     * - onClose hanya tampil pesan jika belum bayar.

     * - Response `paid: true` dari server (sudah settlement) ditampilkan

     *   langsung tanpa membuka popup.

     */

    private static function midtransSnapHtml(Get $get): HtmlString

    {

        if ($get('metode') !== 'midtrans') {

            return new HtmlString('');

        }



        $noFaktur = e($get('no_faktur'));



        return new HtmlString(<<<HTML

        <div

            x-data="{

                status:      '⏳ Membuka halaman pembayaran Midtrans...',

                statusClass: 'text-gray-500',

                paid:        false,

                storageKey:  'midtrans_paid_{$noFaktur}',



                init() {

                    // Cek localStorage — jika sudah pernah bayar, jangan buka popup lagi

                    if (localStorage.getItem(this.storageKey) === 'true') {

                        this.paid        = true;

                        this.status      = '✅ Pembayaran sudah terbayar.';

                        this.statusClass = 'text-green-600';

                        return;

                    }

                    setTimeout(() => this.openSnap(), 700);

                },



                markPaid() {

                    this.paid = true;

                    localStorage.setItem(this.storageKey, 'true');

                },



                openSnap() {

                    fetch('/midtrans/snap-token', {

                        method:  'POST',

                        headers: {

                            'Content-Type':  'application/json',

                            'X-CSRF-TOKEN':  document.querySelector('meta[name=csrf-token]').content,

                        },

                        body: JSON.stringify({ no_faktur: '{$noFaktur}' }),

                    })

                    .then(r => r.json())

                    .then(data => {

                        // Server: transaksi sudah settlement di Midtrans

                        if (!data.success && data.paid) {

                            this.markPaid();

                            this.status      = '✅ Pembayaran sudah terbayar sebelumnya.';

                            this.statusClass = 'text-green-600';

                            return;

                        }



                        // Gagal generate token

                        if (!data.success) {

                            this.status      = '❌ Gagal: ' + (data.message || 'Terjadi kesalahan.');

                            this.statusClass = 'text-red-600';

                            return;

                        }



                        this.status = '⏳ Menunggu pembayaran...';



                        snap.pay(data.snap_token, {

                            onSuccess: (result) => {

                                this.markPaid();

                                this.status      = '✅ Pembayaran berhasil! Order: ' + result.order_id;

                                this.statusClass = 'text-green-600';

                            },

                            onPending: (result) => {

                                this.markPaid();

                                this.status      = '⏳ Menunggu konfirmasi... Order: ' + result.order_id;

                                this.statusClass = 'text-yellow-600';

                            },

                            onError: (result) => {

                                this.status      = '❌ Gagal: ' + result.status_message;

                                this.statusClass = 'text-red-600';

                            },

                            onClose: () => {

                                // Hanya tampil pesan tutup jika belum berhasil bayar

                                if (!this.paid) {

                                    this.status      = '💬 Popup ditutup. Pilih metode lain atau klik Midtrans lagi.';

                                    this.statusClass = 'text-gray-500';

                                }

                            },

                        });

                    })

                    .catch(err => {

                        this.status      = '❌ Error: ' + err.message;

                        this.statusClass = 'text-red-600';

                    });

                },

            }"

        >

            <p class="text-sm font-medium" :class="statusClass" x-text="status"></p>

        </div>

        HTML);

    }



    private static function actionSimpanPembayaran(): Forms\Components\Actions

    {

        return Forms\Components\Actions::make([

            Forms\Components\Actions\Action::make('simpan_pembayaran')

                ->label('Simpan Pembayaran')

                ->color('success')

                ->icon('heroicon-o-check-badge')

                ->action(function (Get $get) {

                    $penyewaan = Penyewaan::where('no_faktur', $get('no_faktur'))->first();



                    if (! $penyewaan) {

                        self::notif('Proses penyewaan terlebih dahulu!', 'warning');

                        return;

                    }



                    $metode = $get('metode');

                    if (! $metode) {

                        self::notif('Pilih metode pembayaran!', 'warning');

                        return;

                    }



                    Pembayaran::updateOrCreate(

                        ['penyewaan_id' => $penyewaan->id_sewa],

                        [

                            'tgl_bayar'        => now()->toDateString(),

                            'metode'           => $metode,

                            'transaction_time' => now(),

                            'total_harga'      => $penyewaan->total_harga,

                            'order_id'         => $penyewaan->no_faktur,

                            'payment_type'     => $metode === 'tunai' ? 'cash' : 'pg',

                            // Tunai: langsung 200. Midtrans: 201 pending → diupdate via webhook

                            'status_code'    => $metode === 'tunai' ? '200' : '201',

                            'status_message' => $metode === 'tunai'

                                ? 'Pembayaran tunai berhasil.'

                                : 'Pending payment via Midtrans.',

                        ]

                    );



                    // Tunai langsung lunas; Midtrans menunggu webhook handleCallback()

                    if ($metode === 'tunai') {

                        $penyewaan->update(['status_bayar' => 'lunas']);

                    }



                    self::notif('Pembayaran berhasil disimpan!', 'success');

                }),

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

                    ->visible(fn ($record) => $record->status_bayar !== 'lunas')

                    ->requiresConfirmation()

                    ->action(function ($record) {

                        $record->update(['status_bayar' => 'lunas']);

                        self::notif('Pembayaran Berhasil Ditandai Lunas!', 'success');

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

                        $pdf       = Pdf::loadView('pdf.penyewaan', compact('penyewaan'));

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



    // =========================================================================

    // RELATIONS & PAGES

    // =========================================================================



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

     * Recalculate total_harga dari semua subtotal motor di repeater.

     */

    public static function recalcTotal(Get $get, Set $set): void

    {

        $total = collect($get('penyewaanMotor') ?? [])

            ->sum(fn ($i) => (int) ($i['subtotal'] ?? 0));

        $set('total_harga', $total);

    }



    /**

     * Simpan / update penyewaan + penyewaan_motor dari tombol "Proses Penyewaan".

     */

    public static function simpanPenyewaan(Get $get): void

    {

        $noFaktur = trim($get('no_faktur'));



        if (empty($noFaktur)) {

            self::notif('Nomor faktur tidak ditemukan!', 'danger');

            return;

        }



        $items      = $get('penyewaanMotor') ?? [];

        $totalHarga = 0;



        foreach ($items as $item) {

            if (! empty($item['motor_id'])) {

                $motor       = Motor::find($item['motor_id']);

                $totalHarga += (float) ($motor?->harga_sewa_perhari ?? 0) * (int) ($item['jml'] ?? 1);

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

                if (empty($item['motor_id'])) continue;



                $motor  = Motor::find($item['motor_id']);

                $harga  = (float) ($motor?->harga_sewa_perhari ?? $item['harga_sewa_perhari'] ?? 0);

                $durasi = (int)   ($item['jml'] ?? 1);



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



        self::notif('Penyewaan berhasil diproses!', 'success');

    }



    /**

     * Helper shorthand untuk mengirim Filament notification.

     */

    private static function notif(string $title, string $type = 'success'): void

    {

        \Filament\Notifications\Notification::make()

            ->title($title)

            ->{$type}()

            ->send();

    }

}