# Lenviqa Beta Scope

This document defines the current beta boundary for Lenviqa core.

It is intentionally narrow and technical. Confidence claims in this document should map back to something that is actually covered by the current guardrails:

- route scenarios
- Gutenberg scenarios
- preview scenarios
- package validation
- install/runtime validation

## 1. Core behavior now proven reliable

The following behaviors are currently covered by repeatable validation and can be treated as beta-safe for core.

### Route resolution

Covered by:

- [route-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\route-scenario-matrix.md)
- [validate-core.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\validate-core.ps1)

Proven cases:

- home route resolution in the current Local setup
- standard published page resolution
- nested hierarchical page resolution
- standard published post resolution
- unresolved route honesty with `404` behavior
- basic path normalization, including duplicate-slash input and full URL normalization

### Gutenberg rendering

Covered by:

- [gutenberg-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\gutenberg-scenario-matrix.md)
- [validate-core.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\validate-core.ps1)

Proven cases:

- nested groups acting as structural wrappers
- columns inside grouped sections
- media-text blocks recovering layout/content from saved markup when parsed block trees are sparse
- cover blocks preserving hero/content relationships with safe fallback
- galleries recovering stable output from saved markup when parsed gallery structure is incomplete
- button-group layout intent preserved without requiring perfect block-tree fidelity

### Preview flow

Covered by:

- [preview-scenario-matrix.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\preview-scenario-matrix.md)
- [refresh-preview-scenarios.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\refresh-preview-scenarios.ps1)
- [validate-core.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\validate-core.ps1)

Proven cases:

- valid preview token resolution
- preview payloads returning preview state and preview metadata
- expired preview token failure
- missing/unknown preview token failure
- malformed preview route failure
- preview not leaking draft-only content into normal public routing
- local preview validation remaining repeatable without manual reseeding

### Packaging and install/runtime behavior

Covered by:

- [packaging-install-confidence.md](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\docs\packaging-install-confidence.md)
- [validate-package.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\validate-package.ps1)
- [validate-install-runtime.ps1](C:\Users\Christo\Documents\WordPress Plugin Development\WP to React\scripts\validate-install-runtime.ps1)

Proven cases:

- plugin ZIP builds repeatably from repo state
- packaged ZIP structure is correct
- packaged plugin version metadata stays aligned
- activation hook wiring is present
- default settings seed correctly on activation
- starter export hook registration is present after activation
- deactivation does not obviously destroy settings state
- uninstall cleanup removes `wtr_settings`

## 2. Beta-safe with caveats

The following areas are in reasonable beta territory, but should still be described carefully.

### Generic React starter behavior

Reasonable confidence:

- the main starter runs locally
- the exported starter mirrors the main starter structure closely enough for current core behavior
- the lightweight smoke frontend remains a valid quick-check frontend

Caveat:

- `frontend-lite` is a smoke frontend, not a full parity frontend
- parity is maintained where it matters for core validation, not feature-for-feature

### Gutenberg rendering of common layouts

Reasonable confidence:

- layout intent is preserved for the validated scenario set
- safe fallback behavior is preferred over fragile exact translation

Caveat:

- this is not a claim of theme-level fidelity
- the guarantee is stable structure, not pixel parity with WordPress theme output

### Preview behavior in local development

Reasonable confidence:

- local preview token resolution is repeatable and honest
- preview guardrails catch obvious preview regressions

Caveat:

- this does not fully prove browser-level cross-domain preview behavior in every environment
- the current proof is strongest in the Local scenario set, not on arbitrary hosting setups

## 3. Still manual or environment-dependent

These areas have supporting validation, but still require manual confirmation or depend on environment-specific behavior.

- wp-admin ZIP upload/install clicks on a fresh site
- wp-admin settings page rendering and workflow in a real browser session
- starter export download behavior on arbitrary hosting
- non-Local WordPress runtime differences
- browser-level preview flow from the editor UI through a separate frontend domain
- server-specific filesystem, zip, or PHP extension quirks
- final visual confidence on Gutenberg-heavy pages beyond the seeded scenario set

## 4. Intentionally out of scope for core

The following are not part of the current beta promise for Lenviqa core.

- WooCommerce compatibility as a core guarantee
- split-domain commerce/session reliability
- third-party/custom block ecosystem compatibility
- interactive blocks that require WordPress frontend JS
- theme-specific CSS fidelity
- pixel-perfect parity with the active WordPress theme
- Elementor or other builder-specific compatibility promises

These may become advanced compatibility or premium layers later, but they are not core beta commitments.

## 5. Next post-beta priorities

Once core beta confidence is stable, the next priorities should be:

1. tighten real-world confidence on a few more representative WordPress content sets without widening core claims
2. keep starter/exported-starter/smoke-frontend alignment disciplined
3. package the current guardrail workflow into release discipline
4. define advanced compatibility layers separately from core

The likely first post-beta advanced priorities are:

- WooCommerce compatibility
- ACF mapping
- clearer foundation/starter variants

Those should be framed as adoption accelerators, not as fixes for core instability.
