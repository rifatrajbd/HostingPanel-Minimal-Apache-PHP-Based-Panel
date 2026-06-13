<?php

namespace App\Filament\Widgets;

use App\Models\DnsZone;
use App\Models\MailDomain;
use App\Models\Mailbox;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Services\SystemStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CountsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $s = app(SystemStats::class)->snapshot();
        $disk = $s['disk'];
        $mem = $s['memory'];

        return [
            Stat::make('Sites', Site::count())
                ->description('Hosted websites')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary')
                ->url(\App\Filament\Resources\SiteResource::getUrl()),
            Stat::make('Databases', SiteDatabase::count())
                ->description('MySQL databases')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->url(\App\Filament\Resources\SiteDatabaseResource::getUrl()),
            Stat::make('Mailboxes', Mailbox::count())
                ->description(MailDomain::count() . ' mail domain(s)')
                ->descriptionIcon('heroicon-m-envelope')
                ->url(\App\Filament\Resources\MailDomainResource::getUrl()),
            Stat::make('DNS zones', DnsZone::count())
                ->description('Authoritative zones')
                ->descriptionIcon('heroicon-m-globe-europe-africa')
                ->url(\App\Filament\Resources\DnsZoneResource::getUrl()),
            Stat::make('Disk', $disk['percent'] . '%')
                ->description("{$disk['used_gb']} / {$disk['total_gb']} GB")
                ->descriptionIcon('heroicon-m-server')
                ->color($disk['percent'] > 85 ? 'danger' : ($disk['percent'] > 65 ? 'warning' : 'success')),
            Stat::make('Memory', $mem['percent'] . '%')
                ->description("{$mem['used_mb']} / {$mem['total_mb']} MB")
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color($mem['percent'] > 85 ? 'danger' : ($mem['percent'] > 65 ? 'warning' : 'success')),
        ];
    }
}
