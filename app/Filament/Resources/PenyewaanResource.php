<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenyewaanResource\Pages;
use App\Models\Penyewaan;

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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;

use Filament\Forms\Get;
use Filament\Forms\Set;

use App\Models\Pelanggan;
use App\Models\Motor;

class PenyewaanResource extends Resource
{
    protected static ?string $model = Penyewaan::class;

    protected static ?string $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Penyewaan';
    protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([

                    /*
                    |----------------------------------------------------------
                    | STEP 1 : DATA PELANGGAN
                    |----------------------------------------------------------
                    */
                    Wizard\Step::make('Data Pelanggan')
                        ->schema([
                            Section::make('Pilih Pelanggan')
                                ->schema([

                                    Select::make('pelanggan_id')
                                        ->label('Pelanggan')
                                        ->relationship('pelanggan', 'nama_pelanggan')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required(),

                                    Placeholder::make('no_hp')
                                        ->label('No Telepon')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->no_telepon ?? '-'
                                        ),

                                    Placeholder::make('alamat')
                                        ->label('Alamat')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->alamat ?? '-'
                                        ),

                                ])
                                ->columns(2),
                        ]),

                    /*
|--------------------------------------------------------------------------
| STEP 2 : DATA MOTOR — pastikan live() ada di repeater & durasi_sewa
|--------------------------------------------------------------------------
*/
Wizard\Step::make('Data Motor')
    ->schema([
         Repeater::make('penyewaanMotor')
            ->relationship()
            ->live()
            ->schema([

                Select::make('motor_id')
                    ->label('Motor')
                    ->options(
                        Motor::where('status', 'tersedia')
                            ->pluck('nama_motor', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $motor  = Motor::find($state);
                        $harga  = $motor?->harga_sewa_perhari ?? 0;
                        $durasi = (int) ($get('jml') ?? 1);

                        $set('harga_sewa_perhari', $harga);
                        $set('subtotal', $harga * $durasi);
                    }),

                TextInput::make('harga_sewa_perhari')  // ✅ sesuai migration
                    ->label('Harga Sewa / Hari')
                    ->prefix('Rp')
                    ->numeric()
                    ->readOnly(),

                TextInput::make('jml')                  // ✅ sesuai migration
                    ->label('Durasi Sewa (Hari)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $harga  = (float) ($get('harga_sewa_perhari') ?? 0);
                        $durasi = (int)   ($state ?? 1);

                        $set('subtotal', $harga * $durasi);
                    }),

                TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->prefix('Rp')
                    ->numeric()
                    ->readOnly(),

                    Forms\Components\Hidden::make('tgl')
            ->default(now()->toDateString()),


            ])
            ->columns(2)
            ->addActionLabel('Tambah Motor')
            ->required(),
    ]),
                    /*
                    |----------------------------------------------------------
                    | STEP 3 : RINGKASAN + TANGGAL SEWA
                    | - Tampilkan data pelanggan & motor yang sudah diisi
                    | - Input tgl_sewa, tgl_kembali
                    |----------------------------------------------------------
                    */
                    Wizard\Step::make('Detail Sewa')
                        ->schema([

                            // ── Ringkasan Data Pelanggan ──────────────────
                            Section::make('Data Pelanggan')
                                ->schema([

                                    Placeholder::make('ringkasan_nama')
                                        ->label('Nama Pelanggan')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->nama_pelanggan ?? '-'
                                        ),

                                    Placeholder::make('ringkasan_hp')
                                        ->label('No Telepon')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->no_telepon ?? '-'
                                        ),

                                    Placeholder::make('ringkasan_alamat')
                                        ->label('Alamat')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->alamat ?? '-'
                                        ),

                                ])
                                ->columns(2),

                            // ── Ringkasan Data Motor ──────────────────────
                            Section::make('Motor yang Disewa')
                                ->schema([

                                    Placeholder::make('ringkasan_motor')
    ->label('Daftar Motor')
    ->content(function (Get $get) {
        $items = $get('penyewaanMotor');
        if (!$items) return '-';

        $lines = [];
        foreach ($items as $item) {
            $motor = Motor::find($item['motor_id'] ?? null);
            if ($motor) {
                $lines[] = sprintf(
                    '%s — %d hari × Rp %s = Rp %s',
                    $motor->nama_motor,
                    $item['jml'] ?? 0,                                          // ✅ jml
                    number_format($item['harga_sewa_perhari'] ?? 0, 0, ',', '.'), // ✅ harga_sewa_perhari
                    number_format($item['subtotal']           ?? 0, 0, ',', '.')
                );
            }
        }
        return implode("\n", $lines) ?: '-';
    }),

                                    Placeholder::make('ringkasan_subtotal')
                                        ->label('Total Sewa')
                                        ->content(function (Get $get) {
                                            $total = collect($get('penyewaanMotor') ?? [])
                                                ->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));

                                            return 'Rp ' . number_format($total, 0, ',', '.');
                                        }),

                                ])
                                ->columns(1),

                            // ── Tanggal Sewa & Kembali ────────────────────
                            Section::make('Tanggal Sewa')
                                ->schema([

                                    TextInput::make('no_faktur')
                                        ->label('No Faktur')
                                        ->default(Penyewaan::getKodeFaktur())
                                        ->readOnly(),

                                    DatePicker::make('tgl_sewa')
                                        ->label('Tanggal Sewa')
                                        ->required(),

                                    DatePicker::make('tgl_kembali')
                                        ->label('Tanggal Kembali (Estimasi)')
                                        ->required(),

                                ])
                                ->columns(3),

                        ]),

                    /*
|--------------------------------------------------------------------------
| STEP 4 : PEMBAYARAN SEWA — fix total otomatis
|--------------------------------------------------------------------------
*/
Wizard\Step::make('Pembayaran Sewa')
    ->schema([
        Section::make('Pembayaran')
            ->schema([

                // ✅ Placeholder untuk tampilan — selalu reaktif
               Placeholder::make('total_harga_display')
    ->label('Total Bayar')
    ->content(function (Get $get) {
        $total = collect($get('penyewaanMotor') ?? [])
            ->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));

        return 'Rp ' . number_format($total, 0, ',', '.');
    }),

                // ✅ Hidden untuk simpan nilai ke database
                Forms\Components\Hidden::make('total_harga')
                    ->dehydrated()
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        $total = collect($get('penyewaanMotor') ?? [])
                            ->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));

                        $set('total_harga', $total);
                    }),

                Select::make('metode')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash'     => 'Cash',
                        'transfer' => 'Transfer',
                        'qris'     => 'QRIS',
                    ])
                    ->required(),

                FileUpload::make('bukti_bayar')
    ->label('Bukti Bayar')
    ->directory('bukti-pembayaran')
    ->nullable(),

            ])
            ->columns(2),
    ]),
                    /*
                    |----------------------------------------------------------
                    | STEP 5 : KONFIRMASI TRANSAKSI
                    |----------------------------------------------------------
                    */
                    Wizard\Step::make('Konfirmasi')
                        ->schema([
                            Section::make('Ringkasan Transaksi Final')
                                ->schema([

                                    Placeholder::make('final_pelanggan')
                                        ->label('Pelanggan')
                                        ->content(fn (Get $get) =>
                                            optional(Pelanggan::find($get('pelanggan_id')))->nama_pelanggan ?? '-'
                                        ),

                                    Placeholder::make('final_faktur')
                                        ->label('No Faktur')
                                        ->content(fn (Get $get) => $get('no_faktur') ?? '-'),

                                    Placeholder::make('final_tgl_sewa')
                                        ->label('Tanggal Sewa')
                                        ->content(fn (Get $get) => $get('tgl_sewa') ?? '-'),

                                    Placeholder::make('final_tgl_kembali')
                                        ->label('Tanggal Kembali')
                                        ->content(fn (Get $get) => $get('tgl_kembali') ?? '-'),

                                    Placeholder::make('final_motor')
                                        ->label('Motor Disewa')
                                        ->content(function (Get $get) {
                                            $items = $get('penyewaanMotor');
                                            if (!$items) return '-';

                                            $lines = [];
                                            foreach ($items as $item) {
                                                $motor = Motor::find($item['motor_id'] ?? null);
                                                if ($motor) {
                                                    $lines[] = $motor->nama_motor
                                                        . ' — Rp '
                                                        . number_format($item['subtotal'] ?? 0, 0, ',', '.');
                                                }
                                            }
                                            return implode("\n", $lines) ?: '-';
                                        }),

                                    Placeholder::make('final_metode')
                                        ->label('Metode Pembayaran')
                                        ->content(fn (Get $get) => strtoupper($get('metode') ?? '-')),

                                    Placeholder::make('final_total')
                                        ->label('Grand Total')
                                        ->content(function (Get $get) {
                                            $total = collect($get('penyewaanMotor') ?? [])
                                                ->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));
                                            return 'Rp ' . number_format($total, 0, ',', '.');
                                        }),

                                ])
                                ->columns(2),
                        ]),

                ])
                ->columnSpanFull(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('no_faktur')
                    ->label('No Faktur')
                    ->searchable(),

                TextColumn::make('pelanggan.nama_pelanggan')
                    ->label('Pelanggan')
                    ->searchable(),

                TextColumn::make('tgl_sewa')
                    ->label('Tgl Sewa')
                    ->date(),

                TextColumn::make('tgl_kembali')
                    ->label('Tgl Kembali')
                    ->date(),

                TextColumn::make('total_harga')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'Rp'.number_format($state, 0, ',', '.')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
    // ✅ Tambahkan ini
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with('penyewaanMotor');
    }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPenyewaans::route('/'),
            'create' => Pages\CreatePenyewaan::route('/create'),
            'edit'   => Pages\EditPenyewaan::route('/{record}/edit'),
        ];
    }
}
