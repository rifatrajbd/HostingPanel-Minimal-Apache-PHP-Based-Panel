#!/usr/bin/env bash
#
# HostingPanel installer — fresh Ubuntu 22.04 / 24.04 VPS.
#
#   sudo bash installer/install.sh [--email you@example.com] [--hostname mail.example.com]
#
# Installs: Apache + PHP-FPM (7.4/8.1/8.3), MariaDB, certbot,
# Postfix + Dovecot + Rspamd, UFW, fail2ban, and the panel itself
# on https://<server-ip>:8443
#
set -euo pipefail

# ------------------------------------------------------------------ helpers
log()  { echo -e "\e[1;34m[hostingpanel]\e[0m $*"; }
fail() { echo -e "\e[1;31m[hostingpanel] ERROR:\e[0m $*" >&2; exit 1; }

# Download with sane timeouts; retry over IPv4 (many VPSes have broken
# IPv6 routing, which makes plain curl hang for minutes).
fetch() {
    curl -fsSL --connect-timeout 20 --retry 2 "$@" \
        || curl -fsSL -4 --connect-timeout 20 --retry 2 "$@"
}

[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash installer/install.sh"
grep -qE '22\.04|24\.04' /etc/os-release || fail "Ubuntu 22.04 or 24.04 required."

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INSTALL_DIR=/opt/hostingpanel
PANEL_PHP=8.3
PHP_VERSIONS=(7.4 8.1 8.3)

LE_EMAIL=""
MAIL_HOSTNAME="$(hostname -f 2>/dev/null || hostname)"
while [[ $# -gt 0 ]]; do
    case "$1" in
        --email)    LE_EMAIL="$2"; shift 2 ;;
        --hostname) MAIL_HOSTNAME="$2"; shift 2 ;;
        *) fail "Unknown option: $1" ;;
    esac
done

export DEBIAN_FRONTEND=noninteractive

# ------------------------------------------------------------------ packages
log "Updating apt and adding the ondrej/php PPA…"
apt-get update -qq
apt-get install -y -qq software-properties-common curl gnupg zip unzip git rsync openssl rclone
add-apt-repository -y ppa:ondrej/php >/dev/null
apt-get update -qq

log "Installing Apache, MariaDB, certbot, UFW, fail2ban…"
apt-get install -y -qq apache2 mariadb-server python3-certbot-apache ufw fail2ban

log "Installing PHP ${PHP_VERSIONS[*]} (FPM + common extensions)…"
for v in "${PHP_VERSIONS[@]}"; do
    apt-get install -y -qq \
        "php${v}-fpm" "php${v}-cli" "php${v}-mysql" "php${v}-xml" \
        "php${v}-mbstring" "php${v}-curl" "php${v}-zip" "php${v}-gd" "php${v}-intl"
done
apt-get install -y -qq "php${PANEL_PHP}-sqlite3"

if ! command -v composer >/dev/null; then
    log "Installing Composer…"
    if [[ -f "${SRC_DIR}/third-party/composer.phar" ]]; then
        install -m 755 "${SRC_DIR}/third-party/composer.phar" /usr/local/bin/composer
    elif fetch https://getcomposer.org/installer -o /tmp/composer-setup.php; then
        "php${PANEL_PHP}" /tmp/composer-setup.php \
            --install-dir=/usr/local/bin --filename=composer --quiet
        rm -f /tmp/composer-setup.php
    elif fetch https://github.com/composer/composer/releases/latest/download/composer.phar \
            -o /usr/local/bin/composer; then
        chmod 755 /usr/local/bin/composer
    else
        log "Falling back to the Ubuntu composer package…"
        apt-get install -y -qq composer || fail "Could not install Composer by any method."
    fi
fi

log "Installing mail stack (Postfix, Dovecot, Rspamd)…"
echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
echo "postfix postfix/mailname string ${MAIL_HOSTNAME}" | debconf-set-selections
apt-get install -y -qq postfix postfix-mysql \
    dovecot-imapd dovecot-lmtpd dovecot-mysql rspamd

# ------------------------------------------------------------------ users & dirs
log "Creating system users and directories…"
id hostingpanel &>/dev/null || \
    useradd --system --home-dir /var/lib/hostingpanel --shell /usr/sbin/nologin hostingpanel
id vmail &>/dev/null || \
    useradd --system --uid 5000 --home-dir /var/vmail --shell /usr/sbin/nologin vmail

