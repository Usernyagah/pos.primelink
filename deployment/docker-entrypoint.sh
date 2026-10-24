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

if [ ! -e "$conf_file" ] || [ "$(wc -c < "$conf_file")" -le 8 ]; then
	cat > "$conf_file" <<'EOF'
<?php
$dolibarr_main_url_root='';
$dolibarr_main_document_root='/var/www/html';
$dolibarr_main_data_root='/var/www/documents';
EOF
fi

chown www-data:www-data "$conf_file"
chmod u+rw "$conf_file"

exec docker-php-entrypoint "$@"
