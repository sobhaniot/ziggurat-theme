# Zigurat Theme Updater

WordPress plugin source for **Appearance → به‌روزرسانی زیگورات**. The plugin accepts a small ZIP containing only changed theme files, previews its file list, creates a private backup, applies the patch, and can restore the most recent backup.

## Patch ZIP format

The ZIP root contains `zigurat-theme-patch.json` and each changed file at its path relative to the theme root. Example manifest:

```json
{
  "theme": "zigurat",
  "files": [
    "inc/core/example.php",
    "assets/css/example.css"
  ]
}
```

The manifest paths and archive entries must match exactly. The uploader rejects traversal, hidden paths, symlinks, unlisted files, unsupported extensions, more than 200 files, ZIPs over 20 MB, or expanded contents over 40 MB. Package preview expires after ten minutes.

## Installation and operation

Install the generated `zigurat-theme-updater.zip` from **Plugins → Add Plugin → Upload Plugin**, activate it, then open **Appearance → به‌روزرسانی زیگورات**. The plugin runs only when the active theme stylesheet is `zigurat` and the WordPress filesystem is directly writable. It does not change file permissions or request FTP credentials.

Backups are written to `wp-content/.zigurat-theme-updater/backups/`, with Apache deny rules and index guards, and the plugin keeps the five most recent archives. The page can restore the most recent patch. A host using Nginx should add a deny rule for `wp-content/.zigurat-theme-updater/` before enabling the updater; the directory is intentionally outside the public uploads area.

## Build

From PowerShell, run `build-plugin.ps1`. It packages only the plugin PHP file and this readme into `dist/zigurat-theme-updater.zip`; tests and source tooling are excluded. Create a changed-files patch with `build-patch.ps1 -Files @('assets/css/manager.css','inc/core/example.php')`; pass `-ThemeRoot` if the changed files are in another theme copy.

## Local verification

Run `C:\xampp\php\php.exe tests\updater.test.php`. The test uses temporary fixture directories and does not modify the active theme or WordPress database.
