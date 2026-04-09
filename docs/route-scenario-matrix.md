# Route Scenario Matrix

These scenarios form the lightweight core route guardrail for Lenviqa beta-readiness.

They are meant to prove predictable bridge behavior on common and risky route cases. They do not attempt to prove every WordPress permalink variation or advanced content ecosystem.

## Scenario 1: Home Route

- Route: `/`
- Expected behavior:
  - resolves successfully
  - returns `route_type: archive` when the Local site is configured to show latest posts on the front page
- Local coverage:
  - uses the current Local `show_on_front = posts` configuration
- Beta-safe meaning:
  - Lenviqa can treat the root route as archive truth when WordPress is using a posts front page

## Scenario 2: Standard Page Route

- Route: `/sample-page/`
- Expected behavior:
  - resolves successfully
  - returns `route_type: singular`
  - resolves to the Sample Page content
- Local coverage:
  - uses the default Sample Page already in the Local site
- Beta-safe meaning:
  - normal published page routes remain safe

## Scenario 3: Nested Hierarchical Page Route

- Route: `/pb-route-parent/pb-route-child/`
- Expected behavior:
  - resolves successfully
  - returns `route_type: singular`
  - preserves the full hierarchical path
- Local coverage:
  - seeded by [seed-route-scenarios.php](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\seed-route-scenarios.php)
- Beta-safe meaning:
  - hierarchical page fallback resolution is working, not just flat page lookup

## Scenario 4: Post Route

- Route: `/hello-world/`
- Expected behavior:
  - resolves successfully
  - returns `route_type: singular`
  - resolves to the default Hello World post
- Local coverage:
  - uses the default Local WordPress post
- Beta-safe meaning:
  - normal post permalink resolution remains safe

## Scenario 5: Bad / Unresolved Route

- Route: `/does-not-exist/`
- Expected behavior:
  - returns a `404`
  - returns code `wtr_route_not_found`
- Local coverage:
  - explicit unresolved test route
- Beta-safe meaning:
  - bad paths fail honestly instead of resolving unpredictably

## Scenario 6: Path Normalization

- Routes:
  - `sample-page`
  - `//sample-page//`
  - `http://wp-to-react.local/sample-page/`
- Expected behavior:
  - each normalizes safely to `/sample-page/`
  - each resolves to the Sample Page singular route
- Local coverage:
  - explicit resolve endpoint checks
- Beta-safe meaning:
  - frontend callers do not need perfect slash formatting for basic routes

## Scenario 7: Archive-Style Route

- Route: `/`
- Expected behavior:
  - archive behavior already covered by the home route in the current Local config
- Local coverage:
  - same as Scenario 1
- Beta-safe meaning:
  - the Local guardrail proves archive routing in the common “posts on front page” case

## Scenario 8: Posts Page Route

- Route:
  - not configured in the current Local site
- Expected behavior:
  - should resolve as archive when `page_for_posts` is configured
- Local coverage:
  - not proven by the current Local scenario set
- Beta status:
  - partially trusted from implementation, not validated by this guardrail yet

## Scenario 9: CPT Single / CPT Archive

- Route:
  - not configured in the current Local site
- Expected behavior:
  - should resolve when a supported public CPT exists
- Local coverage:
  - not proven by the current Local scenario set because the Local site currently exposes only `post` and `page`
- Beta status:
  - partially trusted from implementation, not validated by this guardrail yet

## What This Matrix Makes Beta-Safe

- home/archive routing when the site front page shows posts
- normal published page resolution
- hierarchical page resolution
- normal published post resolution
- unresolved-route honesty
- basic path normalization

## What Still Needs Caution

- configured posts-page routing when `page_for_posts` is used
- CPT single and archive routing on real public CPTs
- unusual permalink setups not represented in the Local site
- theme/plugin-specific rewrites outside normal WordPress permalink behavior
