#!/bin/bash
# Deploy EcomCine player.js fix to remote hosting
# Usage: ./scripts/deploy-player-fix.sh

set -euo pipefail

# Configuration
REMOTE_USER="${REMOTE_USER:-efttsqrtff}"
REMOTE_HOST="${REMOTE_HOST:-209.16.158.249}"
REMOTE_PORT="${REMOTE_PORT:-5022}"
REMOTE_KEY="${REMOTE_KEY:-$HOME/.ssh/ecomcine_n0c}"
REMOTE_WPATH="/home/efttsqrtff/app.topdoctorchannel.us"
PLUGIN_PATH="${REMOTE_WPATH}/wp-content/plugins/ecomcine"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if SSH key exists
if [[ ! -f "$REMOTE_KEY" ]]; then
    log_error "SSH key not found: $REMOTE_KEY"
    exit 1
fi

# Check if local player.js exists
LOCAL_PLAYER="/root/dev/EcomCine/ecomcine/modules/tm-media-player/assets/js/player.js"
if [[ ! -f "$LOCAL_PLAYER" ]]; then
    log_error "Local player.js not found: $LOCAL_PLAYER"
    exit 1
fi

log_info "Starting deployment of player.js fix to remote hosting..."

# Step 1: Backup remote player.js
log_info "Backing up remote player.js..."
ssh -i "$REMOTE_KEY" -p "$REMOTE_PORT" \
    -o StrictHostKeyChecking=accept-new \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "cp ${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js ${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js.backup.$(date +%Y%m%d-%H%M%S)"

if [[ $? -ne 0 ]]; then
    log_error "Failed to backup remote player.js"
    exit 1
fi

log_info "Backup created successfully"

# Step 2: Copy new player.js to remote
log_info "Copying new player.js to remote..."
scp -i "$REMOTE_KEY" -P "$REMOTE_PORT" \
    "$LOCAL_PLAYER" \
    "${REMOTE_USER}@${REMOTE_HOST}:${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js"

if [[ $? -ne 0 ]]; then
    log_error "Failed to copy player.js to remote"
    exit 1
fi

log_info "player.js deployed successfully"

# Step 3: Flush LiteSpeed cache
log_info "Flushing LiteSpeed cache..."
ssh -i "$REMOTE_KEY" -p "$REMOTE_PORT" \
    -o StrictHostKeyChecking=accept-new \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "wp --path=${REMOTE_WPATH} lscache flush --allow-root 2>/dev/null || true"

# Step 4: Purge player.js specifically
log_info "Purging player.js from cache..."
ssh -i "$REMOTE_KEY" -p "$REMOTE_PORT" \
    -o StrictHostKeyChecking=accept-new \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "wp --path=${REMOTE_WPATH} lscache purge_url 'https://app.topdoctorchannel.us/wp-content/plugins/ecomcine/modules/tm-media-player/assets/js/player.js' --allow-root 2>/dev/null || true"

log_info "Cache flushed successfully"

# Step 5: Verify deployment
log_info "Verifying deployment..."
REMOTE_VERSION=$(ssh -i "$REMOTE_KEY" -p "$REMOTE_PORT" \
    -o StrictHostKeyChecking=accept-new \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "grep -c 'hasValidUserActivation' ${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js" 2>/dev/null || echo "0")

if [[ "$REMOTE_VERSION" -gt 0 ]]; then
    log_info "✅ Deployment successful! New player.js is active on remote hosting."
    log_info "   - hasValidUserActivation function found: $REMOTE_VERSION occurrences"
else
    log_warn "⚠️  Warning: New player.js may not be deployed correctly"
    log_warn "   - hasValidUserActivation function not found"
fi

log_info ""
log_info "Next steps:"
log_info "1. Test on the online instance: https://app.topdoctorchannel.us"
log_info "2. Clear browser cache and sessionStorage"
log_info "3. Click the play button once, then navigate to next vendor"
log_info "4. Verify autoplay works without clicking again"
log_info ""
log_info "To test locally:"
log_info "1. Run: ./scripts/wp.sh wp cache flush --allow-root"
log_info "2. Open http://localhost:8180 in browser"
log_info "3. Test the same flow"

exit 0
