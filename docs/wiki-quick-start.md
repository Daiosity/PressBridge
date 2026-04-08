# Quick Start

1. Build or install the PressBridge plugin ZIP.
2. Activate the plugin in WordPress.
3. Open `Settings > PressBridge`.
4. Set the frontend URL to `http://localhost:5173`.
5. Run the local frontend.

For the main starter:

```powershell
cd frontend-app
npm install
npm run dev
```

For the lightweight local server:

```powershell
cd frontend-lite
python server.py
```

Then verify:

- `http://wp-to-react.local/wp-json/pressbridge/v1/site`
- `http://wp-to-react.local/wp-json/pressbridge/v1/resolve?path=/`
- `http://localhost:5173/`

For local demo and screenshot content:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\refresh-demo-content.ps1
```

For core validation:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\validate-core.ps1 -FrontendBase 'http://127.0.0.1:5173'
```

For a fuller first-run flow, see [first-time-onboarding.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\first-time-onboarding.md) and [local-dev.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\local-dev.md).
