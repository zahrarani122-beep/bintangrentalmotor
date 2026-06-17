<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JurnalResource\Pages;
use App\Filament\Resources\JurnalResource\RelationManagers;
use App\Models\Jurnal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Akun;
use App\Models\JurnalDetail;
use App\Models\Penyewaan;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Section;

class JurnalResource extends Resource
{
    protected static ?string $model = Jurnal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Jurnal Umum';

    protected static ?string $navigationGroup = 'Laporan';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Jurnal')
                    ->description('Pilih faktur terlebih dahulu, tanggal akan terisi otomatis.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        // ✅ Faktur dulu di atas
                        Select::make('no_referensi')
                            ->label('Nomor Faktur')
                            ->placeholder('— Pilih faktur lunas —')
                            ->options(
                                Penyewaan::where('status_bayar', 'lunas')
                                    ->pluck('no_faktur', 'no_faktur')
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $penyewaan = Penyewaan::with(['pembayaran', 'penyewaanMotor.motor'])
                                    ->where('no_faktur', $state)
                                    ->first();

                                if ($penyewaan) {
                                    // ✅ Tanggal otomatis dari data penyewaan
                                    $set('tgl', $penyewaan->tgl_sewa ?? $penyewaan->created_at?->toDateString());

                                    $metode = match ($penyewaan->pembayaran?->metode) {
                                        'tunai'    => 'Tunai',
                                        'midtrans' => 'Midtrans',
                                        default    => '-',
                                    };

                                    $status = $penyewaan->status_bayar === 'lunas' ? 'Lunas' : 'Belum Lunas';

                                    $detailMotor = $penyewaan->penyewaanMotor->map(function ($d) {
                                        $nama  = $d->motor->nama_motor ?? 'Motor';
                                        $hari  = (int) $d->jml;
                                        $harga = 'Rp ' . number_format((float) $d->subtotal, 0, ',', '.');
                                        return "{$nama} ({$hari} hari, {$harga})";
                                    })->implode(', ');

                                    $total = 'Rp ' . number_format((float) $penyewaan->total_harga, 0, ',', '.');

                                    $set('deskripsi', "Pembayaran sewa {$state} via {$metode} - {$status}. Detail: {$detailMotor}. Total: {$total}");
                                }
                            }),

                        // ✅ Tanggal di bawah, terisi otomatis tapi bisa diedit manual
                        DatePicker::make('tgl')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now())
                            ->displayFormat('d M Y')
                            ->native(false)
                            ->helperText('Terisi otomatis dari faktur, bisa diubah manual jika perlu.'),

                        Textarea::make('deskripsi')
                            ->label('Keterangan Transaksi')
                            ->placeholder('Deskripsi otomatis muncul setelah memilih faktur...')
                            ->rows(3)
                            ->autosize(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Entri Debit & Kredit')
                    ->description('Pastikan total debit sama dengan total kredit.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Repeater::make('items')
                            ->label(false)
                            ->relationship('jurnaldetail')
                            ->addActionLabel('+ Tambah Baris Jurnal')
                            ->schema([
                                Select::make('coa_id')
                                    ->label('Nama Akun')
                                    ->placeholder('Cari akun...')
                                    ->options(Akun::all()->pluck('nama_akun', 'id'))
                                    ->searchable()
                                    ->required(),

                                TextInput::make('debit')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->required(),

                                TextInput::make('credit')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->required(),

                                Textarea::make('deskripsi')
                                    ->label('Catatan Baris')
                                    ->placeholder('Opsional...')
                                    ->rows(2),
                            ])
                            ->columns(2)
                            ->required()
                            ->reorderableWithButtons(),
                    ])
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('no_referensi')
                    ->label('No. Faktur')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tgl')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('deskripsi')
                    ->label('Keterangan')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->deskripsi),

                TextColumn::make('jurnaldetail.debit')
                    ->label('Total Debit')
                    ->formatStateUsing(fn ($state, $record) => rupiah($record->jurnaldetail()->sum('debit')))
                    ->alignment('end')
                    ->color('success'),

                TextColumn::make('jurnaldetail.credit')
                    ->label('Total Kredit')
                    ->formatStateUsing(fn ($state, $record) => rupiah($record->jurnaldetail()->sum('credit')))
                    ->alignment('end')
                    ->color('danger'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(''),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('tgl', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListJurnals::route('/'),
            'create' => Pages\CreateJurnal::route('/create'),
            'edit'   => Pages\EditJurnal::route('/{record}/edit'),
        ];
    }
}