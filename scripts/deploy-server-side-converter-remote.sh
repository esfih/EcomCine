#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

REMOTE_USER="${REMOTE_USER:-efttsqrtff}"
REMOTE_HOST="${REMOTE_HOST:-209.16.158.249}"
REMOTE_PORT="${REMOTE_PORT:-5022}"
REMOTE_KEY="${REMOTE_KEY:-$HOME/.ssh/ecomcine_n0c}"
REMOTE_DOMAIN="${REMOTE_DOMAIN:-converter.ecomcine.com}"
REMOTE_APP_ROOT="${REMOTE_APP_ROOT:-converter}"
REMOTE_APP_URI="${REMOTE_APP_URI:-/}"
NODE_VERSION="${NODE_VERSION:-20.18.2}"
DEPLOY_VERSION="${1:-$(date +%Y.%m.%d-%H%M%S)}"
PLATFORM_SLUG="$(uname -s | tr '[:upper:]' '[:lower:]')-$(uname -m)"
STAGE_DIR="$REPO_ROOT/dist/server-side-converter/stage/server-side-converter-${PLATFORM_SLUG}"
REMOTE_HOME="/home/${REMOTE_USER}"
REMOTE_TARGET_DIR="${REMOTE_HOME}/${REMOTE_APP_ROOT}"
PASSENGER_LOG_FILE="${REMOTE_TARGET_DIR}/logs/passenger.log"
ENV_VARS='{"NODE_ENV":"production"}'

if [[ ! -f "$REMOTE_KEY" ]]; then
  echo "ERROR: SSH key not found at $REMOTE_KEY" >&2
  exit 4
fi

"$REPO_ROOT/scripts/build-server-side-converter-release.sh" "$DEPLOY_VERSION" >/dev/null

if [[ ! -d "$STAGE_DIR" ]]; then
  echo "ERROR: Expected build stage directory missing: $STAGE_DIR" >&2
  exit 4
fi

SSH_BASE=(ssh -i "$REMOTE_KEY" -p "$REMOTE_PORT")
SCP_BASE=(scp -i "$REMOTE_KEY" -P "$REMOTE_PORT")
RSYNC_SSH="ssh -i '$REMOTE_KEY' -p '$REMOTE_PORT'"

echo "Preparing remote application directory..."
"${SSH_BASE[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p '$REMOTE_TARGET_DIR' '$REMOTE_TARGET_DIR/logs' '$REMOTE_TARGET_DIR/tmp'"

echo "Syncing standalone converter files to ${REMOTE_DOMAIN}..."
rsync -az --delete \
  --exclude '.well-known/' \
  --exclude '.htaccess' \
  --exclude 'logs/' \
  --exclude 'tmp/' \
  -e "$RSYNC_SSH" "$STAGE_DIR/" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_TARGET_DIR}/"

echo "Restoring Passenger webroot routing..."
"${SSH_BASE[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "cat > '$REMOTE_TARGET_DIR/.htaccess' <<EOF
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

PassengerAppRoot \"$REMOTE_TARGET_DIR\"
PassengerBaseURI \"$REMOTE_APP_URI\"
PassengerNodejs \"$REMOTE_HOME/nodevenv/$REMOTE_APP_ROOT/20/bin/node\"
PassengerAppType node
PassengerStartupFile server.js
PassengerAppLogFile \"$PASSENGER_LOG_FILE\"
EOF"

echo "Configuring CloudLinux Node.js app..."
REMOTE_CONFIG_SCRIPT=$(cat <<'EOF'
set -euo pipefail
DOMAIN="$1"
APP_ROOT="$2"
APP_URI="$3"
NODE_VERSION="$4"
PASSENGER_LOG_FILE="$5"
ENV_VARS="$6"

create_app() {
  cloudlinux-selector create --json --interpreter nodejs \
    --domain "$DOMAIN" \
    --app-root "$APP_ROOT" \
    --app-uri "$APP_URI" \
    --version "$NODE_VERSION" \
    --startup-file server.js \
    --env-vars "$ENV_VARS" \
    --passenger-log-file "$PASSENGER_LOG_FILE"
}

set_app() {
  cloudlinux-selector set --json --interpreter nodejs \
    --domain "$DOMAIN" \
    --app-root "$APP_ROOT" \
    --startup-file server.js \
    --env-vars "$ENV_VARS" \
    --passenger-log-file "$PASSENGER_LOG_FILE"
}

if ! set_app; then
  create_app
fi

cloudlinux-selector restart --json --interpreter nodejs --domain "$DOMAIN" --app-root "$APP_ROOT" \
  || cloudlinux-selector start --json --interpreter nodejs --domain "$DOMAIN" --app-root "$APP_ROOT"
EOF
)

"${SSH_BASE[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "bash -s -- '$REMOTE_DOMAIN' '$REMOTE_APP_ROOT' '$REMOTE_APP_URI' '$NODE_VERSION' '$PASSENGER_LOG_FILE' '$ENV_VARS'" <<<"$REMOTE_CONFIG_SCRIPT"

echo "Waiting for hosted app to answer..."
for attempt in 1 2 3 4 5 6 7 8 9 10; do
  if curl -ksSf "https://${REMOTE_DOMAIN}/api/config" >/dev/null; then
    break
  fi
  if [[ "$attempt" -eq 10 ]]; then
    echo "ERROR: Hosted converter did not become healthy at https://${REMOTE_DOMAIN}/api/config" >&2
    "${SSH_BASE[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "tail -n 80 '$PASSENGER_LOG_FILE' 2>/dev/null || true"
    exit 5
  fi
  sleep 3
done

echo "Deployment complete: https://${REMOTE_DOMAIN}"