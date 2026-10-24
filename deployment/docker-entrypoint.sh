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
$settings['DOLI_URL_ROOT'] = getenv('DOLI_URL_ROOT') ?: (getenv('RENDER_EXTERNAL_URL') ?: '');

$invalidSettings = array();
if (!in_array($settings['DOLI_DB_TYPE'], array('pgsql', 'mysqli'), true)) {
	$invalidSettings[] = 'DOLI_DB_TYPE (must be pgsql or mysqli)';
}
if (!ctype_digit($settings['DOLI_DB_PORT'])) {
	$invalidSettings[] = 'DOLI_DB_PORT (must contain digits only)';
}
if (!preg_match('/^[a-z0-9]+_$/i', $settings['DOLI_DB_PREFIX'])) {
	$invalidSettings[] = 'DOLI_DB_PREFIX (must be letters/digits followed by an underscore)';
}
if (!preg_match('/^[a-f0-9]{64}$/i', $settings['DOLI_INSTANCE_UNIQUE_ID'])) {
	$invalidSettings[] = 'DOLI_INSTANCE_UNIQUE_ID (must be 64 hexadecimal characters)';
}
if (!filter_var($settings['DOLI_URL_ROOT'], FILTER_VALIDATE_URL)) {
	$invalidSettings[] = 'DOLI_URL_ROOT (set it to the public HTTPS URL of this service)';
}
if (!empty($invalidSettings)) {
	file_put_contents('php://stderr', "ERROR: invalid Dolibarr environment settings: ".implode('; ', $invalidSettings).".\n");
	exit(1);
}
$settings['DOLI_URL_ROOT'] = rtrim($settings['DOLI_URL_ROOT'], '/');

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

$dataDir = '/var/www/documents';
if (!is_dir($dataDir)) {
	@mkdir($dataDir, 0775, true);
}
@chown($dataDir, 'www-data');
@chgrp($dataDir, 'www-data');

$lockFile = $dataDir.'/install.lock';
$forceLock = in_array(strtolower((string) getenv('DOLI_LOCK_INSTALL')), array('1', 'true', 'yes', 'on'), true);
$unlockUpgrade = in_array(strtolower((string) getenv('DOLI_UNLOCK_UPGRADE')), array('1', 'true', 'yes', 'on'), true);

if ($unlockUpgrade) {
	@file_put_contents($dataDir.'/upgrade.unlock', "Unlocked for upgrade by container entrypoint on ".date('c'));
	@chown($dataDir.'/upgrade.unlock', 'www-data');
}

$isInstalled = false;
if ($forceLock) {
	$isInstalled = true;
} else {
	try {
		$driver = $settings['DOLI_DB_TYPE'] === 'mysqli' ? 'mysql' : 'pgsql';
		$dsn = $driver.':host='.$settings['DOLI_DB_HOST'].';port='.$settings['DOLI_DB_PORT'].';dbname='.$settings['DOLI_DB_NAME'];
		$pdo = new PDO($dsn, $settings['DOLI_DB_USER'], $settings['DOLI_DB_PASSWORD'], array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
			PDO::ATTR_TIMEOUT => 5,
		));

		$checkTable = $settings['DOLI_DB_PREFIX'].'user';
		$stmt = $pdo->query("SELECT 1 FROM ".$checkTable." LIMIT 1");
		if ($stmt !== false) {
			$isInstalled = true;

			// Clean up stale MAIN_NOT_INSTALLED flag to prevent redirect loops
			$constTable = $settings['DOLI_DB_PREFIX'].'const';
			$pdo->exec("DELETE FROM ".$constTable." WHERE name = 'MAIN_NOT_INSTALLED'");
		}
	} catch (Throwable $e) {
		// Do not fail container startup if database check encounters an error
	}
}

if ($isInstalled && !file_exists($lockFile)) {
	if (file_put_contents($lockFile, "This is a lock file to prevent use of install pages (created by container entrypoint on ".date('c').")\n") !== false) {
		@chmod($lockFile, 0444);
		@chown($lockFile, 'www-data');
		@chgrp($lockFile, 'www-data');
	}
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

data_dir=/var/www/documents
mkdir -p "$data_dir"
chown www-data:www-data "$data_dir"
chmod 775 "$data_dir"

case "${DOLI_LOCK_INSTALL:-}" in
	1|true|TRUE|yes|YES|on|ON)
		lock_file="${data_dir}/install.lock"
		if [ ! -f "$lock_file" ]; then
			echo "Locked by container entrypoint" > "$lock_file"
			chmod 444 "$lock_file"
			chown www-data:www-data "$lock_file"
		fi
		;;
esac

chown www-data:www-data "$conf_file"
chmod u+rw "$conf_file"

exec docker-php-entrypoint "$@"
