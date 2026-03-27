<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PegawaiResource\Pages;
use App\Models\Pegawai;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

// tambahan
use Illuminate\Support\Facades\Hash;

class PegawaiResource extends Resource
{
    protected static ?string $model = Pegawai::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // ================= FORM =================
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                TextInput::make('nama_pegawai')
                    ->required()
                    ->placeholder('Masukkan nama pegawai'),

                TextInput::make('username_pegawai')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('Masukkan username')
                    ->autocomplete(false),

                TextInput::make('password_pegawai')
                    ->password()
                    ->required()
                    ->placeholder('Masukkan password')
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                TextInput::make('jabatan')
                    ->required()
                    ->placeholder('Masukkan jabatan'),

                TextInput::make('no_telepon')
                    ->required()
                    ->placeholder('Masukkan nomor telepon'),

                TextInput::make('alamat')
                    ->required()
                    ->placeholder('Masukkan alamat'),

                DatePicker::make('tanggal_lahir')
                    ->required(),

                FileUpload::make('foto_ktp')
                    ->directory('foto_ktp')
                    ->image()
                    ->required(),

            ]);
    }

    // ================= TABLE =================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('nama_pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username_pegawai')
                    ->searchable(),

                TextColumn::make('jabatan')
                    ->sortable(),

                TextColumn::make('no_telepon'),

                TextColumn::make('tanggal_lahir')
                    ->date(),

                ImageColumn::make('foto_ktp')
                    ->disk('public'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    // ================= PAGES =================
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPegawais::route('/'),
            'create' => Pages\CreatePegawai::route('/create'),
            'edit' => Pages\EditPegawai::route('/{record}/edit'),
        ];
    }
}