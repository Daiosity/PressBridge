<?php
/**
 * Local WordPress DB helper for development smoke tests.
 *
 * Usage examples:
 * php local-db-option.php get active_plugins
* php local-db-option.php activate-plugin lenviqa/pressbridge.php
 * php local-db-option.php set wtr_settings '{"headless_mode":true}'
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$host     = getenv( 'WTR_DB_HOST' ) ?: '127.0.0.1';
$port     = (int) ( getenv( 'WTR_DB_PORT' ) ?: 3306 );
$database = getenv( 'WTR_DB_NAME' ) ?: 'local';
$user     = getenv( 'WTR_DB_USER' ) ?: 'root';
$password = getenv( 'WTR_DB_PASSWORD' ) ?: 'root';
$prefix   = getenv( 'WTR_DB_PREFIX' ) ?: 'wp_';

$action = $argv[1] ?? '';
$key    = $argv[2] ?? '';
$value  = $argv[3] ?? '';

$mysqli = mysqli_init();

if ( ! mysqli_real_connect( $mysqli, $host, $user, $password, $database, $port ) ) {
	fwrite( STDERR, 'Connection failed: ' . mysqli_connect_error() . "\n" );
	exit( 1 );
}

$table = $prefix . 'options';

switch ( $action ) {
	case 'get':
		$stmt = $mysqli->prepare( "SELECT option_value FROM {$table} WHERE option_name = ?" );
		$stmt->bind_param( 's', $key );
		$stmt->execute();
		$stmt->bind_result( $option_value );
		if ( $stmt->fetch() ) {
			echo $option_value;
		}
		$stmt->close();
		exit( 0 );

	case 'find':
		$like = $key;
		$stmt = $mysqli->prepare( "SELECT option_name, option_value FROM {$table} WHERE option_name LIKE ?" );
		$stmt->bind_param( 's', $like );
		$stmt->execute();
		$result = $stmt->get_result();
		while ( $row = $result->fetch_assoc() ) {
			echo $row['option_name'] . '=' . $row['option_value'] . PHP_EOL;
		}
		$stmt->close();
		exit( 0 );

	case 'set':
		$autoload = 'yes';
		$stmt     = $mysqli->prepare( "INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)" );
		$stmt->bind_param( 'sss', $key, $value, $autoload );
		$stmt->execute();
		echo 'updated';
		$stmt->close();
		exit( 0 );

	case 'set-json':
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			fwrite( STDERR, "Value must decode to a JSON object or array.\n" );
			exit( 1 );
		}

		$serialized = serialize( $decoded );
		$autoload   = 'yes';
		$stmt       = $mysqli->prepare( "INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)" );
		$stmt->bind_param( 'sss', $key, $serialized, $autoload );
		$stmt->execute();
		echo $serialized;
		$stmt->close();
		exit( 0 );

	case 'set-base64':
		$decoded_value = base64_decode( $value, true );
		if ( false === $decoded_value ) {
			fwrite( STDERR, "Value must be valid base64.\n" );
			exit( 1 );
		}

		$autoload = 'yes';
		$stmt     = $mysqli->prepare( "INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)" );
		$stmt->bind_param( 'sss', $key, $decoded_value, $autoload );
		$stmt->execute();
		echo $decoded_value;
		$stmt->close();
		exit( 0 );

	case 'activate-plugin':
		$plugin = $key;
		if ( empty( $plugin ) ) {
			fwrite( STDERR, "Missing plugin slug.\n" );
			exit( 1 );
		}

		$stmt = $mysqli->prepare( "SELECT option_value FROM {$table} WHERE option_name = 'active_plugins'" );
		$stmt->execute();
		$stmt->bind_result( $active_plugins_value );
		$stmt->fetch();
		$stmt->close();

		$active_plugins = @unserialize( $active_plugins_value );
		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		if ( ! in_array( $plugin, $active_plugins, true ) ) {
			$active_plugins[] = $plugin;
			sort( $active_plugins );
		}

		$serialized = serialize( array_values( $active_plugins ) );
		$stmt       = $mysqli->prepare( "UPDATE {$table} SET option_value = ? WHERE option_name = 'active_plugins'" );
		$stmt->bind_param( 's', $serialized );
		$stmt->execute();
		$stmt->close();

		echo $serialized;
		exit( 0 );
}

fwrite( STDERR, "Unknown action.\n" );
exit( 1 );
