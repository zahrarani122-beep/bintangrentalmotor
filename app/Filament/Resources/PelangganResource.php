<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelangganResource\Pages;
use App\Filament\Resources\PelangganResource\RelationManagers;
use App\Models\Pelanggan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

//tambahan
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class PelangganResource extends Resource
{
    protected static ?string $model = Pelanggan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('kode_pelanggan')
                    ->default(fn () => \App\Models\Pelanggan::generateKodePelanggan())
                    ->disabled()
                    ->dehydrated() // tetap tersimpan
                    ->required(),

                // Nama
                TextInput::make('nama_pelanggan')
                    ->required()
                    ->placeholder('Masukkan nama'),

                // No Telepon
                TextInput::make('no_telepon')
                    ->tel()
                    ->required()
                    ->placeholder('Masukkan No Telepon'),

                // Foto KTP/SIM
                FileUpload::make('foto_KTP_SIM')
                    ->directory('foto_KTP_SIM')
                    ->image()
                    ->required(),

                // Alamat
                Textarea::make('alamat')
                    ->rows(3)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_pelanggan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_pelanggan')
                    ->searchable(),

                TextColumn::make('no_telepon'),

                ImageColumn::make('foto_identitas')
                    ->disk('public'),

                TextColumn::make('alamat')
                    ->limit(30),

                TextColumn::make('created_at')
                    ->dateTime('d M Y'),
            ])
            ->filters([
                //
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelanggans::route('/'),
            'create' => Pages\CreatePelanggan::route('/create'),
            'edit' => Pages\EditPelanggan::route('/{record}/edit'),
        ];
    }
}
