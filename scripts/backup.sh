#!/usr/bin/env bash
#
# HostingPanel backup: all MySQL databases + every site in /var/www +
# the panel's own data, uploaded via rclone to the "backup:" remote
# (Google Drive or FTP — see /etc/hostingpanel/rclone.conf).
#
# Installed as /usr/local/bin/hostingpanel-backup, run by cron
# (managed from the panel's Settings page).
#
set -euo pipefail

CONF=/etc/hostingpanel/rclone.conf
ENV_FILE=/etc/hostingpanel/backup.env
[[ -f "$CONF" ]] || { echo "No rclone config — configure backups in the panel first."; exit 1; }
REMOTE_PATH=hostingpanel-backups
RETENTION=7
[[ -f "$ENV_FILE" ]] && source "$ENV_FILE"

STAMP="$(date +%Y-%m-%d_%H%M)"
WORK="/var/backups/hostingpanel/${STAMP}"
mkdir -p "$WORK"
trap 'rm -rf "$WORK"' EXIT

echo "[$(date '+%F %T')] backup ${STAMP} starting"

# --- MySQL: every non-system database ------------------------------------
for db in $(mysql -N -e "SHOW DATABASES" \
        | grep -Ev '^(information_schema|performance_schema|mysql|sys)$'); do
    echo "  dumping database: ${db}"
    mysqldump --single-transaction --quick --routines "$db" | gzip > "${WORK}/db-${db}.sql.gz"
done

# --- Site files -----------------------------------------------------------
for dir in /var/www/*/; do
    site="$(basename "$dir")"
    [[ "$site" == "html" || "$site" == "panel-acme" ]] && continue
    echo "  archiving site: ${site}"
    tar czf "${WORK}/site-${site}.tar.gz" -C /var/www "$site"
done

# --- Panel data -----------------------------------------------------------
cp /var/lib/hostingpanel/panel.sqlite "${WORK}/panel.sqlite" 2>/dev/null || true

# --- Upload ---------------------------------------------------------------
echo "  uploading to backup:${REMOTE_PATH}/${STAMP}"
rclone --config "$CONF" copy "$WORK" "backup:${REMOTE_PATH}/${STAMP}" --transfers 2

# --- Retention: keep the newest $RETENTION backup folders ------------------
mapfile -t old < <(rclone --config "$CONF" lsf --dirs-only "backup:${REMOTE_PATH}" \
    | sort | head -n -"$RETENTION" || true)
for d in "${old[@]:-}"; do
    [[ -z "$d" ]] && continue
    echo "  pruning old backup: ${d}"
    rclone --config "$CONF" purge "backup:${REMOTE_PATH}/${d%/}"
done

echo "[$(date '+%F %T')] backup ${STAMP} finished OK"
