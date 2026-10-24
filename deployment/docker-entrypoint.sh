#!/bin/sh
set -eu

chown www-data:www-data /var/www/html/conf
chmod u+rwx /var/www/html/conf

if [ -f /var/www/html/conf/conf.php ]; then
	chown www-data:www-data /var/www/html/conf/conf.php
	chmod u+rw /var/www/html/conf/conf.php
fi

exec docker-php-entrypoint "$@"
