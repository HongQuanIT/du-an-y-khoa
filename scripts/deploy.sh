#!/usr/bin/env bash
# =============================================================================
# medpro — Deploy Script cho aaPanel (Git Manager / Webhook)
# =============================================================================
# Cách dùng trên aaPanel:
#   1. Website → site → Conf → Git Manager → Script
#   2. Alias: Deploy_Script (không dùng khoảng trắng)
#   3. Dán nội dung file này (hoặc: bash /www/wwwroot/<site>/scripts/deploy.sh)
#   4. Repository → gắn Script → copy Webhook URL → thêm vào GitHub/GitLab
#
# Trước khi chạy lần đầu:
#   - Đổi SITE_PATH, BRANCH, PHP_BIN cho đúng server
#   - Site Run Directory = /public
#   - .env production đã có sẵn trong SITE_PATH (không commit vào git)
#   - SSH deploy key của aaPanel đã add vào GitHub/GitLab (Deploy Keys)
# =============================================================================

set -euo pipefail

# aaPanel webhook đôi khi không export HOME → lỗi với `set -u`
if [[ -z "${HOME:-}" ]]; then
  HOME="$(getent passwd "$(id -un)" 2>/dev/null | cut -d: -f6 || true)"
fi
export HOME="${HOME:-/root}"

# --------------------- Cấu hình (chỉnh theo server) --------------------------
SITE_PATH="${SITE_PATH:-/www/wwwroot/medpro.wpops.io}"
BRANCH="${BRANCH:-main}"
APP_USER="${APP_USER:-www}"

# PHP aaPanel — ví dụ: 84 = PHP 8.4, 83 = PHP 8.3
PHP_BIN="${PHP_BIN:-/www/server/php/84/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

# true = chạy npm ci && npm run build trên server
BUILD_ASSETS="${BUILD_ASSETS:-true}"

# true = migrate --force sau khi pull
RUN_MIGRATE="${RUN_MIGRATE:-true}"

# Supervisor program (để trống nếu chưa cấu hình)
HORIZON_PROGRAM="${HORIZON_PROGRAM:-medpro-horizon}"
REVERB_PROGRAM="${REVERB_PROGRAM:-medpro-reverb}"

# PHP-FPM service name trên aaPanel (thường php-fpm-84)
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php-fpm-84}"

MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-ops-bypass-token}"

# SSH key aaPanel dùng cho git (thường key của root khi clone qua panel)
# Để trống = dùng mặc định ~/.ssh/id_* của user chạy webhook
GIT_SSH_KEY="${GIT_SSH_KEY:-}"

# --------------------- Helpers -----------------------------------------------
log()  { echo "[deploy $(date '+%Y-%m-%d %H:%M:%S')] $*"; }
fail() { echo "[deploy ERROR] $*" >&2; exit 1; }

