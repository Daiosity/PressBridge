# Supported Beta Scope

PressBridge core is currently beta-safe where the repo has repeatable validation coverage.

That validated core currently includes:

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
- preview foundations:
  - valid preview token resolution
  - honest invalid/expired preview failure
  - preview content staying separate from normal public routing
- package/install/runtime confidence:
  - repeatable ZIP packaging
  - package structure validation
  - activation defaults
  - starter export hook presence
  - deactivation/uninstall checks

This is not a promise of universal WordPress frontend parity.

Use these docs as the detailed scope boundary:

- [beta-scope.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\beta-scope.md)
- [route-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\route-scenario-matrix.md)
- [gutenberg-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\gutenberg-scenario-matrix.md)
- [preview-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\preview-scenario-matrix.md)
- [packaging-install-confidence.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\packaging-install-confidence.md)

For the higher-level product boundary, see [beta-scope.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\beta-scope.md).
