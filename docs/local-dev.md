# PressBridge Local Development

This repo is set up for a Local-based WordPress site plus two frontend workflows.

## WordPress runtime

Current assumptions:

- the bundled local test site lives in `live-test-website`
- it is run through Local, not a custom PHP server command
- the current Local site hostname in this repo is `http://wp-to-react.local`
- if you rename the Local site later, update the frontend config and plugin setting accordingly

Recommended WordPress checks before frontend work:

1. Start the site in Local.
2. Open `http://wp-to-react.local`.
3. Confirm `PressBridge` is active.
4. Open `Settings > PressBridge`.
5. Set the frontend URL you plan to test against.

Useful local API checks:

- `http://wp-to-react.local/wp-json/pressbridge/v1/site`
- `http://wp-to-react.local/wp-json/pressbridge/v1/pages`
- `http://wp-to-react.local/wp-json/pressbridge/v1/posts`
- `http://wp-to-react.local/wp-json/pressbridge/v1/resolve?path=/`

## Frontend options

### Vite app

Use `frontend-app` when you want the real React development flow:

```powershell
cd frontend-app
npm install
npm run dev
```

Expected URL:

- `http://localhost:5173`

Use this when:

- you are changing app code seriously
- you want the proper starter structure
- you want to verify the Vite-based frontend rather than the smoke frontend
- you want to test the generic PressBridge starter, not a custom site implementation

### Lightweight smoke frontend

Use `frontend-lite` for quick checks without a Node build:

```powershell
cd frontend-lite
python server.py
```

Expected URL:

- `http://127.0.0.1:5173`

The plugin frontend URL is usually still set to:

- `http://localhost:5173`

This is acceptable for local testing because browsers resolve `localhost` and `127.0.0.1` to the same machine.

The smoke frontend is intentionally simpler than the Vite app. It should reflect the bridge behavior clearly, but it is not the long-term starter structure.

## Recommended local workflow

1. Start WordPress in Local.
2. Confirm the PressBridge API is responding.
3. Start either `frontend-app` or `frontend-lite`.
4. Set the frontend URL in WordPress.
5. Keep route handling in WordPress mode until page, post, archive, and preview rendering are working.
6. Only then enable redirect handoff.

## Preview flow locally

1. Edit a page or post in WordPress.
2. Click Preview.
3. WordPress should generate a frontend URL containing `wtr_preview_token`.
4. The frontend should request `pressbridge/v1/preview/{token}` and show preview state.

If preview fails, check:

- the frontend URL in `Settings > PressBridge`
- whether the React frontend is reachable
- whether the PressBridge API namespace is responding

## Common local issues

### WordPress route works but frontend is blank

Check:

- frontend dev server is running
- browser console for fetch or script errors
- `site` and `resolve` endpoints respond with valid JSON

### Redirect mode seems broken while logged in

This is usually expected. PressBridge intentionally avoids redirecting logged-in editors and admins while they work.

### API route returns 404

Check:

- plugin is active
- namespace is `pressbridge/v1`
- permalinks and REST routing are functioning normally in WordPress

### Frontend content looks structurally right but not theme-identical

This is expected. PressBridge translates Gutenberg block intent into a React-side design system. It is not a pixel-perfect clone of the active WordPress theme.

### WooCommerce route looks different from the starter

This is also expected for now. WooCommerce compatibility is being treated as an advanced layer rather than part of the base starter promise, especially when cart and checkout flows live on a different origin from the frontend.