install -d -o hostingpanel -g hostingpanel -m 750 /var/lib/hostingpanel
install -d -o hostingpanel -g hostingpanel -m 750 /var/lib/hostingpanel/uploads
install -d -o hostingpanel -g hostingpanel -m 750 /var/lib/hostingpanel/pma-tmp
install -d -o hostingpanel -g hostingpanel -m 750 /var/lib/hostingpanel/webmail-data
install -d -o hostingpanel -g hostingpanel -m 750 /var/log/hostingpanel
install -d -o vmail -g vmail -m 770 /var/vmail
install -d -m 755 /etc/hostingpanel
install -d -m 750 /etc/hostingpanel/ssl
install -d -m 755 /etc/hostingpanel/site-access
install -d -m 755 /var/backups/hostingpanel
install -d -o _rspamd -g _rspamd -m 750 /var/lib/rspamd/dkim

# ------------------------------------------------------------------ panel files
log "Installing panel to ${INSTALL_DIR}…"
rsync -a --delete \
    --exclude 'panel/var' --exclude 'panel/vendor' --exclude '.git' \
    --exclude 'phpmyadmin' --exclude 'webmail' \
    "${SRC_DIR}/" "${INSTALL_DIR}/"
chown -R root:root "${INSTALL_DIR}"   # NOT writable by the panel user

log "Running composer install…"
(cd "${INSTALL_DIR}/panel" && composer install --no-dev --quiet --no-interaction)

# Frontend assets ship in the repo; download only if somehow missing.
if [[ ! -f "${INSTALL_DIR}/panel/public/assets/tailwind.js" ]]; then
    log "Downloading frontend assets (Tailwind, Alpine)…"
    fetch https://cdn.tailwindcss.com/3.4.5 -o "${INSTALL_DIR}/panel/public/assets/tailwind.js" \
        || log "WARNING: Tailwind download failed — UI will be unstyled until you fetch it."
    fetch https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js \
        -o "${INSTALL_DIR}/panel/public/assets/alpine.min.js" \
        || log "WARNING: Alpine download failed."
fi

# panelctl wrapper (root-owned; sudoers points here)
cat > /usr/local/bin/panelctl <<EOF
#!/bin/bash
exec /usr/bin/php${PANEL_PHP} ${INSTALL_DIR}/panelctl/panelctl "\$@"
EOF
chmod 755 /usr/local/bin/panelctl

# backup script + weekly Cloudflare IP refresh
install -m 755 "${INSTALL_DIR}/scripts/backup.sh" /usr/local/bin/hostingpanel-backup
cat > /etc/cron.weekly/hostingpanel-cfips <<'EOF'
#!/bin/bash
/usr/local/bin/panelctl cf:update >/dev/null 2>&1 || true
EOF
chmod 755 /etc/cron.weekly/hostingpanel-cfips

install -m 440 "${INSTALL_DIR}/etc/sudoers.d/hostingpanel" /etc/sudoers.d/hostingpanel
visudo -c >/dev/null || fail "sudoers validation failed"

# ------------------------------------------------------------------ phpMyAdmin + SnappyMail webmail
PMA_VERSION=5.2.1
if [[ ! -d ${INSTALL_DIR}/phpmyadmin ]]; then
    log "Installing phpMyAdmin ${PMA_VERSION}…"
    PMA_TAR="${INSTALL_DIR}/third-party/phpmyadmin.tar.gz"
    if [[ ! -f "${PMA_TAR}" ]]; then
        fetch "https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz" \
            -o /tmp/pma.tar.gz && PMA_TAR=/tmp/pma.tar.gz
    fi
    if [[ -f "${PMA_TAR}" ]]; then
        mkdir -p "${INSTALL_DIR}/phpmyadmin"
        tar xzf "${PMA_TAR}" -C "${INSTALL_DIR}/phpmyadmin" --strip-components=1
        rm -f /tmp/pma.tar.gz
        cat > "${INSTALL_DIR}/phpmyadmin/config.inc.php" <<EOF
<?php
\$cfg['blowfish_secret'] = '$(openssl rand -base64 32)';
\$cfg['TempDir'] = '/var/lib/hostingpanel/pma-tmp';
\$i = 1;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = '127.0.0.1';
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
EOF
    else
        log "WARNING: phpMyAdmin download failed — install later by re-running this script."
    fi
fi

