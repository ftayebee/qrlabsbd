#!/bin/sh
# Resolves the stack's secrets, then runs the container's command. A value set in
# the environment wins; otherwise a random one is generated once and kept on the
# shared volume, so a deploy with no configuration ships no default credentials.
set -eu

secrets_dir="${QRIO_SECRETS_DIR:-/var/lib/qrio-secrets}"

ensure_secret() {
    file="$secrets_dir/$1"
    eval "value=\${$1:-}"
    if [ -n "$value" ]; then
        printf '%s' "$value" > "$file"
    elif [ ! -s "$file" ]; then
        php -r "$2" > "$file"
    fi
}

# Hex passwords stay safe to pass through shells and config files.
password='echo bin2hex(random_bytes(24));'
ensure_secret APP_KEY 'echo "base64:".base64_encode(random_bytes(32));'
ensure_secret MYSQL_PASSWORD "$password"
ensure_secret REDIS_PASSWORD "$password"

APP_KEY="$(cat "$secrets_dir/APP_KEY")"
DB_PASSWORD="$(cat "$secrets_dir/MYSQL_PASSWORD")"
REDIS_PASSWORD="$(cat "$secrets_dir/REDIS_PASSWORD")"
export APP_KEY DB_PASSWORD REDIS_PASSWORD

exec "$@"
