# Preview Scenario Matrix

These scenarios form the lightweight preview-flow guardrail for PressBridge beta-readiness.

They are meant to prove the core bridge behavior around preview tokens, preview payload resolution, honest failure states, and normal public route safety. They do not attempt to prove full editor UI behavior or cross-domain browser quirks beyond what the local scenario set can actually validate.

Local validation keeps this scenario set fresh by reseeding the preview source content and tokens before preview checks run. That removes hidden dependence on whether a previously seeded token has silently expired.

## Scenario 1: Valid Preview Token

- Route:
  - `/wp-json/pressbridge/v1/preview/{token}`
- Seeded local setup:
  - published page at `/pb-preview-scenario/`
  - revision-style preview source with changed content
  - valid token `pbpreviewvalidtoken001`
- Expected behavior:
  - returns `200`
  - returns `is_preview: true`
  - returns `route_type: singular`
  - returns preview metadata
  - uses the revision snapshot content and reports `preview.source = autosave`
- Proves:
  - preview tokens can resolve to preview content
  - preview payloads stay distinct from normal public route resolution

## Scenario 2: Expired Preview Token

- Route:
  - `/wp-json/pressbridge/v1/preview/{token}`
- Seeded local setup:
  - expired token `pbpreviewexpiredtoken001`
- Expected behavior:
  - returns `404`
  - returns code `wtr_preview_expired`
- Proves:
  - expired or missing preview tokens fail honestly

## Scenario 3: Missing / Unknown Preview Token

- Route:
  - `/wp-json/pressbridge/v1/preview/{token}`
- Local coverage:
  - request a well-formed but unseeded token value
- Expected behavior:
  - returns `404`
  - returns code `wtr_preview_expired`
- Proves:
  - unknown tokens do not silently resolve

## Scenario 4: Invalid Preview Token Format

- Route:
  - `/wp-json/pressbridge/v1/preview/invalid-token!`
- Local coverage:
  - request a token that violates the route token pattern
- Expected behavior:
  - returns route failure rather than resolving preview content
- Proves:
  - malformed preview input fails honestly

## Scenario 5: Preview Frontend URL Assumption

- Route:
  - `/pb-preview-scenario/?wtr_preview=1&wtr_preview_token={token}`
- Local coverage:
  - frontend route returns `200`
- Expected behavior:
  - frontend route remains reachable when the token is attached
- Proves:
  - local frontend preview URLs remain structurally valid

## Scenario 6: Normal Public Route Safety

- Routes:
  - `/pb-preview-scenario/`
  - `/pb-preview-draft-only/`
- Local coverage:
  - published preview source route
  - draft-only route
- Expected behavior:
  - published route resolves normally through `/resolve`
  - draft-only route does not resolve publicly
  - failure may surface as `wtr_route_not_found` or `wtr_route_not_public`, but it must fail honestly with `404`
- Proves:
  - preview setup does not change normal public route availability

## What This Matrix Makes Beta-Safe

- valid preview tokens resolving through the plugin
- honest expired / missing preview failures
- malformed preview input failing honestly
- normal public route handling remaining intact around preview-enabled content
- local frontend preview URL structure remaining compatible with the bridge
- repeatable local preview validation without manual token reseeding

## What Still Needs Caution

- real editor-triggered preview link generation through the wp-admin UI is not fully automated by this guardrail
- true cross-domain cookie/browser behavior is only lightly proven in local testing
- autosave and revision edge cases beyond the seeded scenario are still only partially trusted
- local preview reseeding assumes the repo is using Local with the `wp-to-react.local` site available and startable
