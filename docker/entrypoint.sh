#!/bin/bash
set -euo pipefail

APP=/var/www/html

log() { echo "[sklad-cms] $*"; }

# ---------------------------------------------------------------------------
# 1. Настройки из переменных окружения
# ---------------------------------------------------------------------------
DB_HOST=${MYSQLHOST:-mysql}
DB_PORT=${MYSQLPORT:-3306}
DB_NAME=${MYSQLDATABASE:-sklad}
DB_USER=${MYSQLUSER:-root}
DB_PASS=${MYSQLPASSWORD:-}
SITE_URL=${SITE_URL:-http://localhost:8080}
SITE_STAGE=${SITE_STAGE:-dev}
APP_DEBUG=${APP_DEBUG:-true}

CONFIG_FILE="$APP/config/config.php"
if [ ! -f "$CONFIG_FILE" ]; then
  log "Создаю config.php из переменных окружения..."
  cat > "$CONFIG_FILE" <<PHP
<?php
return [
    'db' => [
        'host' => '$DB_HOST',
        'port' => $DB_PORT,
        'name' => '$DB_NAME',
        'user' => '$DB_USER',
        'pass' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'url' => '$SITE_URL',
        'theme' => '${SITE_THEME:-default}',
    ],
    'admin_path' => '/admin',
    'stage' => '$SITE_STAGE',
    'debug' => $APP_DEBUG,
];
PHP
  chown www-data:www-data "$CONFIG_FILE"
fi

# ---------------------------------------------------------------------------
# 2. Ожидание готовности базы данных
# ---------------------------------------------------------------------------
log "Проверяю подключение к базе $DB_HOST:$DB_PORT..."
for i in {1..30}; do
  if php "$APP/bin/test-db.php" >/dev/null 2>&1; then
    log "База данных доступна."
    break
  fi
  if [ "$i" -eq 30 ]; then
    log "ВНИМАНИЕ: База данных не ответила вовремя. Продолжаю запуск..."
    break
  fi
  sleep 1
done

# ---------------------------------------------------------------------------
# 3. Накатывание миграций
# ---------------------------------------------------------------------------
if php "$APP/bin/test-db.php" >/dev/null 2>&1; then
  log "Применяю миграции..."
  php "$APP/bin/migrate.php" || true

  if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    log "Создаю учётную запись администратора $ADMIN_EMAIL..."
    php "$APP/bin/migrate.php" --admin "$ADMIN_EMAIL" "$ADMIN_PASSWORD" "${ADMIN_NAME:-Администратор}" || true
  fi
fi

# ---------------------------------------------------------------------------
# 4. Проверка прав на папки
# ---------------------------------------------------------------------------
chown -R www-data:www-data "$APP/storage" "$APP/public/upload" "$APP/config" || true
chmod -R 775 "$APP/storage" "$APP/public/upload" || true

log "Запуск Apache на порту ${PORT:-8080}..."
exec "$@"
