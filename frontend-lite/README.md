# PressBridge Lite Frontend

This is a no-build React smoke-test frontend for the Local WordPress site.

## Run it

1. Open a terminal in this folder
2. Run `python server.py`
3. Open `http://localhost:5173`

## Why this exists

The machine does not currently have a usable Node.js toolchain on PATH, so this lets you test the WordPress handoff flow today while keeping the proper Vite app in `/frontend-app`.

## What to test

- Home page loads WordPress posts
- Sample page route resolves
- Plugin connection status is visible
- Public redirect mode can safely point WordPress to `http://localhost:5173`
