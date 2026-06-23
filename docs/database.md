# Database Keys

All data written by this plugin lives in `wp_options`. No custom tables.

## Manifest cache (per form)

### `qomon_manifest_cache_<md5hash>`

Stores the manifest and its fetch timestamp for a single form.

```php
[
    'manifest'   => [ /* decoded manifest array from Qomon CDN */ ],
    'fetched_at' => 1742000000, // unix timestamp of last successful CDN fetch
]
```

- **Hash:** `md5($base_id . $bucket)`
- **Written by:** `wpqomon_get_manifest()` on a successful CDN fetch
- **Read by:** `wpqomon_get_manifest()` on every form render
- **Deleted by:** `wpqomon_refresh_manifests()` before re-fetching; `POST /wp-json/qomon/v1/refresh-manifest` for single-form refresh
- **Autoload:** `false`

The `fetched_at` timestamp drives the 2-hour stale threshold and 4-hour hard cutoff. There is no automatic expiry — `wpqomon_get_manifest()` checks the age at read time.

## Form registry

### `qomon_registered_forms`

An associative PHP array of all form IDs known to the plugin, keyed by `md5($base_id . $bucket)`.

```php
[
    '<md5hash>' => [
        'base_id'   => '5652feb3-bc2a-4c0d-ba75-e5f68997f308',
        'form_type' => 'petition',   // '' for standard forms
        'bucket'    => 'cdn-form.qomon.org',
    ],
    // ...
]
```

- **Written by:** `wpqomon_register_form()`, called from `save_post` and `wpqomon_render_form()`
- **Read by:** `wpqomon_refresh_manifests()` (cron job and manual refresh)
- **Autoload:** `false`

Forms are never removed automatically. If a form is removed from all posts, its entry remains and the cron continues to refresh it. This is intentional — the cached manifest stays available as a fallback.

## Cleanup on uninstall

No uninstall hook is registered. To clean up manually after removing the plugin:

```sql
DELETE FROM wp_options WHERE option_name LIKE 'qomon_manifest_cache_%';
DELETE FROM wp_options WHERE option_name = 'qomon_registered_forms';
```
