<?php

namespace App\Filament\Widgets;

use App\Services\SystemStats;
use Filament\Widgets\Widget;

class ServerStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.server-stats';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function getStats(): array
    {
        return app(SystemStats::class)->snapshot();
    }
}
