<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PencatatanBiayaResource\Pages;
use App\Models\Akun;
use App\Models\PencatatanBiaya;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PencatatanBiayaResource extends Resource
{
    protected static ?string $model = PencatatanBiaya::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pencatatan Biaya';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pencatatan Biaya';

    protected static ?string $pluralModelLabel = 'Pencatatan Biaya';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('kode_pencatatan')
                    ->label('Kode Transaksi')
                    ->default(fn (): string => PencatatanBiaya::generateKode(now()->toDateString()))
                    ->afterStateHydrated(function (?string $state, Set $set, ?PencatatanBiaya $record): void {
                        if (! $state) {
                            $set('kode_pencatatan', PencatatanBiaya::generateKode($record?->tanggal_catat));
                        }
                    })
                    ->readOnly()
                    ->dehydrated()
                    ->required(),

                Select::make('akun_id')
                    ->label('Akun Beban')
                    ->relationship(
                        name: 'akun',
                        titleAttribute: 'nama_akun',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('header_akun', 5)
                            ->orderBy('kode_akun')
                    )
                    ->getOptionLabelFromRecordUsing(fn (Akun $record): string => "{$record->kode_akun} - {$record->nama_akun}")
                    ->searchable(['kode_akun', 'nama_akun'])
                    ->preload()
                    ->required(),

                TextInput::make('nominal')
                    ->label('Nominal')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                DatePicker::make('tanggal_catat')
                    ->label('Tanggal Catat')
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                        if ($operation === 'create') {
                            $set('kode_pencatatan', PencatatanBiaya::generateKode($state));
                        }
                    })
                    ->required(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_catat', 'desc')
            ->columns([
                TextColumn::make('kode_pencatatan')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tanggal_catat')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('akun.nama_akun')
                    ->label('Akun Beban')
                    ->description(fn (PencatatanBiaya $record): ?string => $record->akun?->kode_akun)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(50),
            ])
            ->filters([
                Filter::make('tanggal_catat')
                    ->form([
                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai'),
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal Selesai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_mulai'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_catat', '>=', $date)
                            )
                            ->when(
                                $data['tanggal_selesai'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_catat', '<=', $date)
                            );
                    }),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPencatatanBiayas::route('/'),
            'create' => Pages\CreatePencatatanBiaya::route('/create'),
            'edit' => Pages\EditPencatatanBiaya::route('/{record}/edit'),
        ];
    }
}
