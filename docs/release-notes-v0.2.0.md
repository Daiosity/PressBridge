# PressBridge v0.2.0 Release Notes

## Summary

PressBridge is a WordPress plugin and starter frontend setup for developers who want WordPress as the CMS and React as the public frontend.

This `v0.2.0` release is the first public beta-core release. The focus is not feature breadth. The focus is a smaller, validated bridge promise that developers can evaluate honestly.

## What Is Working In This Release

Validated core coverage in `v0.2.0` includes:

- route resolution for:
  - home route
  - standard page routes
  - nested hierarchical page routes
  - standard post routes
  - unresolved-route honesty
  - basic path normalization
- Gutenberg-aware rendering for common layout patterns:
  - nested groups
  - columns inside groups
  - media-text recovery from saved markup
  - cover sections with inner content
  - gallery fallback from saved markup
  - button-group layout intent
- preview-flow foundations:
  - valid preview token resolution
  - honest invalid/expired/malformed preview failure
  - preview content staying separate from normal public routing
- packaging/install/runtime confidence:
  - repeatable ZIP packaging
  - package structure validation
  - activation defaults
  - starter export hook presence
  - deactivation and uninstall checks

## What Is Beta-Safe

The beta-safe boundary for this release is defined in [beta-scope.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\beta-scope.md).

That means the current release is best treated as:

- a practical WordPress-to-React bridge with validated routing and preview foundations
- a Gutenberg-aware starter with safe fallback behavior for common layout patterns
- a package/install flow that has repeatable validation support

## Intentionally Out Of Scope

This release does not promise:

- WooCommerce as a solved core feature
- ACF integration
- Elementor compatibility
- theme-specific CSS fidelity
- pixel-perfect Gutenberg parity with the active WordPress theme
- support for third-party/custom block ecosystems
- support for interactive blocks that require WordPress frontend JS

## Known Limitations

- `frontend-lite` is a smoke frontend, not a full parity frontend
- preview validation is strongest in the Local scenario set, not all hosting/browser environments
- package/install validation reduces risk, but some wp-admin and hosting-specific behaviors remain manual
- Gutenberg rendering aims to preserve layout intent, not clone the active WordPress theme

## Who Should Try This Beta

This beta is a good fit for:

- developers evaluating WordPress + React without building the bridge layer from scratch
- agencies testing a safer WordPress backend + React frontend baseline
- teams who care about route truth, preview flow, and a starter they can inspect and extend

This beta is not the right release for teams expecting a finished compatibility layer for commerce, builders, or custom block ecosystems.

## What Feedback Is Most Useful

Useful feedback for this beta includes:

- route-resolution failures on normal WordPress content
- preview-flow failures or confusing preview behavior
- Gutenberg layouts that break structurally instead of failing safely
- install/package/runtime issues on real WordPress environments
- onboarding friction where the validated path is still unclear

Less useful feedback for this beta:

- requests for theme parity
- requests for unsupported ecosystem compatibility framed as core regressions
- feature requests that assume WooCommerce, ACF, or Elementor are already in scope for core
