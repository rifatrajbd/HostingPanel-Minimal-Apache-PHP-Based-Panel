<?php

namespace App\Filament\Widgets;

use App\Models\Mailbox;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Services\SystemStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CountsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $load = app(SystemStats::class)->snapshot()['load'][0] ?? 0;

        return [
            Stat::make('Sites', Site::count())
                ->icon('heroicon-o-globe-alt')
                ->url(\App\Filament\Resources\SiteResource::getUrl()),
            Stat::make('Databases', SiteDatabase::count())
                ->icon('heroicon-o-circle-stack')
                ->url(\App\Filament\Resources\SiteDatabaseResource::getUrl()),
            Stat::make('Mailboxes', Mailbox::count())
                ->icon('heroicon-o-envelope'),
            Stat::make('Load (1m)', $load)
                ->icon('heroicon-o-cpu-chip'),
        ];
    }
}
