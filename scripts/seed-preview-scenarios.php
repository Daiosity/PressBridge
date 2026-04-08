<?php
/**
 * Seed repeatable preview scenarios into the Local PressBridge site.
 *
 * This creates:
 * - a published page used as the canonical preview route
 * - a revision-style preview source with changed content
 * - one valid preview token transient
 * - one expired preview token transient
 */

function pressbridge_preview_connect_local_db() {
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

	return $mysqli;
}

function pressbridge_preview_get_home_url( mysqli $mysqli ) {
	$site_url_result = $mysqli->query( "SELECT option_value FROM wp_options WHERE option_name = 'home' LIMIT 1" );
	$home_url        = 'http://wp-to-react.local';

	if ( $site_url_result instanceof mysqli_result ) {
		$row = $site_url_result->fetch_assoc();
		if ( isset( $row['option_value'] ) ) {
			$home_url = rtrim( (string) $row['option_value'], '/' );
		}
		$site_url_result->free();
	}

	return $home_url;
}

function pressbridge_preview_upsert_page( mysqli $mysqli, array $page, $home_url ) {
	$select_stmt = $mysqli->prepare( "SELECT ID FROM wp_posts WHERE post_type = 'page' AND post_name = ? LIMIT 1" );
	$insert_stmt = $mysqli->prepare(
		"INSERT INTO wp_posts (
			post_author, post_date, post_date_gmt, post_content, post_title,
			post_excerpt, post_status, comment_status, ping_status, post_password,
			post_name, to_ping, pinged, post_modified, post_modified_gmt,
			post_content_filtered, post_parent, guid, menu_order, post_type,
			post_mime_type, comment_count
		) VALUES (1, ?, ?, ?, ?, ?, ?, 'closed', 'closed', '', ?, '', '', ?, ?, '', 0, ?, 0, 'page', '', 0)"
	);
	$update_stmt = $mysqli->prepare(
		"UPDATE wp_posts SET
			post_date = ?, post_date_gmt = ?, post_content = ?, post_title = ?,
			post_excerpt = ?, post_status = ?, comment_status = 'closed',
			ping_status = 'closed', post_modified = ?, post_modified_gmt = ?, guid = ?
		WHERE ID = ?"
	);

	$slug    = $page['post_name'];
	$title   = $page['post_title'];
	$excerpt = $page['post_excerpt'];
	$content = $page['post_content'];
	$status  = $page['post_status'];
	$guid    = $home_url . '/' . $slug . '/';
	$now     = gmdate( 'Y-m-d H:i:s' );

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
		$update_stmt->bind_param( 'sssssssssi', $now, $now, $content, $title, $excerpt, $status, $now, $now, $guid, $existing_id );
		$update_stmt->execute();
		$post_id = $existing_id;
	} else {
		$insert_stmt->bind_param( 'ssssssssss', $now, $now, $content, $title, $excerpt, $status, $slug, $now, $now, $guid );
		$insert_stmt->execute();
		$post_id = (int) $mysqli->insert_id;
	}

	$select_stmt->close();
	$insert_stmt->close();
	$update_stmt->close();

	if ( ! $post_id ) {
		fwrite( STDERR, "Failed to create or update preview page {$slug}: {$mysqli->error}\n" );
		exit( 1 );
	}

	return $post_id;
}

function pressbridge_preview_upsert_revision( mysqli $mysqli, $post_id ) {
	$select_stmt = $mysqli->prepare(
		"SELECT ID FROM wp_posts WHERE post_type = 'revision' AND post_parent = ? AND post_name = ? LIMIT 1"
	);
	$insert_stmt = $mysqli->prepare(
		"INSERT INTO wp_posts (
			post_author, post_date, post_date_gmt, post_content, post_title,
			post_excerpt, post_status, comment_status, ping_status, post_password,
			post_name, to_ping, pinged, post_modified, post_modified_gmt,
			post_content_filtered, post_parent, guid, menu_order, post_type,
			post_mime_type, comment_count
		) VALUES (1, ?, ?, ?, ?, '', 'inherit', 'closed', 'closed', '', ?, '', '', ?, ?, '', ?, ?, 0, 'revision', '', 0)"
	);
	$update_stmt = $mysqli->prepare(
		"UPDATE wp_posts SET
			post_date = ?, post_date_gmt = ?, post_content = ?, post_title = ?,
			post_status = 'inherit', post_modified = ?, post_modified_gmt = ?, guid = ?
		WHERE ID = ?"
	);

	$revision_slug    = $post_id . '-autosave-v1';
	$revision_title   = 'PB Preview Scenario Draft';
	$revision_content = <<<'HTML'
