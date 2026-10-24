#!/bin/sh
set -eu

conf_dir=/var/www/html/conf
conf_file="${conf_dir}/conf.php"

chown www-data:www-data "$conf_dir"
chmod u+rwx "$conf_dir"

if [ -e "$conf_file" ] && [ ! -f "$conf_file" ]; then
	echo "ERROR: $conf_file exists but is not a regular file." >&2
	exit 1
fi

if [ ! -e "$conf_file" ]; then
	install -o www-data -g www-data -m 0640 /dev/null "$conf_file"
else
	chown www-data:www-data "$conf_file"
	chmod u+rw "$conf_file"
fi

exec docker-php-entrypoint "$@"
