# PressBridge

PressBridge connects WordPress to modern frontends.

It is a WordPress plugin that keeps WordPress as the CMS and editorial backend while handing routing, previews, and frontend delivery over to a React app or another modern frontend.

This repo contains both product layers:

- `WordPress plugin`
  - settings UI, REST bridge, route handoff logic, preview token flow, starter export
- `React frontends`
  - a Vite frontend for normal development
  - a lightweight smoke-test frontend
  - the starter template that ships from the plugin

PressBridge is not a WordPress theme replacement. WordPress still handles themes normally. PressBridge adds a bridge plugin plus an optional frontend starter.

## Quick Start

### Local WordPress

1. Start the Local site at `http://wp-to-react.local`.
2. Confirm `PressBridge` is active in WordPress.
3. Go to `Settings > PressBridge`.
4. Set the frontend URL to `http://localhost:5173`.
5. Keep route handling in WordPress mode until the frontend is resolving routes correctly.

### Local frontend

For the proper React dev flow:

```powershell
cd frontend-app
npm install
npm run dev
```

For a fast smoke-test frontend:

```powershell
cd frontend-lite
python server.py
```

Once the frontend is reachable, test:

- `http://wp-to-react.local/wp-json/pressbridge/v1/site`
- `http://wp-to-react.local/wp-json/pressbridge/v1/resolve?path=/`
- `http://localhost:5173/`

## Current Alpha Capabilities

- Headless mode toggle with safe fallback behavior
- Configurable frontend app URL
- Public redirect handoff for logged-out visitors
- Safe bypass for `wp-admin`, login, REST, AJAX, cron, and logged-in editors
- Custom REST namespace for site config, menus, pages, posts, generic items, content by slug, route resolution, and previews
- Normalized content payloads for React
- Path-based route resolution for singular and archive routes
- Signed preview token flow for cross-domain frontend previews
- Gutenberg preview guidance
- `View in React` shortcuts for logged-in users
- Exportable React starter package

## Repo Structure

```text
pressbridge.php
readme.txt
uninstall.php
includes/
assets/
templates/
frontend-app/
frontend-lite/
docs/
scripts/
```

### Plugin directories

- `includes/Core`
  - bootstrap, settings model, activation/deactivation, shared path helper
- `includes/Api`
  - REST router and controllers
- `includes/Data`
  - content mapping, menu normalization, supported post types
- `includes/Frontend`
  - public handoff logic and preview token service
- `includes/Admin`
  - settings screen, starter export, preview guidance, React view links
- `templates`
  - admin settings page template
- `assets`
  - editor helper JS and exported starter frontend assets

### Frontend directories

- `frontend-app`
  - Vite-based React frontend for proper local/frontend development
- `frontend-lite`
  - minimal no-build local test frontend used for quick live checks in the current workspace
- `assets/starter`
  - starter frontend template exported by the plugin

## How The Bridge Works

WordPress remains responsible for:

- content management
- publishing workflows
- previews
- permalink truth
- menus and public content types

PressBridge adds:

- a React-friendly REST namespace
- safe public handoff
- route resolution
- preview tokens
- normalized content payloads

The frontend should ask PressBridge what a route means instead of guessing. For published routes, it should call:

- `/wp-json/pressbridge/v1/resolve?path=/some-path/`

For preview routes, it should call:

- `/wp-json/pressbridge/v1/preview/{token}`

## Architecture Summary

### WordPress side

WordPress remains the content and editorial system:

- pages
- posts
- public custom post types
- menus
- previews
- publishing workflows

The plugin adds:

- plugin settings
- bridge-specific REST endpoints
- content normalization
- frontend handoff logic
- preview token generation and validation

### React side

The React frontend handles:

- routing
- page and archive rendering
- header and footer presentation
- preview rendering
- API failure and empty-state handling

The frontend consumes the plugin endpoints instead of calling WordPress template functions directly.

## Local Development Flow

### WordPress

1. Run a local WordPress site.
2. Install or copy the plugin into `wp-content/plugins/`.
3. Activate `PressBridge`.
4. Open `Settings > PressBridge`.
5. Set the frontend URL, for example `http://localhost:5173`.
6. Keep route handling in WordPress mode while integrating.

### React frontend

#### Fast local demo

Use `frontend-lite` when you want a quick no-build local frontend:

- open `frontend-lite`
- serve it locally
- point the plugin frontend URL at that server

#### Vite frontend

Use `frontend-app` when you want the real React dev flow:

1. install dependencies
2. run the Vite dev server
3. point the plugin frontend URL to the Vite URL
4. keep WordPress in safe rendering mode until the frontend resolves real routes cleanly

### Recommended integration sequence

1. Get the plugin endpoints working in WordPress.
2. Run the React frontend locally.
3. Confirm pages, posts, archives, and previews render correctly.
4. Only then switch public route handling to redirect mode.

## Packaging

Build the plugin ZIP with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-plugin.ps1
```

Expected output:

- `build/pressbridge-0.2.0.zip`

You can rebuild the package any time after PHP, admin, or starter-template changes. The packaging script only includes the plugin payload, not the local test site or frontend workspaces.

## Known Limitations

- No ACF integration yet
- No WooCommerce integration yet
- No authenticated frontend/session bridge yet
- No SSR or Next.js integration yet
- Menu support currently relies on WordPress menu data being available; the frontend falls back to page/post links when menus are absent
- `frontend-lite` is a convenience testing frontend, not the production-target frontend structure
- Gutenberg layout fidelity is improving, but PressBridge is still translating block intent into its own React design system rather than cloning the active theme pixel-for-pixel

## Review Notes

This alpha is intentionally conservative:

- it favors safe rollout over aggressive takeover
- it keeps editors on WordPress while they work
- it does not rewrite WordPress theme behavior
- it aims to be a bridge product, not a fragile replacement experiment

## Supporting Docs

- [MVP smoke test](docs/mvp-smoke-test.md)
- [Local development flow](docs/local-dev.md)
- [Release checklist](docs/release-checklist.md)
