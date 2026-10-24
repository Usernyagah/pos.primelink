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

if [ -n "${DOLI_DB_HOST:-}${DOLI_DB_NAME:-}${DOLI_DB_USER:-}${DOLI_DB_PASSWORD:-}${DOLI_INSTANCE_UNIQUE_ID:-}" ]; then
	php <<'PHP'
<?php
$required = array('DOLI_DB_HOST', 'DOLI_DB_NAME', 'DOLI_DB_USER', 'DOLI_DB_PASSWORD', 'DOLI_INSTANCE_UNIQUE_ID');
$settings = array();
foreach ($required as $name) {
	$value = getenv($name);
	if ($value === false || $value === '') {
		file_put_contents('php://stderr', "ERROR: environment variable ".$name." is required when DOLI_DB_HOST is set.\n");
		exit(1);
	}
	$settings[$name] = $value;
}

$settings['DOLI_DB_TYPE'] = getenv('DOLI_DB_TYPE') ?: 'pgsql';
$settings['DOLI_DB_PREFIX'] = getenv('DOLI_DB_PREFIX') ?: 'llx_';
$settings['DOLI_DB_PORT'] = getenv('DOLI_DB_PORT') ?: ($settings['DOLI_DB_TYPE'] === 'mysqli' ? '3306' : '5432');
$settings['DOLI_URL_ROOT'] = getenv('DOLI_URL_ROOT') ?: '';

if (!in_array($settings['DOLI_DB_TYPE'], array('pgsql', 'mysqli'), true)
	|| !ctype_digit($settings['DOLI_DB_PORT'])
	|| !preg_match('/^[a-z0-9]+_$/i', $settings['DOLI_DB_PREFIX'])
	|| !preg_match('/^[a-f0-9]{64}$/i', $settings['DOLI_INSTANCE_UNIQUE_ID'])) {
	file_put_contents('php://stderr', "ERROR: invalid database type, port, prefix, or instance ID in Dolibarr environment settings.\n");
	exit(1);
}

$config = array(
	'dolibarr_main_url_root' => $settings['DOLI_URL_ROOT'],
	'dolibarr_main_document_root' => '/var/www/html',
	'dolibarr_main_data_root' => '/var/www/documents',
	'dolibarr_main_db_host' => $settings['DOLI_DB_HOST'],
	'dolibarr_main_db_port' => $settings['DOLI_DB_PORT'],
	'dolibarr_main_db_name' => $settings['DOLI_DB_NAME'],
	'dolibarr_main_db_prefix' => $settings['DOLI_DB_PREFIX'],
	'dolibarr_main_db_user' => $settings['DOLI_DB_USER'],
	'dolibarr_main_db_pass' => $settings['DOLI_DB_PASSWORD'],
	'dolibarr_main_db_type' => $settings['DOLI_DB_TYPE'],
	'dolibarr_main_authentication' => 'dolibarr',
	'dolibarr_main_instance_unique_id' => $settings['DOLI_INSTANCE_UNIQUE_ID'],
);

$contents = "<?php\n";
foreach ($config as $name => $value) {
	$contents .= '$'.$name.'='.var_export($value, true).";\n";
}

$temporaryFile = '/var/www/html/conf/conf.php.tmp';
if (file_put_contents($temporaryFile, $contents, LOCK_EX) === false || !rename($temporaryFile, '/var/www/html/conf/conf.php')) {
	file_put_contents('php://stderr', "ERROR: unable to write the Dolibarr configuration file.\n");
	exit(1);
}
PHP
elif [ ! -e "$conf_file" ] || [ "$(wc -c < "$conf_file")" -le 8 ]; then
	cat > "$conf_file" <<'EOF'
<?php
$dolibarr_main_url_root='';
$dolibarr_main_document_root='/var/www/html';
$dolibarr_main_data_root='/var/www/documents';
EOF
fi

if [ -f "${conf_file}.tmp" ]; then
	rm -f "${conf_file}.tmp"
fi

chown www-data:www-data "$conf_file"
chmod u+rw "$conf_file"

exec docker-php-entrypoint "$@"
