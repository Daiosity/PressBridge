# First-Time Onboarding

This is the shortest reliable path for a developer using PressBridge for the first time in the Local test setup.

## Assumptions

- WordPress is running through Local at `http://wp-to-react.local`
- the React starter runs at `http://localhost:5173`
- you are starting from the repo and need the plugin ZIP, not a preinstalled plugin

## First-Time Flow

1. Build the plugin ZIP:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-plugin.ps1
```

2. In WordPress admin, install the built `build/pressbridge-{version}.zip` file through `Plugins > Add New > Upload Plugin`.
3. Activate `PressBridge`.
4. Open `Settings > PressBridge`.
5. Set `Frontend app URL` to `http://localhost:5173`.
6. Leave route handling on `Keep WordPress rendering public pages`.
7. Start the Vite frontend:

```powershell
cd frontend-app
npm install
npm run dev
```

8. Confirm the bridge responds:
   - `http://wp-to-react.local/wp-json/pressbridge/v1/site`
   - `http://wp-to-react.local/wp-json/pressbridge/v1/resolve?path=/sample-page/`
   - `http://localhost:5173/`
9. Open a normal page route such as `http://localhost:5173/sample-page/`.
10. Edit or create a draft page in WordPress and click `Preview`.
11. Confirm the preview URL contains `wtr_preview_token` and the frontend shows preview state.
12. Only after routes and preview are working, switch route handling to redirect mode.

## Friction Points This Flow Assumes

- Local is already started and the hostname is still `wp-to-react.local`
- the developer knows the plugin ZIP is built from this repo, not downloaded elsewhere
- the Vite frontend uses the same local host naming as the docs
- preview should be tested before redirect mode is enabled

## What Is Still Manual

- installing the ZIP into WordPress
- activating the plugin in wp-admin
- saving settings
- confirming preview from the WordPress editor
- switching route mode after the frontend proves stable

## What Should Not Be Assumed

- that redirect mode is safe before preview is checked
- that `pressbridge.local` is still the local hostname
- that the exported starter is required for local repo development
