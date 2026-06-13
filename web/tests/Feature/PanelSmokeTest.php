<?php

namespace Tests\Feature;

use App\Models\MailDomain;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('HostingPanel');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_all_pages_render_for_admin(): void
    {
        // Seed a little data so list/detail pages have rows.
        $site = Site::create([
            'domain' => 'forum.example.com', 'php_version' => '8.1',
            'doc_root' => '/var/www/forum.example.com/htdocs',
            'system_user' => 'web-forumexamplecom', 'ini' => [],
        ]);
        $db = SiteDatabase::create(['name' => 'mybb', 'db_user' => 'mybb_user']);
        MailDomain::create(['domain' => 'example.com', 'dkim_dns' => 'TXT ...']);

        $zone = \App\Models\DnsZone::create(['domain' => 'example.com']);
        \App\Models\DnsRecord::create([
            'dns_zone_id' => $zone->id, 'type' => 'A', 'name' => '@', 'content' => '203.0.113.1',
        ]);
        \App\Models\FtpAccount::create(['username' => 'siteftp', 'site_id' => $site->id]);

        $this->actingAs($this->admin());

        $urls = [
            '/',
            '/sites',
            '/sites/create',
            "/sites/{$site->id}/manage",
            '/file-manager',
            "/file-edit?site={$site->id}&path=/htdocs/index.php",
            '/site-databases',
            '/site-databases/create',
            "/site-databases/{$db->id}/manage",
            '/dns-zones',
            '/dns-zones/create',
            "/dns-zones/{$zone->id}/records",
            '/ftp-accounts',
            '/ftp-accounts/create',
            '/mail-domains',
            '/mail-domains/create',
            '/mail-queue',
            '/ssl-manager',
            '/php-manager',
            '/settings',
            '/security',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }
}