<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"},"blockGap":"16px"},"color":{"background":"#eef6ff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#eef6ff;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Preview content is coming from the autosave snapshot</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This content only exists in the preview seed revision. The published page should keep its normal content unless a valid preview token is present.</p>
<!-- /wp:paragraph -->
<!-- /wp:group -->
HTML;
	$guid             = 'http://wp-to-react.local/?p=' . $post_id . '&preview=true';
	$now              = gmdate( 'Y-m-d H:i:s' );

	$select_stmt->bind_param( 'is', $post_id, $revision_slug );
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
		$update_stmt->bind_param( 'sssssssi', $now, $now, $revision_content, $revision_title, $now, $now, $guid, $existing_id );
		$update_stmt->execute();
		$revision_id = $existing_id;
	} else {
		$insert_stmt->bind_param( 'sssssssis', $now, $now, $revision_content, $revision_title, $revision_slug, $now, $now, $post_id, $guid );
		$insert_stmt->execute();
		$revision_id = (int) $mysqli->insert_id;
	}

	$select_stmt->close();
	$insert_stmt->close();
	$update_stmt->close();

	if ( ! $revision_id ) {
		fwrite( STDERR, "Failed to create or update preview revision: {$mysqli->error}\n" );
		exit( 1 );
	}

	return $revision_id;
}

function pressbridge_preview_upsert_transient( mysqli $mysqli, $token, array $payload, $expires_at ) {
	$key_hash      = md5( $token );
	$value_name    = '_transient_wtr_preview_' . $key_hash;
	$timeout_name  = '_transient_timeout_wtr_preview_' . $key_hash;
	$payload_value = serialize( $payload );

	$stmt = $mysqli->prepare(
		"INSERT INTO wp_options (option_name, option_value, autoload)
		 VALUES (?, ?, 'off')
		 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'off'"
	);

	$stmt->bind_param( 'ss', $value_name, $payload_value );
	$stmt->execute();

	$timeout_value = (string) $expires_at;
	$stmt->bind_param( 'ss', $timeout_name, $timeout_value );
	$stmt->execute();
	$stmt->close();
}

$mysqli   = pressbridge_preview_connect_local_db();
$home_url = pressbridge_preview_get_home_url( $mysqli );

$published_page_id = pressbridge_preview_upsert_page(
	$mysqli,
	array(
		'post_title'   => 'PB Preview Scenario',
		'post_name'    => 'pb-preview-scenario',
		'post_excerpt' => 'Published preview scenario page.',
		'post_status'  => 'publish',
		'post_content' => '<p>This is the published content for the preview scenario route.</p>',
	),
	$home_url
);

$draft_page_id = pressbridge_preview_upsert_page(
	$mysqli,
	array(
		'post_title'   => 'PB Preview Draft Only',
		'post_name'    => 'pb-preview-draft-only',
		'post_excerpt' => 'Draft-only route for preview validation.',
		'post_status'  => 'draft',
		'post_content' => '<p>This draft route should not resolve publicly, but it should resolve through preview when a valid token is present.</p>',
	),
	$home_url
);

$revision_id = pressbridge_preview_upsert_revision( $mysqli, $published_page_id );

$valid_token   = 'pbpreviewvalidtoken001';
$expired_token = 'pbpreviewexpiredtoken001';
$now           = time();

pressbridge_preview_upsert_transient(
	$mysqli,
	$valid_token,
	array(
		'post_id'     => $published_page_id,
		'revision_id' => $revision_id,
		'user_id'     => 1,
		'created_at'  => $now,
	),
	$now + 900
);

pressbridge_preview_upsert_transient(
	$mysqli,
	$expired_token,
	array(
		'post_id'     => $published_page_id,
		'revision_id' => $revision_id,
		'user_id'     => 1,
		'created_at'  => $now - 1800,
	),
	$now - 60
);

$mysqli->close();

echo json_encode(
	array(
		'published_page' => array(
			'id'   => $published_page_id,
			'path' => '/pb-preview-scenario/',
			'url'  => $home_url . '/pb-preview-scenario/',
		),
		'draft_page'     => array(
			'id'   => $draft_page_id,
			'path' => '/pb-preview-draft-only/',
			'url'  => $home_url . '/pb-preview-draft-only/',
		),
		'preview_tokens' => array(
			'valid'   => $valid_token,
			'expired' => $expired_token,
		),
		'frontend_preview_url' => 'http://localhost:5173/pb-preview-scenario/?wtr_preview=1&wtr_preview_token=' . $valid_token,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
