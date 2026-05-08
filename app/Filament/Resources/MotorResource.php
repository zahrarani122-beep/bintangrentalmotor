<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MotorResource\Pages;
use App\Models\Motor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// tambahan seperti di BarangResource
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class MotorResource extends Resource
{
    protected static ?string $model = Motor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Motor';
    protected static ?string $pluralModelLabel = 'Data Motor';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
                TextInput::make('nama_motor')
                    ->label('Nama Motor')
                    ->required(),

                TextInput::make('jenis_motor')
                    ->label('Jenis Motor')
                    ->required(),

                TextInput::make('merek_motor')
                    ->label('Merek Motor')
                    ->required(),

                TextInput::make('plat_nomor')
                    ->label('Plat Nomor')
                    ->required(),

                FileUpload::make('foto_motor')
                    ->label('Foto Motor')
                    ->image()
                    ->directory('foto_motor'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'disewa' => 'Disewa',
                    ])
                    ->default('tersedia')
                    ->required(),

                TextInput::make('harga_sewa_perhari')
                    ->label('harga sewa')
                    ->numeric()
                    ->required(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([

            TextColumn::make('nama_motor')
                ->label('Nama Motor')
                ->searchable(),

            TextColumn::make('jenis_motor')
                ->label('Jenis'),

            TextColumn::make('merek_motor')
                ->label('Merek'),

            TextColumn::make('plat_nomor')
                ->label('Plat Nomor'),

            ImageColumn::make('foto_motor')
                ->label('Foto Motor'),

            TextColumn::make('status')
                ->badge()
                ->colors([
                    'success' => 'tersedia',
                    'danger' => 'disewa',
                ]),

            TextColumn::make('sewa_perhari')
                ->label('Harga')
                ->formatStateUsing(fn (string|int|null $state): string => rupiah($state))
                ->extraAttributes(['class' => 'text-right']) 
                ->sortable()

        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotors::route('/'),
            'create' => Pages\CreateMotor::route('/create'),
            'edit' => Pages\EditMotor::route('/{record}/edit'),
        ];
    }
}