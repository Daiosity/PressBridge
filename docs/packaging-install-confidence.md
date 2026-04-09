# Packaging and Install Confidence

This checklist exists to reduce beta risk around plugin ZIP packaging, install, activation, settings, starter export, deactivation, and uninstall behavior.

It is intentionally lightweight. The goal is repeatable confidence, not a heavy automation framework.

## What This Pass Proves

- the plugin ZIP can be built from the current repo state
- the ZIP filename matches the plugin header version
- the packaged structure contains the expected plugin root and required files
- the packaged bootstrap still contains the expected version header
- the packaged starter export still injects runtime starter config
- activation seeds default settings
- settings page and starter export remain part of the packaged plugin
- uninstall removes the plugin settings option

## Repeatable Local Confidence Flow

### 1. Build the ZIP

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-plugin.ps1
```

Expected result:

- `build/lenviqa-{version}.zip`

### 2. Validate the ZIP structure

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\validate-package.ps1
```

This validation checks:

- plugin version can be parsed from `pressbridge.php`
- the built ZIP exists
- the ZIP extracts with a top-level `lenviqa/` folder
- required packaged files and directories exist:
  - `pressbridge.php`
  - `readme.txt`
  - `uninstall.php`
  - `assets/`
  - `includes/`
  - `templates/`
- activation and deactivation hook registration are still present in the packaged bootstrap
- packaged `WTR_VERSION` matches the plugin header version
- uninstall still removes `wtr_settings` and `wtr_settings` multisite cleanup
- packaged `pressbridge.php` still reports the same version
- packaged starter export still injects runtime `src/config/wp-config.json`
- packaged starter export still wires runtime `apiBase`

### 3. Validate install/activation behavior in the Local site

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\validate-install-runtime.ps1
```

This validation checks:

- the built ZIP can be extracted into the Local site's plugin directory
- the plugin activates cleanly in a real WordPress runtime
- activation seeds the expected default settings
- starter export registration is present after activation
- deactivation succeeds without dropping the settings option
- uninstall cleanup removes `wtr_settings` in the WordPress runtime
- the site's original active/settings state is restored after validation
- the Local site's checked-in `wp-config.php` is temporarily patched to use the actual Local MySQL port for CLI validation, then restored immediately

### 4. Manual install confidence pass

These are still manual and should stay in the release checklist:

1. Install the built ZIP into a clean or disposable WordPress site.
2. Activate the plugin.
3. Open `Settings > Lenviqa`.
4. Confirm default settings load without fatal errors.
5. Confirm starter export download works when `ZipArchive` is available.
6. Deactivate the plugin and confirm:
   - no fatal errors
   - site remains accessible
7. Uninstall the plugin and confirm:
   - `wtr_settings` is removed

## What Is Beta-Safe

- repeatable ZIP packaging from the repo
- version-aware ZIP naming
- packaged file/folder structure validation
- basic activation default-setting seeding
- Local runtime activation, deactivation, and uninstall settings cleanup validation
- starter export hook registration in a real WordPress runtime
- uninstall settings cleanup

## What Still Remains Manual

- fresh WordPress admin install/activate flow on a clean site
- confirming the settings page renders correctly in wp-admin after activation
- starter export behavior on the target server environment
- server-specific `ZipArchive` availability and filesystem quirks
- non-Local environments, because the runtime validator is intentionally Local-specific

## Honest Boundaries

This guardrail does not prove:

- every hosting environment will behave identically
- starter export will succeed on every server
- uninstall removes every temporary transient or preview token artifact
- multisite-specific packaging/install behavior
