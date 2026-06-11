#!/usr/bin/env bash
#
# HostingPanel one-line bootstrap.
#
# Install on any fresh Ubuntu 22.04/24.04 VPS with:
#
#   curl -fsSL https://raw.githubusercontent.com/rifatrajbd/HostingPanel-Minimal-Apache-PHP-Based-Panel/main/installer/web-install.sh \
#     | sudo bash -s -- --email you@example.com
#
# All arguments are passed straight through to install.sh
# (--email, --hostname). Override the repo with HOSTINGPANEL_REPO=…
#
set -euo pipefail

REPO="${HOSTINGPANEL_REPO:-https://github.com/rifatrajbd/HostingPanel-Minimal-Apache-PHP-Based-Panel.git}"
SRC=/opt/hostingpanel-src

[[ $EUID -eq 0 ]] || { echo "Run as root: curl … | sudo bash"; exit 1; }

export DEBIAN_FRONTEND=noninteractive
command -v git >/dev/null || { apt-get update -qq; apt-get install -y -qq git; }

echo "[hostingpanel] Fetching ${REPO} …"
rm -rf "$SRC"
git clone --depth 1 "$REPO" "$SRC"

# Remember the repo so `panelctl panel:self-update` can re-clone if needed.
mkdir -p /etc/hostingpanel
echo "$REPO" > /etc/hostingpanel/repo-url

exec bash "$SRC/installer/install.sh" "$@"
