#!/bin/bash
# ============================================================================
# SQLite -> MySQL conversion script
# Run from your Laravel project root (where "artisan" lives).
# Requires: the `mysql` CLI client installed, and a MySQL server reachable
# with the credentials below. Requires TransferSqliteToMysql.php to already
# be placed in app/Console/Commands/.
# ============================================================================

set -e  # stop immediately if any step fails

echo "== 0. Ensuring the transfer command is in place =="
if [ ! -f "app/Console/Commands/TransferSqliteToMysql.php" ]; then
  if [ -f "TransferSqliteToMysql.php" ]; then
    mkdir -p app/Console/Commands
    cp TransferSqliteToMysql.php app/Console/Commands/TransferSqliteToMysql.php
    echo "Copied TransferSqliteToMysql.php into app/Console/Commands/"
  else
    echo "ERROR: app/Console/Commands/TransferSqliteToMysql.php is missing, and no"
    echo "TransferSqliteToMysql.php was found in the current folder to copy from."
    echo "Place TransferSqliteToMysql.php next to this script, or in app/Console/Commands/, and re-run."
    exit 1
  fi
fi
composer dump-autoload > /dev/null
php artisan optimize:clear > /dev/null
if ! php artisan list | grep -q "app:transfer-sqlite-to-mysql"; then
  echo "ERROR: Laravel still does not recognize db:transfer-sqlite-to-mysql."
  echo "Check that the class namespace inside the file is exactly 'App\Console\Commands'."
  exit 1
fi
echo "Command registered OK."

# ---- EDIT THESE BEFORE RUNNING ----
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3306"
MYSQL_DATABASE="ai_product_assistant"
MYSQL_USERNAME="root"
MYSQL_PASSWORD=""
# ------------------------------------

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "== 1. Backing up current .env =="
cp .env ".env.backup_${TIMESTAMP}"
echo "Saved as .env.backup_${TIMESTAMP}"

echo "== 2. Backing up current SQLite database file (if present) =="
SQLITE_BACKUP=""
if [ -f "database/database.sqlite" ]; then
  SQLITE_BACKUP="database/database.sqlite.backup_${TIMESTAMP}"
  cp database/database.sqlite "${SQLITE_BACKUP}"
  echo "Saved as ${SQLITE_BACKUP}"
else
  echo "No database/database.sqlite file found — nothing to back up or transfer later."
fi

echo "== 3. Updating .env for MySQL =="
sed -i.bak '/^DB_CONNECTION=/d;/^DB_HOST=/d;/^DB_PORT=/d;/^DB_DATABASE=/d;/^DB_USERNAME=/d;/^DB_PASSWORD=/d' .env
rm -f .env.bak

cat >> .env << EOF

DB_CONNECTION=mysql
DB_HOST=${MYSQL_HOST}
DB_PORT=${MYSQL_PORT}
DB_DATABASE=${MYSQL_DATABASE}
DB_USERNAME=${MYSQL_USERNAME}
DB_PASSWORD=${MYSQL_PASSWORD}
EOF

echo "== 4. Creating the MySQL database if it doesn't already exist =="
mysql -h"${MYSQL_HOST}" -P"${MYSQL_PORT}" -u"${MYSQL_USERNAME}" -p"${MYSQL_PASSWORD}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "== 5. Clearing cached config so Laravel picks up the new .env =="
php artisan config:clear

echo "== 6. Running migrations fresh against MySQL =="
php artisan migrate:fresh --force

echo "== 7. Transferring data from the old SQLite backup into MySQL =="
if [ -n "${SQLITE_BACKUP}" ]; then
  php artisan app:transfer-sqlite-to-mysql --sqlite-path="${SQLITE_BACKUP}"
else
  echo "No prior SQLite data to transfer. Fresh empty MySQL schema is ready."
fi

echo "============================================================"
echo "Done."
echo "MySQL is now the active connection (DB_CONNECTION=mysql in .env)."
echo "Backups kept:"
echo "  - .env.backup_${TIMESTAMP}"
if [ -n "${SQLITE_BACKUP}" ]; then
  echo "  - ${SQLITE_BACKUP}"
fi
echo "============================================================"