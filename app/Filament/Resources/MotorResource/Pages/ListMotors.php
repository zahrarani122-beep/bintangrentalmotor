<?php

namespace App\Filament\Resources\MotorResource\Pages;

use App\Filament\Widgets\MotorInsightWidget;
use App\Filament\Resources\MotorResource;
use App\Models\Motor;
use App\Services\GeminiMotorInsightService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMotors extends ListRecords
{
    protected static string $resource = MotorResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            MotorInsightWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('analisa_ai_motor')
                ->label('Analisa AI Motor')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Analisa AI Motor')
                ->modalDescription('Data master motor akan dikirim ke Gemini untuk dibuatkan insight sederhana.')
                ->action(function () {
                    if (Motor::count() === 0) {
                        Notification::make()
                            ->title('Data motor masih kosong.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(GeminiMotorInsightService::class)->analyze();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Analisa AI Motor gagal.')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Analisa AI Motor berhasil dibuat.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