if [[ ! -d ${INSTALL_DIR}/webmail ]]; then
    log "Installing SnappyMail webmail (maintained RainLoop fork)…"
    SNAPPY_ZIP="${INSTALL_DIR}/third-party/snappymail.zip"
    if [[ ! -f "${SNAPPY_ZIP}" ]]; then
        SNAPPY_URL="$(fetch https://api.github.com/repos/the-djmaze/snappymail/releases/latest \
            | grep -o 'https://[^"]*snappymail-[0-9.]*\.zip' | head -1 || true)"
        [[ -n "${SNAPPY_URL}" ]] && fetch "${SNAPPY_URL}" -o /tmp/snappymail.zip \
            && SNAPPY_ZIP=/tmp/snappymail.zip
    fi
    if [[ -f "${SNAPPY_ZIP}" ]]; then
        mkdir -p "${INSTALL_DIR}/webmail"
        unzip -qo "${SNAPPY_ZIP}" -d "${INSTALL_DIR}/webmail"
        rm -f /tmp/snappymail.zip
        cat > "${INSTALL_DIR}/webmail/include.php" <<'EOF'
<?php
define('APP_DATA_FOLDER_PATH', '/var/lib/hostingpanel/webmail-data/');
EOF
    else
        log "WARNING: SnappyMail download failed — install later by re-running this script."
    fi
fi

# ------------------------------------------------------------------ self-signed certs (panel + mail bootstrap)
log "Generating self-signed certificates for the panel and mail…"
for name in panel mail; do
    if [[ ! -f /etc/hostingpanel/ssl/${name}.key ]]; then
        openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
            -keyout "/etc/hostingpanel/ssl/${name}.key" \
            -out "/etc/hostingpanel/ssl/${name}.crt" \
            -subj "/CN=${MAIL_HOSTNAME}" 2>/dev/null
        chmod 600 "/etc/hostingpanel/ssl/${name}.key"
    fi
done

# ------------------------------------------------------------------ mail database
log "Configuring the mailserver database…"
MAILDB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)"
mysql -e "CREATE DATABASE IF NOT EXISTS mailserver CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -e "CREATE USER IF NOT EXISTS 'mailserver'@'127.0.0.1' IDENTIFIED BY '${MAILDB_PASSWORD}'"
mysql -e "ALTER USER 'mailserver'@'127.0.0.1' IDENTIFIED BY '${MAILDB_PASSWORD}'"
mysql -e "GRANT SELECT, INSERT, UPDATE, DELETE ON mailserver.* TO 'mailserver'@'127.0.0.1'"
mysql mailserver < "${INSTALL_DIR}/etc/mailserver-schema.sql"

# ------------------------------------------------------------------ postfix
log "Configuring Postfix…"
install -d -m 750 -o root -g postfix /etc/postfix/mysql
for f in mysql-virtual-mailbox-domains.cf mysql-virtual-mailbox-maps.cf mysql-virtual-alias-maps.cf; do
    sed "s|@MAILDB_PASSWORD@|${MAILDB_PASSWORD}|" "${INSTALL_DIR}/etc/mail/${f}" \
        > "/etc/postfix/mysql/${f}"
    chmod 640 "/etc/postfix/mysql/${f}"
    chgrp postfix "/etc/postfix/mysql/${f}"
done

postconf -e "myhostname = ${MAIL_HOSTNAME}"
postconf -e "mydestination = localhost"
postconf -e "virtual_mailbox_domains = mysql:/etc/postfix/mysql/mysql-virtual-mailbox-domains.cf"
postconf -e "virtual_mailbox_maps = mysql:/etc/postfix/mysql/mysql-virtual-mailbox-maps.cf"
postconf -e "virtual_alias_maps = mysql:/etc/postfix/mysql/mysql-virtual-alias-maps.cf"
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_tls_cert_file = /etc/hostingpanel/ssl/mail.crt"
postconf -e "smtpd_tls_key_file = /etc/hostingpanel/ssl/mail.key"
postconf -e "smtpd_tls_security_level = may"
postconf -e "smtp_tls_security_level = may"
postconf -e "smtpd_relay_restrictions = permit_mynetworks permit_sasl_authenticated defer_unauth_destination"
postconf -e "smtpd_recipient_restrictions = permit_sasl_authenticated, permit_mynetworks, reject_unauth_destination"
postconf -e "smtpd_milters = inet:127.0.0.1:11332"
postconf -e "non_smtpd_milters = inet:127.0.0.1:11332"
postconf -e "milter_default_action = accept"
postconf -e "message_size_limit = 52428800"

