# Lenviqa MVP Smoke Test

Use this checklist when validating the plugin inside a real WordPress install.

## Setup

1. Install and activate the plugin.
2. If testing the distributable, install from the generated ZIP rather than copying the source folder manually.
3. Visit `Settings > Lenviqa`.
4. Save a frontend URL such as `http://localhost:5173`.
5. Confirm the Connection Status panel updates as expected.
6. If using the repo's Local site, confirm `http://wp-to-react.local` is running before frontend checks.

## REST checks

1. Open the `site` endpoint and confirm it returns the configured frontend URL.
2. Open the `menus` endpoint and confirm menu locations are present.
3. Open the `pages` endpoint and confirm published pages are returned.
4. Open the `posts` endpoint and confirm published posts are returned.
5. Open the `content-types` endpoint and confirm public CPTs are listed.
6. Open the `items?type=page` endpoint and confirm collection results match `pages`.
7. Open the `resolve?path=/` endpoint and confirm the home route resolves correctly.

## Frontend behavior checks

1. With headless mode disabled, confirm the public site still renders through WordPress.
2. With headless mode enabled and route mode set to `Keep WordPress rendering public pages`, confirm no redirects happen.
3. With headless mode enabled and route mode set to redirect, confirm public GET requests redirect to the frontend URL.
4. While logged in as an editor or admin, confirm public requests do not redirect.
5. Confirm `wp-admin`, login, REST, AJAX, and cron behavior remain unaffected.

## Preview checks

1. Edit a page or post and click Preview.
2. Confirm the preview URL points to the configured frontend URL.
3. Confirm the preview includes a `wtr_preview_token` query string.
4. Confirm the React app can load preview content through the preview endpoint.
5. Confirm the preview banner clearly indicates preview mode and allows returning to the published route.

## Content mapping checks

1. Confirm mapped responses include `title`, `slug`, `path`, `content`, `excerpt`, `featured_image`, and `terms`.
2. Confirm nested pages resolve correctly by path.
3. Confirm a custom post type entry can be loaded through `content?type=your_type&slug=entry-slug`.
4. Confirm a hierarchical CPT can be loaded through a full nested path slug if applicable.
5. If testing Gutenberg-heavy pages, confirm the React frontend still renders the page structure coherently rather than falling back to a broken layout.
6. If testing shortcode-heavy pages, confirm the frontend uses the expected HTML compatibility path rather than a broken block render.
7. If testing WooCommerce shortcode routes, confirm the starter labels them as advanced compatibility routes rather than implying default starter parity.

## Starter export checks

1. Download the starter ZIP from the settings page.
2. Confirm `src/config/wp-config.json` is present in the archive.
3. Run `npm install` and `npm run dev`.
4. Confirm the starter can load menus and route-resolved content from WordPress.
5. Confirm the starter still feels generic and product-facing rather than shipping site-specific branding or content.
