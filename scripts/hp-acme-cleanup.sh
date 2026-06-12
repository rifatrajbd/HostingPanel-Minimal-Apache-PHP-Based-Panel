#!/usr/bin/env bash
# certbot DNS-01 cleanup hook: remove the _acme-challenge TXT records.
set -euo pipefail
/usr/local/bin/panelctl dns:acme --action del \
    --domain "${CERTBOT_DOMAIN}" --value "${CERTBOT_VALIDATION:-x}"
