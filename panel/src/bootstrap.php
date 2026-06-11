<?php

declare(strict_types=1);

use Panel\Auth\AuthService;
use Panel\Controllers\AuthController;
use Panel\Controllers\DashboardController;
use Panel\Controllers\DatabasesController;
use Panel\Controllers\FilesController;
use Panel\Controllers\MailController;
use Panel\Controllers\PhpController;
use Panel\Controllers\SecurityController;
use Panel\Controllers\SettingsController;
use Panel\Controllers\SitesController;
use Panel\Controllers\SslController;
use Panel\Database;
use Panel\Middleware\AuthMiddleware;
use Panel\Middleware\CsrfMiddleware;
use Panel\Middleware\SecurityHeadersMiddleware;
use Panel\Middleware\SessionMiddleware;
use Panel\Services\PanelCtl;
use Panel\Services\SystemStats;
use Panel\Support\View;
use Slim\App;
use Slim\Factory\AppFactory;

return static function (): App {
    $settings = require dirname(__DIR__) . '/config/settings.php';

    $db = new Database($settings['db_path']);
    $db->migrate();

    $view = new View(dirname(__DIR__) . '/templates');
    $auth = new AuthService($db, $settings);
    $ctl = new PanelCtl($settings);
    $stats = new SystemStats();

    $app = AppFactory::create();

    $authController = new AuthController($view, $auth);
    $dashboard = new DashboardController($view, $db, $stats);
    $sites = new SitesController($view, $db, $ctl, $settings);
    $databases = new DatabasesController($view, $db, $ctl);
    $mail = new MailController($view, $db, $ctl);
    $security = new SecurityController($view, $db, $auth);
    $files = new FilesController($view, $db, $ctl, $settings);
    $php = new PhpController($view, $db, $ctl, $settings);
    $ssl = new SslController($view, $db, $ctl);
    $settingsCtrl = new SettingsController($view, $db, $ctl);

    // Public routes (still behind session + CSRF + headers)
    $app->get('/login', [$authController, 'showLogin']);
    $app->post('/login', [$authController, 'login']);
    $app->get('/login/2fa', [$authController, 'showTwoFactor']);
    $app->post('/login/2fa', [$authController, 'twoFactor']);

    // Authenticated routes
    $app->group('', function ($group) use (
        $authController,
        $dashboard,
        $sites,
        $databases,
        $mail,
        $security,
        $files,
        $php,
        $ssl,
        $settingsCtrl
    ): void {
        $group->post('/logout', [$authController, 'logout']);
        $group->get('/', [$dashboard, 'index']);

        $group->get('/sites', [$sites, 'index']);
        $group->get('/sites/create', [$sites, 'createForm']);
        $group->post('/sites', [$sites, 'create']);
        $group->get('/sites/{id:[0-9]+}', [$sites, 'show']);
        $group->post('/sites/{id:[0-9]+}/php', [$sites, 'updatePhp']);
        $group->post('/sites/{id:[0-9]+}/cfonly', [$sites, 'toggleCf']);
        $group->post('/sites/{id:[0-9]+}/cron', [$sites, 'cronAdd']);
        $group->post('/sites/{id:[0-9]+}/cron/{cid:[0-9]+}/delete', [$sites, 'cronDelete']);
        $group->post('/sites/{id:[0-9]+}/ssl', [$sites, 'issueSsl']);
        $group->post('/sites/{id:[0-9]+}/delete', [$sites, 'delete']);

        $group->get('/files', [$files, 'index']);
        $group->get('/files/download', [$files, 'download']);
        $group->get('/files/edit', [$files, 'edit']);
        $group->post('/files/save', [$files, 'save']);
        $group->post('/files/upload', [$files, 'upload']);
        $group->post('/files/action', [$files, 'action']);

        $group->get('/databases', [$databases, 'index']);
        $group->post('/databases', [$databases, 'create']);
        $group->post('/databases/{id:[0-9]+}/delete', [$databases, 'delete']);

        $group->get('/mail', [$mail, 'index']);
        $group->post('/mail/domains', [$mail, 'addDomain']);
        $group->post('/mail/domains/{id:[0-9]+}/delete', [$mail, 'deleteDomain']);
        $group->post('/mail/mailboxes', [$mail, 'addMailbox']);
        $group->post('/mail/mailboxes/{id:[0-9]+}/delete', [$mail, 'deleteMailbox']);
        $group->post('/mail/queue/flush', [$mail, 'queueFlush']);
        $group->post('/mail/queue/delete', [$mail, 'queueDelete']);

        $group->get('/ssl', [$ssl, 'index']);
        $group->post('/ssl/renew', [$ssl, 'renew']);
        $group->post('/ssl/delete', [$ssl, 'delete']);

        $group->get('/php', [$php, 'index']);
        $group->post('/php/ext', [$php, 'ext']);

        $group->get('/settings', [$settingsCtrl, 'index']);
        $group->post('/settings/panel-domain', [$settingsCtrl, 'panelDomain']);
        $group->post('/settings/backup', [$settingsCtrl, 'backupSave']);
        $group->post('/settings/backup/test', [$settingsCtrl, 'backupTest']);
        $group->post('/settings/backup/run', [$settingsCtrl, 'backupRun']);
        $group->post('/settings/self-update', [$settingsCtrl, 'selfUpdate']);

        $group->get('/security', [$security, 'index']);
        $group->post('/security/password', [$security, 'changePassword']);
        $group->post('/security/2fa/start', [$security, 'startTwoFactor']);
        $group->post('/security/2fa/confirm', [$security, 'confirmTwoFactor']);
        $group->post('/security/2fa/disable', [$security, 'disableTwoFactor']);
    })->add(new AuthMiddleware());

    // Middleware: outermost added last. Order of execution:
    // SecurityHeaders -> Session -> BodyParsing -> Csrf -> routing -> route handlers
    $app->addRoutingMiddleware();
    $app->add(new CsrfMiddleware());
    $app->addBodyParsingMiddleware();
    $app->add(new SessionMiddleware($settings));
    $app->add(new SecurityHeadersMiddleware());

    $errorMiddleware = $app->addErrorMiddleware($settings['env'] === 'dev', true, true);
    $errorMiddleware->getDefaultErrorHandler()->forceContentType('text/html');

    return $app;
};
