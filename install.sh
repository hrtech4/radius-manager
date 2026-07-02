#!/usr/bin/env bash
# ============================================================
# Simple RADIUS Manager - one-shot Ubuntu installer
# Run from inside the extracted project folder:
#   sudo bash install.sh
# ============================================================
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Please run as root: sudo bash install.sh"
  exit 1
fi

APP_DIR="/var/www/radius-manager"
DB_NAME="radius_manager"
DB_USER="radius_manager"
DB_PASS="$(openssl rand -hex 12)"
TEST_SECRET="testing123"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> [1/8] Installing packages (Apache, MySQL, PHP, FreeRADIUS)..."
export DEBIAN_FRONTEND=noninteractive
apt update -qq
apt install -y -qq apache2 mysql-server php php-mysql php-cli libapache2-mod-php unzip \
  freeradius freeradius-mysql freeradius-utils

echo "==> [2/8] Creating database and user..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> [3/8] Loading schema..."
mysql -u root "${DB_NAME}" < "${SCRIPT_DIR}/schema.sql"

echo "==> [4/8] Deploying app files to ${APP_DIR}..."
mkdir -p "${APP_DIR}"
cp -r "${SCRIPT_DIR}/." "${APP_DIR}/"
rm -f "${APP_DIR}/install.sh"

cat > "${APP_DIR}/config.php" <<PHP
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');
define('APP_NAME', 'Simple RADIUS Manager');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('UTC');
PHP

chown -R www-data:www-data "${APP_DIR}"

echo "==> [5/8] Configuring Apache site..."
cat > /etc/apache2/sites-available/radius-manager.conf <<APACHE
<VirtualHost *:80>
    ServerName radius.local
    DocumentRoot ${APP_DIR}
    <Directory ${APP_DIR}>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/radius-manager-error.log
    CustomLog \${APACHE_LOG_DIR}/radius-manager-access.log combined
</VirtualHost>
APACHE
a2ensite radius-manager.conf >/dev/null
a2dissite 000-default.conf >/dev/null 2>&1 || true
systemctl reload apache2

echo "==> [6/8] Pointing FreeRADIUS's SQL module at the database..."
systemctl stop freeradius || true

SQL_MOD="/etc/freeradius/3.0/mods-available/sql"
sed -i "s/^\(\s*driver\s*=\).*/\1 \"rlm_sql_mysql\"/" "$SQL_MOD"
sed -i "s/^\(\s*dialect\s*=\).*/\1 \"mysql\"/" "$SQL_MOD"
sed -i "s/^\(\s*server\s*=\).*/\1 \"localhost\"/" "$SQL_MOD"
sed -i "s/^\(\s*login\s*=\).*/\1 \"${DB_USER}\"/" "$SQL_MOD"
sed -i "s/^\(\s*password\s*=\).*/\1 \"${DB_PASS}\"/" "$SQL_MOD"
sed -i "s/^\(\s*radius_db\s*=\).*/\1 \"${DB_NAME}\"/" "$SQL_MOD"
sed -i "s/^\(\s*#\?\s*read_clients\s*=\).*/\1 yes/" "$SQL_MOD"
sed -i "s/^\(\s*#\?\s*client_table\s*=\).*/\1 \"nas\"/" "$SQL_MOD"

ln -sf "$SQL_MOD" /etc/freeradius/3.0/mods-enabled/sql
chown -R freerad:freerad /etc/freeradius/3.0/

echo "==> [7/8] Adding a local test NAS entry (127.0.0.1) for radtest..."
mysql -u root "${DB_NAME}" <<SQL
INSERT INTO nas (nasname, shortname, type, secret, description)
SELECT '127.0.0.1', 'localhost-test', 'other', '${TEST_SECRET}', 'Local radtest client'
WHERE NOT EXISTS (SELECT 1 FROM nas WHERE nasname = '127.0.0.1');
SQL

systemctl start freeradius
systemctl enable freeradius >/dev/null 2>&1

echo "==> [8/8] Opening firewall ports (if ufw is active)..."
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 'Apache Full' >/dev/null
  ufw allow 1812/udp >/dev/null
  ufw allow 1813/udp >/dev/null
fi

IP="$(hostname -I | awk '{print $1}')"

echo ""
echo "================================================================"
echo " Install complete!"
echo "================================================================"
echo " Web app:        http://${IP}/"
echo "   -> first visit walks you through creating an admin account"
echo ""
echo " Database name:   ${DB_NAME}"
echo " Database user:   ${DB_USER}"
echo " Database pass:   ${DB_PASS}"
echo "   -> also saved in ${APP_DIR}/config.php"
echo ""
echo " FreeRADIUS test client: 127.0.0.1, secret: ${TEST_SECRET}"
echo " Try it once you've added a PPPoE user in the web app:"
echo "   radtest <username> <password> 127.0.0.1 0 ${TEST_SECRET}"
echo ""
echo " Next: add your real router on the NAS/Routers page, then point"
echo " it at this server on UDP 1812/1813 with a matching secret."
echo "================================================================"
