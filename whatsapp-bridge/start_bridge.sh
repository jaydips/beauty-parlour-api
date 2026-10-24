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

cd "$DIR" || exit 1
nohup "$NODE" "$DIR/server.js" >> "$DIR/bridge.log" 2>&1 &
echo "$(date): started bridge pid $! with $NODE" >> "$DIR/watchdog.log"
