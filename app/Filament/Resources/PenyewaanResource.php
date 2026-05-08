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

                        Section::make('Informasi Penyewa')
                            ->schema([

                                TextInput::make('no_faktur')
                                    ->label('No Faktur')
                                    ->default(
                                        fn () => Penyewaan::getKodeFaktur()
                                    )
                                    ->readonly()
                                    ->required(),

                                Select::make('pelanggan_id')
                                    ->label('Pelanggan')
                                    ->options(
                                        Pelanggan::pluck(
                                            'nama_pelanggan',
                                            'id'
                                        )->toArray()
                                    )
                                    ->searchable()
                                    ->required(),

                                TextInput::make('durasi_sewa')
                                    ->label('Durasi Sewa')
                                    ->numeric()
                                    ->suffix('Hari')
                                    ->required(),

                                DatePicker::make('tgl_sewa')
                                    ->label('Tanggal Sewa')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('tgl_kembali')
                                    ->label('Tanggal Kembali')
                                    ->required(),

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

                        Repeater::make('items')
                            ->relationship('penyewaanMotor')
                            ->schema([

                                Select::make('motor_id')
                                    ->label('Motor')
                                    ->options(
                                        Motor::where(
                                            'status',
                                            'tersedia'
                                        )
                                        ->pluck(
                                            'nama_motor',
                                            'id'
                                        )
                                        ->toArray()
                                    )
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function (
                                        $state,
                                        $set,
                                        $get
                                    ) {

                                        $motor = Motor::find($state);

                                        $harga = $motor
                                            ? $motor->harga_sewa_perhari
                                            : 0;

                                        $jml = $get('jml') ?? 1;

                                        $set(
                                            'harga_sewa',
                                            $harga
                                        );

                                        $set(
                                            'subtotal',
                                            $harga * $jml
                                        );
                                    }),

                                TextInput::make('harga_sewa')
                                    ->label('Harga Sewa / Hari')
                                    ->numeric()
                                    ->readonly()
                                    ->dehydrated(),

                                TextInput::make('jml')
                                    ->label('Jumlah')
                                    ->default(1)
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (
                                        $state,
                                        $get,
                                        $set
                                    ) {

                                        $harga =
                                            $get('harga_sewa') ?? 0;

                                        $set(
                                            'subtotal',
                                            $harga * $state
                                        );
                                    }),

                                DatePicker::make('tgl')
                                    ->label('Tanggal')
                                    ->default(today())
                                    ->required(),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->readonly()
                                    ->dehydrated()
                                    ->default(0),

                            ])
                            ->columns(2)
                            ->addable()
                            ->deletable()
                            ->reorderable()
                            ->createItemButtonLabel('Tambah Motor')
                            ->minItems(1)
                            ->required(),

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 3 : DETAIL SEWA
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Detail Sewa')
                    ->schema([

                        Section::make('Data Pelanggan')
                            ->schema([

                                Placeholder::make('detail_pelanggan')
                                    ->label('Nama Pelanggan')
                                    ->content(function (Get $get) {

                                        $pelanggan =
                                            Pelanggan::find(
                                                $get('pelanggan_id')
                                            );

                                        return $pelanggan
                                            ? $pelanggan->nama_pelanggan
                                            : '-';
                                    }),

                                Placeholder::make('detail_durasi')
                                    ->label('Durasi')
                                    ->content(fn (Get $get) =>

                                        ($get('durasi_sewa') ?? 0)
                                        . ' Hari'

                                    ),

                                Placeholder::make('detail_tanggal')
                                    ->label('Tanggal')
                                    ->content(fn (Get $get) =>

                                        ($get('tgl_sewa') ?? '-')
                                        . ' s/d ' .
                                        ($get('tgl_kembali') ?? '-')

                                    ),

                            ])
                            ->columns(3),

                        Section::make('Motor Yang Disewa')
                            ->schema([

                                Placeholder::make('detail_motor')
                                    ->content(function (Get $get) {

                                        $items = $get('items');

                                        if (!$items) {
                                            return 'Belum ada motor dipilih';
                                        }

                                        $output = '';

                                        foreach ($items as $item) {

                                            $motor = Motor::find(
                                                $item['motor_id']
                                            );

                                            if ($motor) {

                                                $output .=
                                                    "Motor : "
                                                    . $motor->nama_motor .

                                                    " | Harga : Rp "
                                                    . number_format(
                                                        $item['harga_sewa'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    )

                                                    . " | Qty : "
                                                    . $item['jml']

                                                    . " | Subtotal : Rp "
                                                    . number_format(
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

                            ]),

                        Section::make('Grand Total')
                            ->schema([

                                Placeholder::make('grand_total')
                                    ->label('Total Penyewaan')
                                    ->content(function (Get $get) {

                                        $items = $get('items');

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

                    ]),

                /*
                |--------------------------------------------------------------------------
                | STEP 4 : PEMBAYARAN
                |--------------------------------------------------------------------------
                */
                Wizard\Step::make('Pembayaran')
                    ->schema([

                        Section::make('Data Pembayaran')
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
    ->dehydrated()
    ->formatStateUsing(function (Get $get) {

        $items = $get('items');

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
                                    ->directory('bukti-pembayaran')
                                    ->image()
                                    ->nullable(),

                                TextInput::make('order_id')
                                    ->default(fn (Get $get) =>
                                        $get('no_faktur')
                                    )
                                    ->readonly(),

                                TextInput::make('payment_type')
                                    ->default('manual'),

                                TextInput::make('status_code')
                                    ->default('200'),

                                TextInput::make('transaction_id')
                                    ->default(fn () => uniqid()),

                                DateTimePicker::make('transaction_time')
                                    ->default(now()),

                                DateTimePicker::make('settlement_time')
                                    ->default(now()),

                                TextInput::make('status_message')
                                    ->default('Pembayaran Berhasil'),

                                TextInput::make('merchant_id')
                                    ->default('RENTAL-MOTOR'),

                            ])
                            ->columns(2)

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