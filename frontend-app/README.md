# PressBridge Frontend

This frontend is wired to the Local test site at `http://wp-to-react.local`.

## Quick start

1. Install dependencies with `npm install`
2. Confirm `.env.local` points to `http://wp-to-react.local/wp-json/pressbridge/v1`
3. Start the dev server with `npm run dev`
4. Open `http://localhost:5173`
5. Once the app renders correctly, switch the plugin route mode to React redirect for full handoff testing

## Notes

- API base: `http://wp-to-react.local/wp-json/pressbridge/v1`
- WordPress site: `http://wp-to-react.local`
- Frontend URL configured in WordPress: `http://localhost:5173`

## Production hosting

Use static hosting for MVP and configure rewrites so every frontend route falls back to `index.html`.

## What this starter includes

- React Router route handling
- Path resolution against WordPress content
- Menu loading
- Generic public post type support through the plugin API
- Preview token support
- A small fetch layer for the plugin REST endpoints

## Current local setup

- WordPress plugin is active on the Local site
- The plugin REST endpoints are responding
- The site is currently in safe WordPress route mode, not full redirect mode
- You can switch to redirect mode after this frontend is running
