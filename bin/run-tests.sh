#!/bin/sh
#
# Run inside the wordpress_phpunit container to set up and execute tests.
# Usage: docker exec wordpress_phpunit sh /var/www/html/wp-content/plugins/wp-pdf-guard/bin/run-tests.sh
#

set -e

PLUGIN_DIR="/var/www/html/wp-content/plugins/wp-pdf-guard"
WP_TESTS_DIR="/tmp/wordpress-tests-lib"
DB_HOST="db:3306"
DB_USER="wp_user"
DB_PASS="wp_pass"
TEST_DB="wpdfg_tests"
MYSQL_OPTS="--skip-ssl -h db -u root -prootpass"

echo "==> Creating test database..."
# Wait for MySQL to be ready
for i in $(seq 1 30); do
    if mysql ${MYSQL_OPTS} -e "SELECT 1" > /dev/null 2>&1; then
        break
    fi
    echo "Waiting for MySQL... ($i)"
    sleep 2
done

mysql ${MYSQL_OPTS} -e "CREATE DATABASE IF NOT EXISTS ${TEST_DB};"
mysql ${MYSQL_OPTS} -e "GRANT ALL ON ${TEST_DB}.* TO '${DB_USER}'@'%';"

echo "==> Installing WordPress test suite..."
if [ ! -f "${WP_TESTS_DIR}/includes/functions.php" ]; then
    mkdir -p "${WP_TESTS_DIR}"

    # Get WordPress version
    WP_VERSION=$(php -r "include '/var/www/html/wp-includes/version.php'; echo \$wp_version;")
    echo "WordPress version: ${WP_VERSION}"

    # Download test suite via GitHub zip (no svn needed)
    echo "Downloading test suite..."
    ARCHIVE_URL="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz"
    TMPDIR=$(mktemp -d)

    if curl -sL "${ARCHIVE_URL}" -o "${TMPDIR}/wp-tests.tar.gz" && [ -s "${TMPDIR}/wp-tests.tar.gz" ]; then
        echo "Using tag ${WP_VERSION}"
    else
        echo "Tag not found, using trunk..."
        curl -sL "https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz" -o "${TMPDIR}/wp-tests.tar.gz"
    fi

    tar -xzf "${TMPDIR}/wp-tests.tar.gz" -C "${TMPDIR}"
    EXTRACTED_DIR=$(find "${TMPDIR}" -maxdepth 1 -type d -name "wordpress-develop*" | head -1)

    cp -r "${EXTRACTED_DIR}/tests/phpunit/includes" "${WP_TESTS_DIR}/includes"
    cp -r "${EXTRACTED_DIR}/tests/phpunit/data" "${WP_TESTS_DIR}/data"

    rm -rf "${TMPDIR}"
fi

# Write wp-tests-config.php
cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<WPCONFIG
<?php
define( 'ABSPATH', '/var/www/html/' );
define( 'DB_NAME', '${TEST_DB}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', '${PLUGIN_DIR}/vendor/yoast/phpunit-polyfills' );
\$table_prefix = 'wptests_';
WPCONFIG

echo "==> Installing PHPUnit..."
if [ ! -f "${PLUGIN_DIR}/vendor/bin/phpunit" ]; then
    cd "${PLUGIN_DIR}"
    # Install composer if not present
    if ! command -v composer > /dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
    fi
    composer require --dev phpunit/phpunit ^9 yoast/phpunit-polyfills ^2 2>/dev/null || true
fi

echo "==> Running tests..."
cd "${PLUGIN_DIR}"
WP_TESTS_DIR="${WP_TESTS_DIR}" vendor/bin/phpunit "$@"
