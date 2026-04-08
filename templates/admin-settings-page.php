<?php
/**
 * Settings page template.
 *
 * @package WP_To_React
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'PressBridge', 'pressbridge' ); ?></h1>
	<p><?php esc_html_e( 'Connect WordPress to modern frontends.', 'pressbridge' ); ?></p>

	<?php settings_errors( \WP_To_React\Core\Settings::OPTION_NAME ); ?>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:20px 0;">
		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'First-Time Setup', 'pressbridge' ); ?></h2>
			<ol style="margin:0 0 0 18px;display:grid;gap:8px;">
				<li><?php esc_html_e( 'Set your frontend URL, for example http://localhost:5173.', 'pressbridge' ); ?></li>
				<li><?php esc_html_e( 'Keep route handling on WordPress mode while you integrate.', 'pressbridge' ); ?></li>
				<li><?php esc_html_e( 'Start the React starter and confirm the site and resolve endpoints return JSON.', 'pressbridge' ); ?></li>
				<li><?php esc_html_e( 'Preview a draft before enabling redirect mode.', 'pressbridge' ); ?></li>
			</ol>
			<p class="description" style="margin-top:12px;"><?php esc_html_e( 'PressBridge is safest when rollout happens in this order: connect the frontend, verify routes, verify preview, then enable redirect handoff.', 'pressbridge' ); ?></p>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Current Status', 'pressbridge' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Headless mode:', 'pressbridge' ); ?></strong>
				<?php echo ! empty( $settings['headless_mode'] ) ? esc_html__( 'Enabled', 'pressbridge' ) : esc_html__( 'Disabled', 'pressbridge' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Frontend URL:', 'pressbridge' ); ?></strong>
				<?php echo ! empty( $settings['frontend_url'] ) ? esc_html( $settings['frontend_url'] ) : esc_html__( 'Not configured', 'pressbridge' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Route mode:', 'pressbridge' ); ?></strong>
				<?php echo esc_html( $this->settings->get_route_modes()[ $settings['route_handling_mode'] ] ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Supported content types:', 'pressbridge' ); ?></strong>
				<?php echo esc_html( (string) count( $post_types ) ); ?>
			</p>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Recommended Rollout', 'pressbridge' ); ?></h2>
			<p><?php esc_html_e( 'Start with WordPress still rendering public pages while your frontend consumes the PressBridge endpoints. Switch on public redirect handoff only after the frontend can resolve real routes cleanly.', 'pressbridge' ); ?></p>
			<p><?php esc_html_e( 'Editors and admins stay on safe WordPress routes while they work, so rollout can be gradual without disrupting content teams.', 'pressbridge' ); ?></p>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Preview Workflow', 'pressbridge' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Preview links open in the configured frontend and use signed tokens that expire after %d minutes.', 'pressbridge' ), $preview_ttl_minutes ) ); ?></p>
			<p><?php esc_html_e( 'The frontend can call the preview endpoint directly, show a visible preview state, and let editors jump back to the published route when they are done reviewing changes.', 'pressbridge' ); ?></p>
		</div>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'wtr_settings_group' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wtr_headless_mode"><?php esc_html_e( 'Enable headless mode', 'pressbridge' ); ?></label>
				</th>
				<td>
					<label for="wtr_headless_mode">
						<input
							type="checkbox"
							id="wtr_headless_mode"
							name="<?php echo esc_attr( \WP_To_React\Core\Settings::OPTION_NAME ); ?>[headless_mode]"
							value="1"
							<?php checked( ! empty( $settings['headless_mode'] ) ); ?>
						/>
						<?php esc_html_e( 'Turn on PressBridge handoff features for this site.', 'pressbridge' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'This never affects wp-admin, login, AJAX, cron, REST requests, or editor sessions that still need WordPress.', 'pressbridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wtr_frontend_url"><?php esc_html_e( 'Frontend app URL', 'pressbridge' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						class="regular-text"
						id="wtr_frontend_url"
						name="<?php echo esc_attr( \WP_To_React\Core\Settings::OPTION_NAME ); ?>[frontend_url]"
						value="<?php echo esc_attr( $settings['frontend_url'] ); ?>"
						placeholder="http://localhost:5173"
					/>
					<p class="description"><?php esc_html_e( 'Examples: http://localhost:5173 or https://frontend.example.com', 'pressbridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wtr_route_mode"><?php esc_html_e( 'Route handling mode', 'pressbridge' ); ?></label>
				</th>
				<td>
					<select
						id="wtr_route_mode"
						name="<?php echo esc_attr( \WP_To_React\Core\Settings::OPTION_NAME ); ?>[route_handling_mode]"
					>
						<?php foreach ( $this->settings->get_route_modes() as $mode => $label ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $settings['route_handling_mode'], $mode ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Use redirect mode only when your frontend is ready to own public routes. Keep WordPress rendering public pages while you integrate and test.', 'pressbridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wtr_enable_debug"><?php esc_html_e( 'Expose debug metadata', 'pressbridge' ); ?></label>
				</th>
				<td>
					<label for="wtr_enable_debug">
						<input
							type="checkbox"
							id="wtr_enable_debug"
							name="<?php echo esc_attr( \WP_To_React\Core\Settings::OPTION_NAME ); ?>[enable_debug]"
							value="1"
							<?php checked( ! empty( $settings['enable_debug'] ) ); ?>
						/>
						<?php esc_html_e( 'Include extra integration diagnostics in the site config endpoint.', 'pressbridge' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Useful during setup. Leave off on production unless you actively need it.', 'pressbridge' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'pressbridge' ) ); ?>
	</form>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:24px;">
		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Connection Status', 'pressbridge' ); ?></h2>
			<ul style="margin:0;display:grid;gap:12px;">
				<?php foreach ( $status_checks as $check ) : ?>
					<li style="margin:0;">
						<strong style="color:<?php echo $check['healthy'] ? esc_attr( '#0f6b34' ) : esc_attr( '#9a3412' ); ?>;">
							<?php echo $check['healthy'] ? esc_html__( 'Ready', 'pressbridge' ) : esc_html__( 'Needs attention', 'pressbridge' ); ?>
						</strong>
						<?php echo esc_html( ' - ' . $check['label'] ); ?>
						<div class="description" style="margin-top:4px;"><?php echo esc_html( $check['detail'] ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Bridge Endpoints', 'pressbridge' ); ?></h2>
			<p><?php esc_html_e( 'These are the core endpoints a connected frontend can consume immediately.', 'pressbridge' ); ?></p>
			<ul style="margin-left:18px;list-style:disc;">
				<?php foreach ( $endpoints as $label => $url ) : ?>
					<li>
						<strong><?php echo esc_html( ucfirst( $label ) ); ?>:</strong>
						<code><?php echo esc_html( $url ); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Supported Content Types', 'pressbridge' ); ?></h2>
			<p><?php esc_html_e( 'The bridge supports public, publicly queryable post types. Pages and posts have dedicated endpoints, and additional content types can use the generic items and content routes.', 'pressbridge' ); ?></p>
			<ul style="margin-left:18px;list-style:disc;">
				<?php foreach ( $post_types as $post_type ) : ?>
					<li>
						<strong><?php echo esc_html( $post_type['label'] ); ?></strong>
						<code><?php echo esc_html( $post_type['name'] ); ?></code>
						<?php if ( ! empty( $post_type['hierarchical'] ) ) : ?>
							<?php esc_html_e( '(hierarchical)', 'pressbridge' ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'Use /items?type=your_post_type for collections and /content?type=your_post_type&slug=entry-slug for single entries.', 'pressbridge' ); ?></p>
		</div>

		<div class="card" style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Frontend Starter Export', 'pressbridge' ); ?></h2>
			<p><?php esc_html_e( 'The starter export gives developers a working React frontend with the bridge already wired in. It is the quickest way to move from WordPress content to a real frontend rendering layer.', 'pressbridge' ); ?></p>
			<?php if ( $download_supported ) : ?>
				<p><a href="<?php echo esc_url( $download_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Download Starter App ZIP', 'pressbridge' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Starter ZIP export is unavailable because ZipArchive is missing on this server.', 'pressbridge' ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'The exported package includes Vite, React Router, API helpers, route resolution, preview token support, and a starter site shell.', 'pressbridge' ); ?></p>
		</div>
	</div>
</div>
