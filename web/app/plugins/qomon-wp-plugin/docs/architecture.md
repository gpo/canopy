# Architecture: Manifest Caching

## Overview

PHP fetches a versioned `manifest.json` from Qomon's CDN per form, resolves the asset URLs, and writes `<script type="module">` and `data-style-link` directly into the page HTML. The manifest is cached in `wp_options` so the CDN is not hit on every page render.

## Manifest resolution

When a page containing a Qomon form is rendered, `wpqomon_get_manifest()` is called:

```
1. Cached copy < 2h old?
   └─ YES → return immediately (fast path)
   └─ NO  ↓

2. Live CDN fetch (wp_remote_get)
   └─ SUCCESS → store with fresh timestamp → return
   └─ FAIL    ↓

3. Stale copy < 4h old?
   └─ YES → return stale copy + error_log warning
   └─ NO  → return false → render HTML comment placeholder
```

The 4-hour hard cutoff means a form will stop rendering rather than serve a manifest that is likely stale enough to reference assets that no longer exist on the CDN.

## Cache store

Manifests are stored in `wp_options` as:

```php
[
    'manifest'   => [ /* decoded manifest array */ ],
    'fetched_at' => 1742000000, // unix timestamp
]
```

One row per form: `qomon_manifest_cache_<md5(base_id . bucket)>`. See `docs/database.md` for full key reference.

## Cache warming

The 2-hour stale threshold is intentionally longer than the 1-hour cron interval:

- **Normal operation:** cron refreshes every form before the 2h threshold → renders always hit the fast path
- **Cron misses one run:** copy still < 2h old → no impact
- **Cron misses two runs:** copy ≥ 2h → live CDN fetch on next render → re-warms the store
- **CDN down at 2h mark:** stale copy served (with logged warning) until 4h, then hard fail

## Form registry

The cron only refreshes forms it knows about. Forms are registered in `qomon_registered_forms` via two paths:

1. **`save_post` hook** — when a post is saved, the plugin parses block attributes and shortcode attributes from `post_content`, registers any Qomon form IDs found, and immediately calls `wpqomon_refresh_manifests()`. The cache is warm before any visitor loads the page.

2. **`wpqomon_render_form()`** — every render call also registers the form as a fallback, catching forms inserted via REST API or raw SQL.

## Manual cache refresh

Two refresh mechanisms are available:

- **Admin page** — "Refresh all form caches" button submits a POST to `admin-post.php` (nonce-protected), calls `wpqomon_refresh_manifests()`, and redirects back with a success notice showing how many forms were refreshed.

- **Block editor** — each Qomon Form block has a "Refresh cache" button (visible when a `base_id` is set). It calls `POST /wp-json/qomon/v1/refresh-manifest` with the form's `base_id`, which deletes the cached entry and forces a live CDN fetch. Requires `edit_posts` capability.

## Block rendering

The Gutenberg block uses a PHP `render_callback`. `save()` returns `null`, marking the block as dynamically rendered — Gutenberg stores attributes in the block comment delimiter but does not validate HTML output. On the frontend, WordPress calls `render_callback` with the block attributes, which calls `wpqomon_render_form()`.

## Script enqueuing

Qomon's form components are ES modules. WordPress's `wp_enqueue_script()` does not support `type="module"` natively, so a `script_loader_tag` filter adds it to any handle prefixed `qomon-form-`. Handles are keyed by `base_id`, so placing the same form twice on a page produces only one `<script>` tag.
