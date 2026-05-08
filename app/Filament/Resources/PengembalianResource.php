<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengembalianResource\Pages;
use App\Models\Pengembalian;
use App\Models\Penyewaan;
use Filament\Forms;
use Filament\Forms\Form;
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
                        Forms\Components\Select::make('id_sewa')
                            ->label('Transaksi Penyewaan')
                            ->options(function (?Pengembalian $record) {
                                // Ambil id_sewa yang sudah punya data pengembalian
                                // supaya satu transaksi tidak dikembalikan dua kali
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
                                'Keterlambatan Pengembalian' => 'Keterlambatan Pengembalian',
                                'Kehilangan' => 'Kehilangan',
                                'Kerusakan' => 'Kerusakan',
                            ])
                            ->placeholder('Tidak ada denda')
                            ->nullable()
                            ->native(false),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('Contoh: Motor terlambat dikembalikan 2 hari / spion rusak / helm hilang')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
                        'Keterlambatan Pengembalian' => 'warning',
                        'Kehilangan' => 'danger',
                        'Kerusakan' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('Tidak ada'),

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
                        'Keterlambatan Pengembalian' => 'Keterlambatan Pengembalian',
                        'Kehilangan' => 'Kehilangan',
                        'Kerusakan' => 'Kerusakan',
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