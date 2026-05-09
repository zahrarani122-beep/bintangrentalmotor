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
                    | STEP 1 : DATA PENYEWA
                    |--------------------------------------------------------------------------
                    */
                    Wizard\Step::make('Data Penyewa')
                        ->schema([

                            Section::make('Informasi Penyewaan')
                                ->icon('heroicon-m-document-text')
                                ->schema([

                                    TextInput::make('no_faktur')
                                        ->default(fn () => Penyewaan::getKodeFaktur())
                                        ->label('No Faktur')
                                        ->required()
                                        ->readonly(),

                                    Select::make('pelanggan_id')
                                        ->label('Pelanggan')
                                        ->options(
                                            Pelanggan::pluck(
                                                'nama_pelanggan',
                                                'id'
                                            )->toArray()
                                        )
                                        ->searchable()
                                        ->required()
                                        ->placeholder('Pilih Pelanggan'),

                                    TextInput::make('durasi_sewa')
                                        ->label('Durasi Sewa')
                                        ->numeric()
                                        ->required()
                                        ->suffix('Hari'),

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
                    | STEP 2 : PILIH MOTOR
                    |--------------------------------------------------------------------------
                    */
                    Wizard\Step::make('Pilih Motor')
                        ->schema([

                            Repeater::make('items')
                                ->relationship('penyewaanMotor')
                                ->schema([

                                    Select::make('motor_id')
                                        ->label('Motor')
                                        ->options(
                                            Motor::where('status', 'tersedia')
                                                ->pluck('nama_motor', 'id')
                                                ->toArray()
                                        )
                                        ->searchable()
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $set, $get) {

    $motor = Motor::find($state);

    $harga = $motor
        ? $motor->harga_sewa_perhari
        : 0;

    $jml = $get('jml') ?? 1;

    $set('harga_sewa_perhari', $harga);

    $set('subtotal', $harga * $jml);
}),

                                    TextInput::make('harga_sewa_perhari')
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
                                    ->afterStateUpdated(function ($state, $get, $set) {

                                    $harga = $get('harga_sewa_perhari') ?? 0;

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

                            Placeholder::make('detail')
                                ->content(
                                    'Pastikan data pelanggan, motor, dan durasi penyewaan sudah benar sebelum disimpan.'
                                ),

                        ]),

                ])
                ->columnSpan(3)

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('no_faktur')
                    ->label('No Faktur')
                    ->searchable(),

                TextColumn::make('pelanggan.nama_pelanggan')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('durasi_sewa')
                    ->label('Durasi')
                    ->suffix(' Hari'),

                TextColumn::make('tgl_sewa')
                    ->label('Tanggal Sewa')
                    ->date(),

                TextColumn::make('tgl_kembali')
                    ->label('Tanggal Kembali')
                    ->date(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime(),

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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPenyewaans::route('/'),
            'create' => Pages\CreatePenyewaan::route('/create'),
            'edit' => Pages\EditPenyewaan::route('/{record}/edit'),
        ];
    }
}