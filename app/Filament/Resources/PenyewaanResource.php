<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenyewaanResource\Pages;
use App\Models\Penyewaan;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// Filament Components
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

use Filament\Forms\Get;
use Filament\Forms\Set;

// Models
use App\Models\Pelanggan;
use App\Models\Motor;
use App\Models\PenyewaanMotor;

// DB
use Illuminate\Support\Facades\DB;

// tambahan untuk tombol action
use Filament\Tables\Actions\Action;


class PenyewaanResource extends Resource
{
    protected static ?string $model = Penyewaan::class;

    protected static ?string $navigationIcon  = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Penyewaan';

    protected static ?string $navigationGroup = 'Transaksi';
   public static function form(Form $form): Form
{
    return $form
        ->schema([

            Wizard::make([

                /*
                |--------------------------------------------------------------------------
                | STEP 1 : DATA PELANGGAN
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Data Pelanggan')
                    ->schema([

                        Section::make('Pilih Pelanggan')
                            ->schema([

                                Select::make('pelanggan_id')
                                    ->label('Pelanggan')
                                    ->relationship(
                                        'pelanggan',
                                        'nama_pelanggan'
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),

                                Placeholder::make('no_hp')
                                    ->label('No Telepon')
                                    ->content(function (Get $get) {

                                        $pelanggan = Pelanggan::find(
                                            $get('pelanggan_id')
                                        );

                                        return $pelanggan
                                            ? $pelanggan->no_telepon
                                            : '-';
                                    }),

                                Placeholder::make('alamat')
                                    ->label('Alamat')
                                    ->content(function (Get $get) {

                                        $pelanggan = Pelanggan::find(
                                            $get('pelanggan_id')
                                        );

                                        return $pelanggan
                                            ? $pelanggan->alamat
                                            : '-';
                                    }),

                            ])
                            ->columns(2)

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 2 : DATA MOTOR
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Data Motor')
                    ->schema([
                        

                        Repeater::make('penyewaanMotor')
                            ->relationship()
                            ->schema([

                                Select::make('motor_id')
                                    ->label('Motor')
                                    ->options(
                                        Motor::where(
                                            'status',
                                            'tersedia'
                                        )->pluck(
                                            'nama_motor',
                                            'id'
                                        )
                                    )
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (
                                        $state,
                                        Set $set,
                                        Get $get
                                    ) {

                                        $motor = Motor::find($state);

                                        $harga = $motor
                                            ? $motor->harga_sewa_perhari
                                            : 0;

                                        $jml =
                                            $get('jml') ?? 1;

                                        $set(
                                            'harga_sewa',
                                            $harga
                                        );

                                        $durasi = $get('../../durasi_sewa') ?? 1;

                                        $set(
                                        'subtotal',
                                        $harga * $jml * $durasi
                                        );
                                    }),

                                TextInput::make('harga_sewa')
                                    ->label('Harga Sewa / Hari')
                                    ->numeric()
                                    ->readOnly(),
                                TextInput::make('durasi_sewa')
                                    ->label('Durasi Sewa')
                                    ->numeric()
                                    ->required(),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->readOnly(),

                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Motor')
                            ->required()

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 3 : DETAIL SEWA
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Detail Sewa')
                    ->schema([

                        Section::make('Detail Penyewaan')
                            ->schema([

                                TextInput::make('no_faktur')
                                    ->default(
                                        Penyewaan::getKodeFaktur()
                                    )
                                    ->readOnly(),


                                DatePicker::make('tgl_sewa')
                                    ->label('Tanggal Sewa')
                                    ->required(),

                                DatePicker::make('tgl_kembali')
                                    ->label('Tanggal Kembali')
                                    ->required(),

                            ])
                            ->columns(2)

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 4 : PEMBAYARAN SEWA
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Pembayaran Sewa')
                    ->schema([

                        Section::make('Pembayaran')
                            ->schema([

                                DatePicker::make('tgl_bayar')
                                    ->label('Tanggal Bayar')
                                    ->default(now())
                                    ->required(),

                                Select::make('metode')
                                    ->label('Metode Pembayaran')
                                    ->options([
                                        'cash' => 'Cash',
                                        'transfer' => 'Transfer',
                                        'qris' => 'QRIS',
                                    ])
                                    ->required(),

                                TextInput::make('total_harga')
                                    ->label('Total Bayar')
                                    ->numeric()
                                    ->readOnly()
                                    ->live()
                                    ->dehydrated()
                                    ->formatStateUsing(function (Get $get) {

                                    $items = $get('penyewaanMotor');

                                    $total = 0;

                                    if ($items) {

                                    foreach ($items as $item) {

                                    $total += $item['subtotal'] ?? 0;
                                        }
                                    }

                                        return $total;
                                    }),

                                FileUpload::make('bukti_bayar')
                                    ->label('Bukti Bayar')
                                    ->directory(
                                        'bukti-pembayaran'
                                    )
                                    ->image()
                                    ->nullable(),

                            ])
                            ->columns(2)

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 5 : DETAIL TRANSAKSI
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Detail Transaksi')
                    ->schema([

                        Section::make('Ringkasan Transaksi')
                            ->schema([

                                Placeholder::make('detail_pelanggan')
                                    ->label('Pelanggan')
                                    ->content(function (Get $get) {

                                        $pelanggan =
                                            Pelanggan::find(
                                                $get('pelanggan_id')
                                            );

                                        return $pelanggan
                                            ? $pelanggan->nama_pelanggan
                                            : '-';
                                    }),

                                Placeholder::make('detail_motor')
                                    ->label('Motor Disewa')
                                    ->content(function (Get $get) {

                                        $items =
                                            $get('penyewaanMotor');

                                        if (!$items) {
                                            return '-';
                                        }

                                        $output = '';

                                        foreach ($items as $item) {

                                            $motor =
                                                Motor::find(
                                                    $item['motor_id']
                                                );

                                            if ($motor) {

                                                $output .=
                                                    $motor->nama_motor .

                                                    ' - Rp ' .

                                                    number_format(
                                                        $item['subtotal'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    )

                                                    . "\n";
                                            }
                                        }

                                        return $output;
                                    }),

                                Placeholder::make('detail_total')
                                    ->label('Grand Total')
                                    ->content(function (Get $get) {

                                        $items =
                                            $get('penyewaanMotor');

                                        $total = 0;

                                        if ($items) {

                                            foreach ($items as $item) {

                                                $total +=
                                                    $item['subtotal']
                                                    ?? 0;
                                            }
                                        }

                                        return 'Rp ' .
                                            number_format(
                                                $total,
                                                0,
                                                ',',
                                                '.'
                                            );
                                    }),

                            ])
                            ->columns(1)

                    ]),

            ])
            ->columnSpanFull()

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

            TextColumn::make('durasi_sewa')
                ->label('Durasi')
                ->suffix(' Hari'),

            TextColumn::make('tgl_sewa')
                ->date(),

            TextColumn::make('tgl_kembali')
                ->date(),

        ])
        ->actions([

            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),

        ])
        ->bulkActions([

            Tables\Actions\BulkActionGroup::make([

                Tables\Actions\DeleteBulkAction::make(),

            ])

        ]);
}

/*
|--------------------------------------------------------------------------
| RELATIONS
|--------------------------------------------------------------------------
*/
public static function getRelations(): array
{
    return [
        //
    ];
}

/*
|--------------------------------------------------------------------------
| PAGES
|--------------------------------------------------------------------------
*/
public static function getPages(): array
{
    return [
        'index' => Pages\ListPenyewaans::route('/'),
        'create' => Pages\CreatePenyewaan::route('/create'),
        'edit' => Pages\EditPenyewaan::route('/{record}/edit'),
    ];
}
}