#!/bin/bash

# GLPI Plugin Permissions Fix Script
# This script safely sets permissions for GLPI plugins directory

set -e

# Configuration
TARGET="/var/www/glpi/plugins"
USER="www-data"
GROUP="www-data"
PERMISSIONS="755"

# Logging
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] $1"
}

error() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [ERROR] $1" >&2
    exit 1
}

# Validate target directory exists
if [ ! -d "$TARGET" ]; then
    error "Target directory does not exist: $TARGET"
fi

# Change ownership
log "Setting ownership to $USER:$GROUP for $TARGET"
if ! chown -R "$USER:$GROUP" "$TARGET"; then
    error "Failed to change ownership"
fi

# Change permissions
log "Setting permissions to $PERMISSIONS for $TARGET"
if ! chmod -R "$PERMISSIONS" "$TARGET"; then
    error "Failed to change permissions"
fi

log "Permissions fixed successfully"
