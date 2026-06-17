<?php

namespace App\Filament\Widgets;

use App\Models\MotorInsight;
use Filament\Widgets\Widget;

class MotorInsightWidget extends Widget
{
    protected static string $view = 'filament.widgets.motor-insight-widget';

    protected int|string|array $columnSpan = 'full';

    public ?MotorInsight $insight = null;

    public function mount(): void
    {
        $this->insight = MotorInsight::latest()->first();
    }
}
