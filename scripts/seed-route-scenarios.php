<?php
/**
 * Seed repeatable route scenarios into the Local PressBridge site.
 *
 * This keeps route validation honest without changing front-page or posts-page
 * options. We seed only the missing hierarchical page cases that the default
 * Local WordPress content does not already provide.
 */

function pressbridge_route_seed_via_db( array $pages ) {
	$sites_file = getenv( 'APPDATA' ) . '/Local/sites.json';

	if ( ! file_exists( $sites_file ) ) {
		fwrite( STDERR, "Could not find Local sites.json at {$sites_file}\n" );
		exit( 1 );
	}

	$sites = json_decode( (string) file_get_contents( $sites_file ), true );

	if ( ! is_array( $sites ) ) {
		fwrite( STDERR, "Could not parse Local sites.json\n" );
		exit( 1 );
	}

	$site_config = null;

	foreach ( $sites as $site ) {
		if ( isset( $site['domain'] ) && 'wp-to-react.local' === $site['domain'] ) {
			$site_config = $site;
			break;
		}
	}

	if ( ! is_array( $site_config ) ) {
		fwrite( STDERR, "Could not find Local site config for wp-to-react.local\n" );
		exit( 1 );
	}

	$mysqli = mysqli_init();
	$port   = (int) ( $site_config['services']['mysql']['ports']['MYSQL'][0] ?? 0 );

	$connected = $mysqli->real_connect(
		'127.0.0.1',
		$site_config['mysql']['user'] ?? 'root',
		$site_config['mysql']['password'] ?? 'root',
		$site_config['mysql']['database'] ?? 'local',
		$port
	);

	if ( ! $connected ) {
		fwrite( STDERR, "Could not connect to Local MySQL on port {$port}: {$mysqli->connect_error}\n" );
		exit( 1 );
	}

	$mysqli->set_charset( 'utf8mb4' );

	$site_url_result = $mysqli->query( "SELECT option_value FROM wp_options WHERE option_name = 'home' LIMIT 1" );
	$home_url        = 'http://wp-to-react.local';

	if ( $site_url_result instanceof mysqli_result ) {
		$row = $site_url_result->fetch_assoc();
		if ( isset( $row['option_value'] ) ) {
			$home_url = rtrim( (string) $row['option_value'], '/' );
		}
		$site_url_result->free();
	}

	$select_stmt = $mysqli->prepare( "SELECT ID FROM wp_posts WHERE post_type = 'page' AND post_name = ? LIMIT 1" );
	$insert_stmt = $mysqli->prepare(
		"INSERT INTO wp_posts (
			post_author, post_date, post_date_gmt, post_content, post_title,
			post_excerpt, post_status, comment_status, ping_status, post_password,
			post_name, to_ping, pinged, post_modified, post_modified_gmt,
			post_content_filtered, post_parent, guid, menu_order, post_type,
			post_mime_type, comment_count
		) VALUES (1, ?, ?, ?, ?, ?, 'publish', 'closed', 'closed', '', ?, '', '', ?, ?, '', ?, ?, 0, 'page', '', 0)"
	);
	$update_stmt = $mysqli->prepare(
		"UPDATE wp_posts SET
			post_date = ?, post_date_gmt = ?, post_content = ?, post_title = ?,
			post_excerpt = ?, post_status = 'publish', comment_status = 'closed',
			ping_status = 'closed', post_parent = ?, post_modified = ?, post_modified_gmt = ?, guid = ?
		WHERE ID = ?"
	);

	if ( ! $select_stmt || ! $insert_stmt || ! $update_stmt ) {
		fwrite( STDERR, "Failed to prepare MySQL statements.\n" );
		exit( 1 );
	}

	$results = array();
	$now     = gmdate( 'Y-m-d H:i:s' );

	foreach ( $pages as $page ) {
		$slug      = $page['post_name'];
		$title     = $page['post_title'];
		$excerpt   = $page['post_excerpt'];
		$content   = $page['post_content'];
		$parent_id = isset( $page['post_parent'] ) ? (int) $page['post_parent'] : 0;
		$guid      = $home_url . '/' . ltrim( $page['path'], '/' );

		$select_stmt->bind_param( 's', $slug );
		$select_stmt->execute();
		$select_result = $select_stmt->get_result();
		$existing_id   = null;

		if ( $select_result instanceof mysqli_result ) {
			$row = $select_result->fetch_assoc();
			if ( isset( $row['ID'] ) ) {
				$existing_id = (int) $row['ID'];
			}
			$select_result->free();
		}

		if ( $existing_id ) {
			$update_stmt->bind_param( 'sssssisssi', $now, $now, $content, $title, $excerpt, $parent_id, $now, $now, $guid, $existing_id );
			$update_stmt->execute();
			$post_id = $existing_id;
		} else {
			$insert_stmt->bind_param( 'ssssssssis', $now, $now, $content, $title, $excerpt, $slug, $now, $now, $parent_id, $guid );
			$post_id = 0;
			$insert_stmt->execute();
			$post_id = (int) $mysqli->insert_id;
		}

		if ( ! $post_id ) {
			fwrite( STDERR, "Failed to create or update {$slug}: {$mysqli->error}\n" );
			exit( 1 );
		}

		$results[] = array(
			'id'    => $post_id,
			'title' => $title,
			'slug'  => $slug,
			'path'  => $page['path'],
			'url'   => $guid,
		);
	}

	$select_stmt->close();
	$insert_stmt->close();
	$update_stmt->close();
	$mysqli->close();

	return $results;
}

$parent_path = 'pb-route-parent/';
$child_path  = $parent_path . 'pb-route-child/';

$seed_pages = pressbridge_route_seed_via_db(
	array(
		array(
			'post_title'   => 'PB Route Parent',
			'post_name'    => 'pb-route-parent',
			'post_excerpt' => 'Parent page for hierarchical route validation.',
			'post_content' => '<p>This parent page exists so PressBridge can validate hierarchical page resolution safely.</p>',
			'post_parent'  => 0,
			'path'         => $parent_path,
		),
	)
);

$parent_id = (int) $seed_pages[0]['id'];

$seed_pages = array_merge(
	$seed_pages,
	pressbridge_route_seed_via_db(
		array(
			array(
				'post_title'   => 'PB Route Child',
				'post_name'    => 'pb-route-child',
				'post_excerpt' => 'Child page for hierarchical route validation.',
				'post_content' => '<p>This child page exists so PressBridge can validate nested hierarchical page resolution.</p>',
				'post_parent'  => $parent_id,
				'path'         => $child_path,
			),
		)
	)
);

echo json_encode(
	array(
		'created' => $seed_pages,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
