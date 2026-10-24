#!/usr/bin/env bash
# deploy/deploy-cpanel.sh - deploy a release zip to cPanel shared hosting through
# the cPanel API only (no SSH). Used by GitHub Actions and runnable locally (Git Bash).
#
# Required environment variables (GitHub secrets in CI):
#   CPANEL_HOST    e.g. jainamagrifood.com        (port 2083, valid TLS certificate)
#   CPANEL_USER    cPanel account user name
#   CPANEL_TOKEN   cPanel API token (Manage API Tokens)
#   CPANEL_HOME    e.g. /home/<user>
#   APP_URL        public base URL of the API, no trailing slash, e.g. https://example.com/api
#   INSTALL_KEY    value of install.key in the server .env (>= 32 chars)
# Optional (document-root sync; skipped when DOCROOT is empty):
#   DOCROOT        absolute path of the document root, e.g. /home/<user>/public_html/api
#   REWRITE_BASE   RewriteBase for public/.htaccess, e.g. /api/  (default: /)
#   PATHS_RELATIVE path from the docroot to the app folder, e.g. ../../beauty-parlour-api (default)
# Argument: path to the release zip (built by deploy/build-release.ps1 or make-zip.php).
#
# Steps: upload zip -> extract over CPANEL_HOME/beauty-parlour-api -> delete zip
#        -> (optional) write docroot files -> run /install/migrate -> check /health
set -euo pipefail

# Git Bash (MSYS) rewrites arguments that look like POSIX paths (/home/user -> C:/Program Files/Git/home/user)
# before handing them to native programs such as curl. Server paths must reach cPanel untouched.
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'

ZIP="${1:?usage: deploy-cpanel.sh <release.zip>}"
: "${CPANEL_HOST:?}" "${CPANEL_USER:?}" "${CPANEL_TOKEN:?}" "${CPANEL_HOME:?}" "${APP_URL:?}" "${INSTALL_KEY:?}"
REWRITE_BASE="${REWRITE_BASE:-/}"
PATHS_RELATIVE="${PATHS_RELATIVE:-../../beauty-parlour-api}"
APP_DIR="$CPANEL_HOME/beauty-parlour-api"
API="https://$CPANEL_HOST:2083"
ZIP_NAME="$(basename "$ZIP")"
HERE="$(cd "$(dirname "$0")" && pwd)"

# Local file paths handed to curl: on Git Bash (Windows) curl needs Windows-style paths.
lp() { if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else printf '%s' "$1"; fi; }

# The token travels only in a header file that is removed on exit (never on the command line).
HDR="$(mktemp)"
printf 'Authorization: cpanel %s:%s' "$CPANEL_USER" "$CPANEL_TOKEN" > "$HDR"
trap 'rm -f "$HDR"' EXIT

json_get() { # json_get '<json>' '<php expression on $j>'  (JSON goes through stdin: API 2 responses can be large)
  printf '%s' "$1" | php -r '$j = json_decode(stream_get_contents(STDIN), true); $v = eval("return " . $argv[1] . ";"); echo is_scalar($v) ? $v : json_encode($v);' "$2"
}
uapi()  { curl -sS --fail-with-body --max-time 300 -H "@$(lp "$HDR")" "$@"; }

echo "== 1/6 upload $ZIP_NAME to $CPANEL_HOME"
RES="$(uapi -F "dir=$CPANEL_HOME" -F 'overwrite=1' -F "file-0=@$(lp "$ZIP");filename=$ZIP_NAME" "$API/execute/Fileman/upload_files")"
[ "$(json_get "$RES" '$j["status"] ?? $j["result"]["status"] ?? 0')" = "1" ] || { echo "upload failed: $RES" >&2; exit 1; }

echo "== 2/6 extract on server (API 2 fileop extract, destfiles=$CPANEL_HOME)"
RES="$(curl -sS --fail-with-body --max-time 600 -H "@$(lp "$HDR")" -X POST \
  --data-urlencode "cpanel_jsonapi_user=$CPANEL_USER" --data-urlencode 'cpanel_jsonapi_apiversion=2' \
  --data-urlencode 'cpanel_jsonapi_module=Fileman' --data-urlencode 'cpanel_jsonapi_func=fileop' \
  --data-urlencode 'op=extract' --data-urlencode "sourcefiles=$CPANEL_HOME/$ZIP_NAME" \
  --data-urlencode "destfiles=$CPANEL_HOME" --data-urlencode 'doubledecode=0' "$API/json-api/cpanel")"
[ "$(json_get "$RES" '$j["cpanelresult"]["data"][0]["result"] ?? 0')" = "1" ] || { echo "extract failed: ${RES:0:600}" >&2; exit 1; }

