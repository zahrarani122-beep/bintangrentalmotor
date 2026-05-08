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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Pengembalian')
                    ->schema([
                        Forms\Components\TextInput::make('id_pengembalian')
                            ->label('ID Pengembalian')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\Select::make('id_sewa')
                            ->label('Transaksi Penyewaan')
                            ->options(function (?Pengembalian $record) {
                                $idSewaSudahDikembalikan = Pengembalian::query()
                                    ->when($record, function ($query) use ($record) {
                                        $query->where('id_pengembalian', '!=', $record->id_pengembalian);
                                    })
                                    ->pluck('id_sewa')
                                    ->toArray();

                                return Penyewaan::query()
                                    ->whereNotIn('id_sewa', $idSewaSudahDikembalikan)
                                    ->pluck('id_sewa', 'id_sewa');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('tgl_pengembalian')
                            ->label('Tanggal Pengembalian')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('denda')
                            ->label('Denda')
                            ->options([
                                'Tidak Ada Denda' => 'Tidak Ada Denda',
                                'Ada Denda' => 'Ada Denda',
                            ])
                            ->default('Tidak Ada Denda')
                            ->live()
                            ->required()
                            ->native(false)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state === 'Tidak Ada Denda') {
                                    $set('detail_denda', []);
                                    $set('total', 0);
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detail Denda')
                    ->schema([
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
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $items = $get('../../detail_denda') ?? [];

                                        $total = collect($items)
                                            ->sum(fn ($item) => (float) ($item['nominal'] ?? 0));

                                        $set('../../total', $total);
                                    }),
                            ])
                            ->columns(3)
                            ->addActionLabel('Tambah Denda')
                            ->reorderable(false)
                            ->collapsible()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $items = $get('detail_denda') ?? [];

                                $total = collect($items)
                                    ->sum(fn ($item) => (float) ($item['nominal'] ?? 0));

                                $set('total', $total);
                            })
                            ->visible(fn (Get $get) => $get('denda') === 'Ada Denda')
                            ->required(fn (Get $get) => $get('denda') === 'Ada Denda'),

                        Forms\Components\TextInput::make('total')
                            ->label('Total Denda')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (Get $get) => $get('denda') === 'Ada Denda'),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('Contoh: Helm hilang, spion motor rusak, STNK hilang, dan sebagainya')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn (Get $get) => $get('denda') === 'Ada Denda'),
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
}