<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class PhpMyAdminController extends Controller
{
    /**
     * Hand phpMyAdmin a signon session (native PHP session it reads), then
     * redirect into it. Only reachable by authenticated panel admins, so
     * phpMyAdmin stays gated behind the panel login.
     */
    public function signon(): RedirectResponse
    {
        $password = config('hostingpanel.pma_password');
        if (!$password) {
            // SSO not configured (dev, or pma:setup hasn't run) — fall through
            // to phpMyAdmin's own login.
            return redirect('/phpmyadmin/');
        }

        // Native PHP session named exactly as phpMyAdmin's SignonSession.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('HostingPanelPMA');
        session_start();
        $_SESSION['PMA_single_signon_user'] = 'pma';
        $_SESSION['PMA_single_signon_password'] = $password;
        $_SESSION['PMA_single_signon_host'] = '127.0.0.1';
        session_write_close();

        return redirect('/phpmyadmin/');
    }
}