echo "== 3/6 remove zip from server"
RES="$(curl -sS --fail-with-body --max-time 120 -H "@$(lp "$HDR")" -X POST \
  --data-urlencode "cpanel_jsonapi_user=$CPANEL_USER" --data-urlencode 'cpanel_jsonapi_apiversion=2' \
  --data-urlencode 'cpanel_jsonapi_module=Fileman' --data-urlencode 'cpanel_jsonapi_func=fileop' \
  --data-urlencode 'op=unlink' --data-urlencode "sourcefiles=$CPANEL_HOME/$ZIP_NAME" --data-urlencode 'doubledecode=0' "$API/json-api/cpanel")"
[ "$(json_get "$RES" '$j["cpanelresult"]["data"][0]["result"] ?? 0')" = "1" ] || echo "warning: could not delete zip: ${RES:0:300}" >&2

if [ -n "${DOCROOT:-}" ]; then
  echo "== 4/6 sync document root $DOCROOT (RewriteBase $REWRITE_BASE, Paths $PATHS_RELATIVE)"
  # The docroot receives everything a browser may fetch: the front controller, .htaccess,
  # robots.txt, favicon.ico and the web UI assets (public/assets/*). They travel as one flat
  # zip that is extracted in place, so sub-folders such as assets/ are created as needed.
  STAGE="$(mktemp -d)"; trap 'rm -f "$HDR"; rm -rf "$STAGE"' EXIT
  mkdir -p "$STAGE/docroot"
  sed "s|^\t# RewriteBase /\$|\tRewriteBase $REWRITE_BASE|" "$HERE/../public/.htaccess" > "$STAGE/docroot/.htaccess"
  sed "s|require FCPATH \. '\.\./\.\./beauty-parlour-api/app/Config/Paths\.php';|require FCPATH . '$PATHS_RELATIVE/app/Config/Paths.php';|" "$HERE/docroot-index.php" > "$STAGE/docroot/index.php"
  cp "$HERE/../public/robots.txt" "$HERE/../public/favicon.ico" "$STAGE/docroot/"
  if [ -d "$HERE/../public/assets" ]; then
    mkdir -p "$STAGE/docroot/assets"
    cp -R "$HERE/../public/assets/." "$STAGE/docroot/assets/"
  fi
  DOCZIP_NAME="docroot-sync-$$.zip"
  php "$HERE/make-zip.php" "$STAGE" docroot "$STAGE/$DOCZIP_NAME" --flat
  RES="$(uapi -F "dir=$DOCROOT" -F 'overwrite=1' -F "file-0=@$(lp "$STAGE/$DOCZIP_NAME");filename=$DOCZIP_NAME" "$API/execute/Fileman/upload_files")"
  [ "$(json_get "$RES" '$j["status"] ?? $j["result"]["status"] ?? 0')" = "1" ] || { echo "docroot upload failed: $RES" >&2; exit 1; }
  RES="$(curl -sS --fail-with-body --max-time 300 -H "@$(lp "$HDR")" -X POST \
    --data-urlencode "cpanel_jsonapi_user=$CPANEL_USER" --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' --data-urlencode 'cpanel_jsonapi_func=fileop' \
    --data-urlencode 'op=extract' --data-urlencode "sourcefiles=$DOCROOT/$DOCZIP_NAME" \
    --data-urlencode "destfiles=$DOCROOT" --data-urlencode 'doubledecode=0' "$API/json-api/cpanel")"
  [ "$(json_get "$RES" '$j["cpanelresult"]["data"][0]["result"] ?? 0')" = "1" ] || { echo "docroot extract failed: ${RES:0:600}" >&2; exit 1; }
  RES="$(curl -sS --fail-with-body --max-time 120 -H "@$(lp "$HDR")" -X POST \
    --data-urlencode "cpanel_jsonapi_user=$CPANEL_USER" --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' --data-urlencode 'cpanel_jsonapi_func=fileop' \
    --data-urlencode 'op=unlink' --data-urlencode "sourcefiles=$DOCROOT/$DOCZIP_NAME" --data-urlencode 'doubledecode=0' "$API/json-api/cpanel")"
  [ "$(json_get "$RES" '$j["cpanelresult"]["data"][0]["result"] ?? 0')" = "1" ] || echo "warning: could not delete $DOCZIP_NAME from the docroot: ${RES:0:300}" >&2
else
  echo "== 4/6 document root sync skipped (DOCROOT not set)"
fi

echo "== 5/6 run migrations: $APP_URL/install/migrate"
RES="$(curl -sS --max-time 300 "$APP_URL/install/migrate?key=$INSTALL_KEY")"
[ "$(json_get "$RES" '$j["migrated"] ?? false')" = "1" ] || { echo "migrate failed: ${RES:0:600}" >&2; exit 1; }
echo "   applied: $(json_get "$RES" 'count($j["applied"])')  pending: $(json_get "$RES" 'count($j["pending"])')"

echo "== 6/6 health check: $APP_URL/health"
RES="$(curl -sS --max-time 60 "$APP_URL/health")"
[ "$(json_get "$RES" '$j["status"] ?? ""')" = "ok" ] || { echo "health check failed: $RES" >&2; exit 1; }
echo "   $RES"
echo "deploy complete"
