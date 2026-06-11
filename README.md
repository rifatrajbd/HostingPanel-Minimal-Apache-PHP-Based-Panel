# HostingPanel

A lightweight, security-focused hosting control panel for a single Ubuntu VPS.
Inspired by CloudPanel, built on **Apache + PHP-FPM** with a full mail stack.

## Stack

| Layer | Technology |
|---|---|
| Panel app | PHP 8.1+ (Slim 4), SQLite, Tailwind CSS + Alpine.js |
| Web server (sites) | Apache 2.4 + PHP-FPM (multi-version: 7.4 / 8.1 / 8.2 / 8.3) |
| Site databases | MariaDB |
| SSL | Let's Encrypt (certbot, apache plugin) |
| Mail | Postfix (SMTP) + Dovecot (IMAP/LMTP) + Rspamd (spam + DKIM) |
| Privileged ops | `panelctl` CLI invoked via tightly-scoped sudo |

## Architecture

```
┌──────────────────────────────────┐
│ Panel web UI  (PHP, runs as the  │  ← https://your-vps:8443
│ unprivileged "hostingpanel" user)│
└───────────────┬──────────────────┘
                │ sudo -n /usr/local/bin/panelctl …   (scoped sudoers entry)
┌───────────────▼──────────────────┐
│ panelctl (root CLI)              │
│  site:create / site:delete       │
│  ssl:issue                       │
│  db:create / db:delete           │
│  mail:domain:add  mailbox:add …  │
└──────────────────────────────────┘
```

The web UI never runs as root. All privileged operations go through `panelctl`,
which validates every input again independently. Secrets (DB / mailbox
passwords) are passed via **stdin**, never via argv or environment.

## Features

- **Sites**: Apache vhosts + per-site PHP-FPM pools and system users, PHP 7.4/8.1/8.3 per site
- **File manager**: browse, upload, edit, rename, chmod, copy/cut/paste, compress (.zip/.tar.gz), extract
- **Databases**: MariaDB databases/users + bundled **phpMyAdmin** (`/phpmyadmin`)
- **Mail**: domains, mailboxes, DKIM, delivery **queue viewer** (retry/delete), mail log, **SnappyMail webmail** (`/webmail`)
- **SSL Manager**: issue, renew, delete Let's Encrypt certificates; auto-renewal built in
- **PHP Manager**: per-site version + ini limits, server-wide extension install/enable/disable
- **Cron manager**: per-site cron jobs running as the site's user
- **Cloudflare-only mode**: per-site toggle that blocks direct-to-origin traffic (auto-updated IP ranges)
- **Panel domain**: one click moves the panel to its own domain with a trusted certificate
- **Backups**: scheduled site files + MySQL dumps to **Google Drive or FTP** via rclone, with retention

## Install — one line (fresh Ubuntu 22.04 / 24.04 VPS)

```bash
curl -fsSL https://raw.githubusercontent.com/rifatrajbd/HostingPanel-Minimal-Apache-PHP-Based-Panel/main/installer/web-install.sh \
  | sudo bash -s -- --email you@example.com
```

Or manually:

```bash
git clone <this-repo> /opt/hostingpanel-src
sudo bash /opt/hostingpanel-src/installer/install.sh --email you@example.com
```

The installer prints the panel URL and the generated admin credentials when done.

## Security features

- Argon2id password hashing
- TOTP two-factor authentication (RFC 6238)
- Login rate limiting (per-IP and per-username, SQLite-backed)
- CSRF tokens on every POST
- Hardened sessions: HttpOnly, Secure, SameSite=Strict, strict mode, ID regeneration on privilege change
- Security headers: CSP (`'self'` only), X-Frame-Options DENY, nosniff, referrer-policy
- Panel served on port **8443** with its own certificate
- `panelctl` reachable only through an exact-match sudoers rule
- Audit log of every panel action
- UFW + fail2ban configured by the installer

## Local development (Windows / XAMPP)

```powershell
cd panel
composer install
$env:PANEL_ENV = "dev"
php -S 127.0.0.1:8081 -t public
```

In dev mode `panelctl` calls run with `--dry-run` and only print what they would do.

## Repository layout

```
panel/        Web UI (Slim 4 app)
panelctl/     Privileged CLI (standalone PHP, no dependencies)
etc/          Config templates: sudoers, fail2ban, Postfix/Dovecot/Rspamd
installer/    install.sh — provisions a fresh Ubuntu VPS end-to-end
```
