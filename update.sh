#!/usr/bin/env bash
# Permanent deploy script for Aale ERP (PHP-FPM + Nginx Vite assets).
# Fail-fast: never declare success unless HTML→CSS chain is verified.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

APP_IMAGE="${APP_IMAGE:-aale-erp:latest}"
NGINX_IMAGE="${NGINX_IMAGE:-aale-erp-nginx:latest}"
COMPOSE="${COMPOSE:-docker compose}"
BASE_URL="${BASE_URL:-http://127.0.0.1}"
KEEP_PREVIOUS="${KEEP_PREVIOUS:-1}"

log()  { printf '\n[%s] %s\n' "$(date '+%F %T')" "$*"; }
die()  { printf '\n[ERROR] %s\n' "$*" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"; }

need docker
need curl
need grep
need sed

if ! $COMPOSE version >/dev/null 2>&1; then
  die "docker compose is required"
fi

if [[ ! -f .env ]]; then
  die ".env is missing — refusing to deploy"
fi

# Host dirs for bind mounts (created if missing; never wiped).
mkdir -p \
  storage/app/public \
  storage/logs \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

if ! docker network inspect aale_default >/dev/null 2>&1; then
  log "Creating docker network aale_default"
  docker network create aale_default >/dev/null
fi

PREV_APP_ID=""
PREV_NGINX_ID=""
if [[ "$KEEP_PREVIOUS" == "1" ]]; then
  PREV_APP_ID="$(docker inspect -f '{{.Id}}' aale-erp-app 2>/dev/null || true)"
  PREV_NGINX_ID="$(docker inspect -f '{{.Id}}' aale-erp-nginx 2>/dev/null || true)"
fi

log "1/8 git pull"
git pull --ff-only || die "git pull failed — aborting before rebuild"

log "2/8 build PHP app image (${APP_IMAGE})"
DOCKER_BUILDKIT=1 $COMPOSE build app || die "app image build failed"

log "3/8 build Nginx image with public/ from app (${NGINX_IMAGE})"
DOCKER_BUILDKIT=1 $COMPOSE build nginx || die "nginx image build failed (must copy public/build from app image)"

log "4/8 recreate containers (data volumes preserved)"
$COMPOSE up -d --force-recreate --remove-orphans || die "docker compose up failed"

log "5/8 wait for PHP-FPM + Nginx"
for i in $(seq 1 30); do
  if docker exec aale-erp-app php -v >/dev/null 2>&1 \
    && docker exec aale-erp-nginx nginx -t >/dev/null 2>&1; then
    break
  fi
  if [[ "$i" -eq 30 ]]; then
    die "containers did not become healthy in time"
  fi
  sleep 1
done

log "6/8 refresh Laravel caches inside app container"
docker exec aale-erp-app php artisan optimize:clear || die "optimize:clear failed"
docker exec aale-erp-app php artisan config:cache || die "config:cache failed"
docker exec aale-erp-app php artisan route:cache || die "route:cache failed"
docker exec aale-erp-app php artisan view:cache || die "view:cache failed"
# Ensure public disk link exists inside the app filesystem (uploads mount covers nginx).
docker exec aale-erp-app php artisan storage:link >/dev/null 2>&1 || true

log "7/8 verify Vite manifest parity (app ↔ nginx)"
APP_MANIFEST="$(docker exec aale-erp-app cat /var/www/html/public/build/manifest.json)" \
  || die "manifest missing inside aale-erp-app"
NGINX_MANIFEST="$(docker exec aale-erp-nginx cat /var/www/html/public/build/manifest.json)" \
  || die "manifest missing inside aale-erp-nginx"

if [[ "$APP_MANIFEST" != "$NGINX_MANIFEST" ]]; then
  die "public/build/manifest.json differs between app and nginx — CSS will break"
fi

CSS_REL="$(printf '%s' "$APP_MANIFEST" | grep -Eo 'assets/app-[A-Za-z0-9_-]+\.css' | head -1 || true)"
[[ -n "$CSS_REL" ]] || die "could not parse CSS asset path from manifest.json"
CSS_PATH="/build/${CSS_REL}"
log "Manifest CSS path: ${CSS_PATH}"

log "8/8 HTTP verification (HTML must reference hashed CSS; CSS must be valid)"
TMP_HTML="$(mktemp)"
TMP_CSS="$(mktemp)"
trap 'rm -f "$TMP_HTML" "$TMP_CSS"' EXIT

HTML_CODE="$(curl -sS -L --max-time 25 -o "$TMP_HTML" -w '%{http_code}' "$BASE_URL/" || true)"
[[ "$HTML_CODE" =~ ^2 ]] || die "homepage HTTP ${HTML_CODE} (expected 2xx) from ${BASE_URL}/"

if ! grep -q "$CSS_PATH" "$TMP_HTML"; then
  # Fallback: any vite-built css link present and matching manifest file name
  if ! grep -Eoq "/build/assets/app-[A-Za-z0-9_-]+\.css" "$TMP_HTML"; then
    die "homepage HTML has no /build/assets/app-*.css link — @vite/manifest not applied"
  fi
  HTML_CSS="$(grep -Eo "/build/assets/app-[A-Za-z0-9_-]+\.css" "$TMP_HTML" | head -1)"
  if [[ "$HTML_CSS" != "$CSS_PATH" ]]; then
    die "HTML references ${HTML_CSS} but manifest has ${CSS_PATH} — stale view/opcache or wrong public tree"
  fi
fi

CSS_HEADERS="$(curl -sS -D - -o "$TMP_CSS" --max-time 25 "${BASE_URL}${CSS_PATH}" || true)"
CSS_CODE="$(printf '%s' "$CSS_HEADERS" | awk 'NR==1 {print $2}')"
[[ "$CSS_CODE" == "200" ]] || die "CSS ${CSS_PATH} returned HTTP ${CSS_CODE}"

CONTENT_TYPE="$(printf '%s' "$CSS_HEADERS" | awk -F': ' 'tolower($1)=="content-type" {print $2}' | tr -d '\r' | head -1)"
if [[ -n "$CONTENT_TYPE" ]] && ! printf '%s' "$CONTENT_TYPE" | grep -qi 'text/css\|charset'; then
  log "WARNING: unexpected Content-Type for CSS: ${CONTENT_TYPE}"
fi

CSS_SIZE="$(wc -c < "$TMP_CSS" | tr -d ' ')"
[[ "$CSS_SIZE" -gt 1000 ]] || die "CSS body too small (${CSS_SIZE} bytes) — likely empty/wrong file"

if ! grep -Eq '(\.erp-shell|@tailwind|tailwind|font-family|--tw-|Vazirmatn)' "$TMP_CSS"; then
  die "CSS content does not look like the ERP/Tailwind bundle"
fi

# Confirm nginx container actually has the file (not a host bind ghost).
docker exec aale-erp-nginx test -f "/var/www/html/public${CSS_PATH}" \
  || die "CSS file missing inside nginx container at /var/www/html/public${CSS_PATH}"

log "SUCCESS — deploy verified"
log "  Homepage: ${BASE_URL}/  (HTTP ${HTML_CODE})"
log "  CSS:      ${BASE_URL}${CSS_PATH}  (HTTP 200, ${CSS_SIZE} bytes)"
log "  Manifest: identical in aale-erp-app and aale-erp-nginx"

if [[ -n "$PREV_APP_ID" || -n "$PREV_NGINX_ID" ]]; then
  log "Previous container IDs (for manual rollback if needed): app=${PREV_APP_ID:-none} nginx=${PREV_NGINX_ID:-none}"
fi

exit 0
