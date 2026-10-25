#!/bin/bash
# Watchdog for the beauty-parlour WhatsApp bridge. cPanel cron, every minute (no SSH on this host):
#   * * * * * /bin/bash /home/<user>/beauty-parlour-api/whatsapp-bridge/start_bridge.sh
# - starts server.js when it is not running (after a reboot, a crash or a deploy)
# - an empty file whatsapp-bridge/.restart makes it restart cleanly (the WhatsApp session is kept)
# - does nothing until .env.bridge exists (the bridge is not configured yet)
DIR="${BRIDGE_DIR:-$(cd "$(dirname "$0")" && pwd)}"
NODE="${NODE_BIN:-/opt/alt/alt-nodejs20/root/usr/bin/node}"
[ -x "$NODE" ] || NODE="$(command -v node)"
MATCH="$DIR/server.js"
ME="$(id -un)"

[ -f "$DIR/.env.bridge" ] || exit 0
set -a
. "$DIR/.env.bridge"
set +a
PORT="${PORT:-3310}"

if [ -f "$DIR/.restart" ]; then
  rm -f "$DIR/.restart"
  curl -s -m 5 -X POST -H "X-Bridge-Key: $BRIDGE_KEY" "http://127.0.0.1:$PORT/restart" >/dev/null 2>&1
  sleep 3
  pkill -u "$ME" -f "$MATCH" >/dev/null 2>&1
  sleep 1
  echo "$(date): restart flag found - bridge stopped" >> "$DIR/watchdog.log"
fi

pgrep -u "$ME" -f "$MATCH" >/dev/null && exit 0

# first run on a fresh host (or after package.json changed and node_modules was removed): install the
# dependencies here, so no one-off cron job is needed; a lock directory keeps two runs from colliding
if [ ! -d "$DIR/node_modules/baileys" ]; then
  NPM="${NPM_BIN:-/opt/alt/alt-nodejs20/root/usr/bin/npm}"
  [ -x "$NPM" ] || NPM="$(command -v npm)"
  if mkdir "$DIR/.npm-install.lock" 2>/dev/null; then
    echo "$(date): node_modules missing - running npm install" >> "$DIR/watchdog.log"
    (cd "$DIR" && PATH="$(dirname "$NODE"):$PATH" "$NPM" install --omit=dev --no-audit --no-fund >> "$DIR/watchdog.log" 2>&1)
    echo "$(date): npm install finished (exit $?)" >> "$DIR/watchdog.log"
    rmdir "$DIR/.npm-install.lock" 2>/dev/null
  else
    exit 0  # another run is installing right now
  fi
  [ -d "$DIR/node_modules/baileys" ] || exit 1
fi

cd "$DIR" || exit 1
nohup "$NODE" "$DIR/server.js" >> "$DIR/bridge.log" 2>&1 &
echo "$(date): started bridge pid $! with $NODE" >> "$DIR/watchdog.log"