# Submission (587, STARTTLS) for mail clients
postconf -M submission/inet="submission inet n - y - - smtpd"
postconf -P "submission/inet/syslog_name=postfix/submission"
postconf -P "submission/inet/smtpd_tls_security_level=encrypt"
postconf -P "submission/inet/smtpd_sasl_auth_enable=yes"
postconf -P "submission/inet/smtpd_relay_restrictions=permit_sasl_authenticated,reject"

# ------------------------------------------------------------------ dovecot
log "Configuring Dovecot…"
cp "${INSTALL_DIR}/etc/mail/dovecot-local.conf" /etc/dovecot/local.conf
sed "s|@MAILDB_PASSWORD@|${MAILDB_PASSWORD}|" "${INSTALL_DIR}/etc/mail/dovecot-sql.conf.ext" \
    > /etc/dovecot/dovecot-sql.conf.ext
chmod 640 /etc/dovecot/dovecot-sql.conf.ext
chgrp dovecot /etc/dovecot/dovecot-sql.conf.ext

# ------------------------------------------------------------------ rspamd
log "Configuring Rspamd (spam filtering + DKIM)…"
cp "${INSTALL_DIR}/etc/mail/rspamd-dkim_signing.conf" /etc/rspamd/local.d/dkim_signing.conf
cat > /etc/rspamd/local.d/worker-proxy.inc <<'EOF'
milter = yes;
timeout = 120s;
upstream "local" {
  default = yes;
  self_scan = yes;
}
EOF

# ------------------------------------------------------------------ apache + panel
log "Configuring Apache and the panel vhost…"
a2enmod -q proxy_fcgi setenvif rewrite ssl headers remoteip >/dev/null
a2dissite -q 000-default >/dev/null 2>&1 || true

cp "${INSTALL_DIR}/etc/panel-fpm-pool.conf" "/etc/php/${PANEL_PHP}/fpm/pool.d/hostingpanel.conf"
cp "${INSTALL_DIR}/etc/panel-vhost.conf" /etc/apache2/sites-available/hostingpanel.conf
a2ensite -q hostingpanel.conf >/dev/null

[[ -n "${LE_EMAIL}" ]] && echo "${LE_EMAIL}" > /etc/hostingpanel/le-email

log "Creating panel admin user…"
ADMIN_OUTPUT="$(sudo -u hostingpanel PANEL_DB=/var/lib/hostingpanel/panel.sqlite \
    "php${PANEL_PHP}" "${INSTALL_DIR}/panel/bin/create-admin.php" admin)"

# ------------------------------------------------------------------ firewall + fail2ban
log "Configuring UFW and fail2ban…"
ufw allow OpenSSH >/dev/null
for port in 80 443 8443 25 587 993; do ufw allow "${port}/tcp" >/dev/null; done
ufw --force enable >/dev/null

cp "${INSTALL_DIR}/etc/fail2ban-jail.local" /etc/fail2ban/jail.d/hostingpanel.local

# ------------------------------------------------------------------ Cloudflare IP ranges (for CF-only mode)
log "Fetching Cloudflare IP ranges…"
/usr/local/bin/panelctl cf:update || log "WARNING: cf:update failed — run 'panelctl cf:update' later."

# ------------------------------------------------------------------ services
log "Restarting services…"
for v in "${PHP_VERSIONS[@]}"; do systemctl restart "php${v}-fpm"; done
systemctl restart apache2 postfix dovecot rspamd fail2ban
systemctl enable -q apache2 mariadb postfix dovecot rspamd fail2ban

# ------------------------------------------------------------------ done
SERVER_IP="$(curl -fsS -4 --connect-timeout 10 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')"
echo
echo "=============================================================="
echo "  HostingPanel installed!"
echo
echo "  Panel:      https://${SERVER_IP}:8443"
echo "              (self-signed certificate — accept the browser warning,"
echo "               or set a panel domain on the Settings page for a real cert)"
echo "  phpMyAdmin: https://${SERVER_IP}:8443/phpmyadmin/"
echo "  Webmail:    https://${SERVER_IP}:8443/webmail/"
echo
echo "  ${ADMIN_OUTPUT//$'\n'/$'\n'  }"
echo
echo "  Next steps:"
echo "   1. Log in and enable 2FA (Security page) right away."
echo "   2. Add your site, database, and mail domain in the panel."
echo "   3. Check outbound port 25:  timeout 5 bash -c '</dev/tcp/smtp.gmail.com/25' && echo open || echo blocked"
echo "      If blocked, ask your VPS provider to unblock it or use an SMTP relay."
echo "   4. Ask your provider to set the PTR record of ${SERVER_IP} to ${MAIL_HOSTNAME}."
echo "=============================================================="
