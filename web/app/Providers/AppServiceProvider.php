<?php

namespace App\Providers;

use App\Models\MailDomain;
use App\Models\Mailbox;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Observers\MailboxObserver;
use App\Observers\MailDomainObserver;
use App\Observers\SiteDatabaseObserver;
use App\Observers\SiteObserver;
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
    }
}
