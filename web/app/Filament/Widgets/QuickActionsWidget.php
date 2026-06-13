<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FileManager;
use App\Filament\Pages\SslManager;
use App\Filament\Resources\DnsZoneResource;
use App\Filament\Resources\FtpAccountResource;
use App\Filament\Resources\MailDomainResource;
use App\Filament\Resources\SiteDatabaseResource;
use App\Filament\Resources\SiteResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    public function getActions(): array
    {
        return [
            ['New site', 'heroicon-o-globe-alt', SiteResource::getUrl('create'), 'sky'],
            ['New database', 'heroicon-o-circle-stack', SiteDatabaseResource::getUrl('create'), 'indigo'],
            ['New mail domain', 'heroicon-o-envelope', MailDomainResource::getUrl('create'), 'rose'],
            ['New DNS zone', 'heroicon-o-globe-europe-africa', DnsZoneResource::getUrl('create'), 'emerald'],
            ['New SFTP account', 'heroicon-o-arrow-up-on-square-stack', FtpAccountResource::getUrl('create'), 'amber'],
            ['File manager', 'heroicon-o-folder', FileManager::getUrl(), 'yellow'],
            ['SSL manager', 'heroicon-o-lock-closed', SslManager::getUrl(), 'teal'],
            ['phpMyAdmin', 'heroicon-o-table-cells', '/phpmyadmin-sso', 'blue'],
            ['Webmail', 'heroicon-o-at-symbol', '/webmail/', 'fuchsia'],
        ];
    }
}
