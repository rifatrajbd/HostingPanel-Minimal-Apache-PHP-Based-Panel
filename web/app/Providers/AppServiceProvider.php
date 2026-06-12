<?php

namespace App\Providers;

use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Models\FtpAccount;
use App\Models\MailDomain;
use App\Models\Mailbox;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Observers\DnsObserver;
use App\Observers\FtpAccountObserver;
use App\Observers\MailboxObserver;
use App\Observers\MailDomainObserver;
use App\Observers\SiteDatabaseObserver;
use App\Observers\SiteObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Site::observe(SiteObserver::class);
        SiteDatabase::observe(SiteDatabaseObserver::class);
        MailDomain::observe(MailDomainObserver::class);
        Mailbox::observe(MailboxObserver::class);
        FtpAccount::observe(FtpAccountObserver::class);

        // DNS: keep PowerDNS in sync with the panel's zone/record tables.
        DnsZone::created(fn (DnsZone $z) => app(DnsObserver::class)->zoneCreated($z));
        DnsZone::deleting(fn (DnsZone $z) => app(DnsObserver::class)->zoneDeleting($z));
        DnsRecord::saved(fn (DnsRecord $r) => app(DnsObserver::class)->recordSaved($r));
        DnsRecord::deleted(fn (DnsRecord $r) => app(DnsObserver::class)->recordDeleted($r));

        // Log failed panel logins in a fail2ban-parseable format.
        Event::listen(Failed::class, function (Failed $event) {
            $email = $event->credentials['email'] ?? 'unknown';
            $line = '[' . date('Y-m-d H:i:s') . '] panel login failed from '
                . request()->ip() . ' (' . $email . ')' . PHP_EOL;
            @file_put_contents(config('hostingpanel.auth_log'), $line, FILE_APPEND);
        });
    }
}
