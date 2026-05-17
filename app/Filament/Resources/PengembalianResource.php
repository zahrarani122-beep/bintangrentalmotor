<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengembalianResource\Pages;
use App\Models\Pengembalian;
use App\Models\Penyewaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PengembalianResource extends Resource
{
    protected static ?string $model = Pengembalian::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Pengembalian';

    protected static ?string $pluralModelLabel = 'Pengembalian';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Wizard::make([

                    //data penyewaan
                    Forms\Components\Wizard\Step::make('Data Penyewaan')
                        ->schema([

                            Forms\Components\Section::make('Informasi Penyewaan')
                                ->icon('heroicon-m-document-text')
                                ->schema([

                                    Forms\Components\Select::make('id_sewa')
                                        ->label('ID Sewa')
                                        ->options(function (?Pengembalian $record) {
                                            $idSewaSudahDikembalikan = Pengembalian::query()
                                                ->when($record, function ($query) use ($record) {
                                                    $query->where('id_pengembalian', '!=', $record->id_pengembalian);
                                                })
                                                ->pluck('id_sewa')
                                                ->toArray();

                                            return Penyewaan::query()
                                                ->with('pelanggan')
                                                ->whereNotIn('id_sewa', $idSewaSudahDikembalikan)
                                                ->get()
                                                ->mapWithKeys(function ($penyewaan) {
                                                    return [
                                                        $penyewaan->id_sewa =>
                                                            'ID Sewa: ' . $penyewaan->id_sewa .
                                                            ' | No Faktur: ' . ($penyewaan->no_faktur ?? '-') .
                                                            ' | Pelanggan: ' . ($penyewaan->pelanggan->nama_pelanggan ?? '-'),
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->native(false)
                                        ->placeholder('Pilih ID Sewa')
                                        ->live(),

                                    Forms\Components\DatePicker::make('tgl_pengembalian')
                                        ->label('Tanggal Pengembalian')
                                        ->default(now())
                                        ->required(),

                                    Forms\Components\Placeholder::make('no_faktur')
                                        ->label('No Faktur')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->no_faktur ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('pelanggan')
                                        ->label('Pelanggan')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->pelanggan->nama_pelanggan ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('tgl_sewa')
                                        ->label('Tanggal Sewa')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->tgl_sewa ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('tgl_kembali')
                                        ->label('Tanggal Kembali')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->tgl_kembali ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('durasi_sewa')
                                        ->label('Durasi Sewa')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            if (!$penyewaan) {
                                                return '-';
                                            }

                                            return $penyewaan->durasi_sewa . ' Hari';
                                        }),

                                ])
                                ->columns(2),

                        ]),

                    /*
                    |--------------------------------------------------------------------------
                    | STEP 2: INPUT DENDA
                    |--------------------------------------------------------------------------
                    */
                    Forms\Components\Wizard\Step::make('Input Denda')
                        ->schema([

                            Forms\Components\Section::make('Data Denda')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->schema([

                                    Forms\Components\Select::make('denda')
                                        ->label('Apakah Ada Denda?')
                                        ->options([
                                            'Tidak Ada Denda' => 'Tidak Ada Denda',
                                            'Ada Denda' => 'Ada Denda',
                                        ])
                                        ->default('Tidak Ada Denda')
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state === 'Tidak Ada Denda') {
                                                $set('detail_denda', []);
                                                $set('total', 0);
                                                $set('keterangan', null);
                                            }
                                        }),

                                    Forms\Components\Repeater::make('detail_denda')
                                        ->label('Daftar Denda')
                                        ->schema([

                                            Forms\Components\Select::make('jenis_denda')
                                                ->label('Jenis Denda')
                                                ->options([
                                                    'Kehilangan' => 'Kehilangan',
                                                    'Kerusakan' => 'Kerusakan',
                                                ])
                                                ->required()
                                                ->native(false),

                                            Forms\Components\Select::make('nama_denda')
                                                ->label('Nama Denda')
                                                ->options([
                                                    'Motor' => 'Motor',
                                                    'Kunci' => 'Kunci',
                                                    'STNK' => 'STNK',
                                                    'Helm' => 'Helm',
                                                ])
                                                ->required()
                                                ->native(false),

                                            Forms\Components\TextInput::make('nominal')
                                                ->label('Nominal')
                                                ->prefix('Rp')
                                                ->default(0)
                                                ->required()
                                                ->inputMode('numeric')
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    $daftarDenda = $get('../../detail_denda') ?? [];

                                                    $total = self::hitungTotalDenda($daftarDenda);

                                                    $set('../../total', $total);
                                                })
                                                ->dehydrateStateUsing(function ($state) {
                                                    return self::ambilAngka($state);
                                                }),

                                        ])
                                        ->columns(3)
                                        ->addActionLabel('Tambah Denda')
                                        ->addable()
                                        ->deletable()
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->minItems(1)
                                        ->visible(fn (Get $get) => $get('denda') === 'Ada Denda')
                                        ->required(fn (Get $get) => $get('denda') === 'Ada Denda')
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            $daftarDenda = $get('detail_denda') ?? [];

                                            $total = self::hitungTotalDenda($daftarDenda);

                                            $set('total', $total);
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('total')
                                        ->label('Total Denda')
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->readOnly()
                                        ->dehydrated()
                                        ->visible(fn (Get $get) => $get('denda') === 'Ada Denda'),

                                    Forms\Components\Textarea::make('keterangan')
                                        ->label('Keterangan')
                                        ->placeholder('Contoh: Helm hilang, STNK hilang, body motor rusak')
                                        ->nullable()
                                        ->columnSpanFull(),

                                ])
                                ->columns(2),

                        ]),

                    /*
                    |--------------------------------------------------------------------------
                    | STEP 3: KONFIRMASI
                    |--------------------------------------------------------------------------
                    */
                    Forms\Components\Wizard\Step::make('Konfirmasi')
                        ->schema([

                            Forms\Components\Section::make('Konfirmasi Data Penyewaan')
                                ->icon('heroicon-m-check-circle')
                                ->schema([

                                    Forms\Components\Placeholder::make('konfirmasi_id_sewa')
                                        ->label('ID Sewa')
                                        ->content(fn (Get $get) => $get('id_sewa') ?? '-'),

                                    Forms\Components\Placeholder::make('konfirmasi_no_faktur')
                                        ->label('No Faktur')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->no_faktur ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('konfirmasi_pelanggan')
                                        ->label('Pelanggan')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->pelanggan->nama_pelanggan ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('konfirmasi_tgl_sewa')
                                        ->label('Tanggal Sewa')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->tgl_sewa ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('konfirmasi_tgl_kembali')
                                        ->label('Tanggal Kembali')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            return $penyewaan->tgl_kembali ?? '-';
                                        }),

                                    Forms\Components\Placeholder::make('konfirmasi_durasi')
                                        ->label('Durasi Sewa')
                                        ->content(function (Get $get) {
                                            $penyewaan = self::ambilPenyewaan($get('id_sewa'));

                                            if (!$penyewaan) {
                                                return '-';
                                            }

                                            return $penyewaan->durasi_sewa . ' Hari';
                                        }),

                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Konfirmasi Data Pengembalian')
                                ->schema([

                                    Forms\Components\Placeholder::make('konfirmasi_tgl_pengembalian')
                                        ->label('Tanggal Pengembalian')
                                        ->content(fn (Get $get) => $get('tgl_pengembalian') ?? '-'),

                                    Forms\Components\Placeholder::make('konfirmasi_denda')
                                        ->label('Status Denda')
                                        ->content(fn (Get $get) => $get('denda') ?? 'Tidak Ada Denda'),

                                    Forms\Components\Placeholder::make('konfirmasi_detail_denda')
                                        ->label('Detail Denda')
                                        ->content(function (Get $get) {
                                            $statusDenda = $get('denda');
                                            $detailDenda = $get('detail_denda') ?? [];

                                            if ($statusDenda !== 'Ada Denda' || count($detailDenda) === 0) {
                                                return 'Tidak ada denda';
                                            }

                                            return collect($detailDenda)
                                                ->values()
                                                ->map(function ($item, $index) {
                                                    $nominal = self::formatRupiah($item['nominal'] ?? 0);

                                                    return 'Denda ' . ($index + 1) . ': ' .
                                                        ($item['jenis_denda'] ?? '-') . ' - ' .
                                                        ($item['nama_denda'] ?? '-') . ' - ' .
                                                        $nominal;
                                                })
                                                ->implode(' | ');
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\Placeholder::make('konfirmasi_total')
                                        ->label('Total Denda')
                                        ->content(function (Get $get) {
                                            return self::formatRupiah($get('total') ?? 0);
                                        }),

                                    Forms\Components\Placeholder::make('konfirmasi_keterangan')
                                        ->label('Keterangan')
                                        ->content(fn (Get $get) => $get('keterangan') ?? '-')
                                        ->columnSpanFull(),

                                    Forms\Components\Checkbox::make('konfirmasi_data')
                                        ->label('Saya sudah memeriksa data pengembalian')
                                        ->accepted()
                                        ->dehydrated(false)
                                        ->columnSpanFull(),

                                ])
                                ->columns(2),

                        ]),

                ])
                    ->columnSpanFull()
                    ->skippable(false),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id_pengembalian')
                    ->label('ID Pengembalian')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('id_sewa')
                    ->label('ID Sewa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('penyewaan.no_faktur')
                    ->label('No Faktur')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('penyewaan.pelanggan.nama_pelanggan')
                    ->label('Pelanggan')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('tgl_pengembalian')
                    ->label('Tanggal Pengembalian')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('denda')
                    ->label('Denda')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Ada Denda' => 'danger',
                        'Tidak Ada Denda' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Denda')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->defaultSort('id_pengembalian', 'desc')
            ->filters([

                Tables\Filters\SelectFilter::make('denda')
                    ->label('Filter Denda')
                    ->options([
                        'Tidak Ada Denda' => 'Tidak Ada Denda',
                        'Ada Denda' => 'Ada Denda',
                    ]),

            ])
            ->actions([

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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengembalians::route('/'),
            'create' => Pages\CreatePengembalian::route('/create'),
            'edit' => Pages\EditPengembalian::route('/{record}/edit'),
        ];
    }

    protected static function ambilPenyewaan($idSewa): ?Penyewaan
    {
        if (!$idSewa) {
            return null;
        }

        return Penyewaan::with('pelanggan')
            ->where('id_sewa', $idSewa)
            ->first();
    }

    protected static function ambilAngka($nilai): int
    {
        return (int) preg_replace('/[^0-9]/', '', (string) $nilai);
    }

    protected static function hitungTotalDenda($daftarDenda): int
    {
        $total = 0;

        foreach ($daftarDenda as $item) {
            $total += self::ambilAngka($item['nominal'] ?? 0);
        }

        return $total;
    }

    protected static function formatRupiah($nominal): string
    {
        return 'Rp' . number_format(self::ambilAngka($nominal), 0, ',', '.');
    }
}
