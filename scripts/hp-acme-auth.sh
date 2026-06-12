#!/usr/bin/env bash
# certbot DNS-01 auth hook: publish the _acme-challenge TXT in our PowerDNS
# zone. Invoked by `panelctl ssl:wildcard` (runs as root under certbot).
set -euo pipefail
/usr/local/bin/panelctl dns:acme --action add \
    --domain "${CERTBOT_DOMAIN}" --value "${CERTBOT_VALIDATION}"
# Give PowerDNS a moment before certbot queries the authoritative server.
sleep 3