run_as() {
  # Artisan / composer / npm: chạy dưới www để đúng quyền runtime
  if [[ "$(id -un)" == "$APP_USER" ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo -u "$APP_USER" -H "$@"
  else
    fail "Cần chạy bằng user ${APP_USER} hoặc có sudo"
  fi
}

artisan() {
  run_as "$PHP_BIN" artisan "$@"
}

# Git phải chạy bằng user webhook (thường root) — aaPanel đã cấu hình SSH key ở đó.
# sudo -u www git fetch sẽ lỗi "Host key verification failed" vì www không có known_hosts/key.
# -c safe.directory=* : tránh "dubious ownership" khi root git trên repo thuộc www.
run_git() {
  local ssh_dir="${HOME}/.ssh"
  local ssh_cmd="ssh -o UserKnownHostsFile=${ssh_dir}/known_hosts -o StrictHostKeyChecking=accept-new"
  if [[ -n "$GIT_SSH_KEY" ]]; then
    ssh_cmd="${ssh_cmd} -i ${GIT_SSH_KEY} -o IdentitiesOnly=yes"
  fi

  local -a git_cmd=(git -c safe.directory="$SITE_PATH" -c safe.directory='*')
  GIT_SSH_COMMAND="$ssh_cmd" "${git_cmd[@]}" "$@"
}

ensure_remote_host_key() {
  local remote_url host
  local ssh_dir="${HOME}/.ssh"

  mkdir -p "$ssh_dir"
  chmod 700 "$ssh_dir"
  touch "${ssh_dir}/known_hosts"
  chmod 600 "${ssh_dir}/known_hosts"

  remote_url="$(run_git remote get-url origin 2>/dev/null || true)"
  [[ -n "$remote_url" ]] || return 0

  # Chỉ xử lý SSH remotes: git@host:owner/repo.git
  if [[ "$remote_url" =~ ^git@([^:]+): ]]; then
    host="${BASH_REMATCH[1]}"
  elif [[ "$remote_url" =~ ^ssh://([^@]+@)?([^:/]+) ]]; then
    host="${BASH_REMATCH[2]}"
  else
    log "Remote không dùng SSH (${remote_url}) — bỏ qua ssh-keyscan"
    return 0
  fi

  if ! grep -qE "(^|,)${host}(,|\\s)" "${ssh_dir}/known_hosts" 2>/dev/null; then
    log "Thêm host key cho ${host} vào ${ssh_dir}/known_hosts"
    ssh-keyscan -T 5 -t rsa,ecdsa,ed25519 "$host" >> "${ssh_dir}/known_hosts" 2>/dev/null \
      || log "Cảnh báo: ssh-keyscan ${host} thất bại — kiểm tra mạng/DNS"
  else
    log "Host key ${host} đã có trong known_hosts"
  fi
}

bring_up_on_error() {
  local code=$?
  log "Deploy thất bại (exit=${code}) — tắt maintenance mode"
  cd "$SITE_PATH" 2>/dev/null && artisan up || true
  exit "$code"
}

# --------------------- Preflight ---------------------------------------------
log "========== medpro deploy start =========="
log "SITE_PATH=${SITE_PATH} BRANCH=${BRANCH} APP_USER=${APP_USER} RUN_AS=$(id -un)"

[[ -d "$SITE_PATH" ]] || fail "Không tìm thấy SITE_PATH: ${SITE_PATH}"
[[ -x "$PHP_BIN" ]] || fail "PHP_BIN không tồn tại/executable: ${PHP_BIN}"
[[ -f "$SITE_PATH/.env" ]] || fail "Thiếu .env trong ${SITE_PATH} — tạo trước khi deploy"
[[ -d "$SITE_PATH/.git" ]] || fail "Chưa phải git repo: ${SITE_PATH}"

cd "$SITE_PATH" || fail "Không cd được vào ${SITE_PATH}"

trap bring_up_on_error ERR

# --------------------- Maintenance -------------------------------------------
log "Bật maintenance mode"
artisan down --retry=60 --secret="$MAINTENANCE_SECRET" || true

# --------------------- Git sync (aaPanel style) ------------------------------
log "Chuẩn bị SSH known_hosts cho remote origin"
ensure_remote_host_key

# root chạy git trên repo thuộc www → Git ≥2.35 báo "dubious ownership"
# Vừa set global (bền) vừa truyền -c trên mọi lệnh (run_git) để chắc chắn.
log "Đánh dấu safe.directory cho ${SITE_PATH}"
git config --global --add safe.directory "$SITE_PATH" 2>/dev/null || true
git config --global --add safe.directory '*' 2>/dev/null || true

log "Git fetch + reset cứng về origin/${BRANCH}"
# Giữ .env / storage / vendor / node_modules / public/build
run_git fetch --prune origin
run_git checkout "$BRANCH"
run_git reset --hard "origin/${BRANCH}"
run_git clean -fd \
  -e '.env' \
  -e '.env.*' \
  -e 'storage/' \
  -e 'vendor/' \
  -e 'node_modules/' \
  -e 'public/build/' \
  -e 'public/storage'

log "Commit đang chạy: $(run_git rev-parse --short HEAD) — $(run_git log -1 --pretty=%s)"

# Đồng bộ quyền sau khi root pull (tránh file root-owned)
if [[ "$(id -un)" == "root" ]]; then
  chown -R "${APP_USER}:${APP_USER}" "$SITE_PATH"
  # Giữ .env chỉ root/www đọc được
  chmod 600 "$SITE_PATH/.env" || true
fi

# --------------------- Dependencies ------------------------------------------
log "Composer install (no-dev)"
if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  run_as "$COMPOSER_BIN" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist
else
  if [[ -f "$SITE_PATH/composer.phar" ]]; then
    run_as "$PHP_BIN" "$SITE_PATH/composer.phar" install \
      --no-dev --optimize-autoloader --no-interaction --prefer-dist
  else
    fail "Không tìm thấy composer. Cài composer hoặc set COMPOSER_BIN"
  fi
fi

if [[ "$BUILD_ASSETS" == "true" ]]; then
  # vite nằm trong devDependencies → phải npm ci ĐỦ (không --omit=dev), build xong mới prune
  log "Build frontend assets (npm ci && npm run build)"
  command -v "$NPM_BIN" >/dev/null 2>&1 || fail "Không tìm thấy npm — cài Node 20 hoặc set BUILD_ASSETS=false"
  run_as "$NPM_BIN" ci
  run_as "$NPM_BIN" run build
  run_as "$NPM_BIN" prune --omit=dev
else
  log "Bỏ qua build assets (BUILD_ASSETS=false)"
fi

# --------------------- Laravel -----------------------------------------------
log "storage:link (idempotent)"
artisan storage:link || true

if [[ "$RUN_MIGRATE" == "true" ]]; then
  log "Migrate --force"
  artisan migrate --force
else
  log "Bỏ qua migrate (RUN_MIGRATE=false)"
fi

log "Cache config / route / view / event"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

# --------------------- Permissions (aaPanel: www) ----------------------------
log "Chỉnh quyền storage & bootstrap/cache"
if [[ "$(id -un)" == "root" ]]; then
  chown -R "${APP_USER}:${APP_USER}" "$SITE_PATH"
  chmod -R ug+rwx "$SITE_PATH/storage" "$SITE_PATH/bootstrap/cache"
  chmod 600 "$SITE_PATH/.env" || true
else
  chmod -R ug+rwx "$SITE_PATH/storage" "$SITE_PATH/bootstrap/cache" || true
fi

# --------------------- Workers / OPcache -------------------------------------
log "Restart queue workers (Horizon)"
artisan horizon:terminate || true

if command -v supervisorctl >/dev/null 2>&1; then
  if [[ -n "$HORIZON_PROGRAM" ]]; then
    supervisorctl restart "$HORIZON_PROGRAM" >/dev/null 2>&1 \
      || log "Cảnh báo: không restart được ${HORIZON_PROGRAM}"
  fi
  if [[ -n "$REVERB_PROGRAM" ]]; then
    supervisorctl restart "$REVERB_PROGRAM" >/dev/null 2>&1 \
      || log "Cảnh báo: không restart được ${REVERB_PROGRAM}"
  fi
else
  log "supervisorctl không có — bỏ qua restart Horizon/Reverb"
fi

if command -v systemctl >/dev/null 2>&1 && [[ -n "$PHP_FPM_SERVICE" ]]; then
  log "Reload PHP-FPM (${PHP_FPM_SERVICE})"
  systemctl reload "$PHP_FPM_SERVICE" >/dev/null 2>&1 \
    || log "Cảnh báo: không reload được ${PHP_FPM_SERVICE} — reload tay trên aaPanel"
fi

# --------------------- Up ----------------------------------------------------
trap - ERR
log "Tắt maintenance mode"
artisan up

log "========== Deploy OK: $(run_git rev-parse --short HEAD) =========="
