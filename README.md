# ChatterMate Chat — WordPress Plugin

Official WordPress plugin that adds the [ChatterMate](https://chattermate.chat) AI
customer-support chat widget to any WordPress site. The admin enters a **Widget ID**
under **Settings → ChatterMate Chat** and the chat launcher appears on every front-end
page — no theme edits, survives theme/plugin updates.

This is the WordPress equivalent of the official Shopify app in `../../shopify/chattermate-chat/`.

## How it works

On `wp_footer` (front end only) the plugin prints the canonical ChatterMate embed:

```html
<script>window.chattermateId="YOUR_WIDGET_ID";</script>
<script src="https://app.chattermate.chat/webclient/chattermate.min.js" async></script>
```

The widget JS is **not** bundled — it is loaded from ChatterMate's hosted service so it
stays auto-updated. Self-hosters override the Script URL on the settings page.

Source of truth for the snippet: `frontend/src/utils/widgetEmbed.ts` → `buildWidgetEmbed()`.

## Files

| File | Purpose |
| --- | --- |
| `chattermate-chat.php` | Main plugin: settings page + footer injection. |
| `readme.txt` | WordPress.org-format readme (the listing gate). |
| `uninstall.php` | Deletes the stored option on plugin deletion. |
| `LICENSE` | GPLv2 (WordPress.org requires GPLv2-compatible licensing). |
| `assets/` | WordPress.org listing art (icon/banner/screenshots). **Not** shipped in the plugin zip. |

## Local testing

Spin up a throwaway WordPress and side-load the plugin:

```bash
# From this directory — zip everything except the listing-art dir:
cd ..
zip -r chattermate-chat.zip chattermate-chat -x '*/assets/*'

# Start a local WordPress (Docker):
docker run --rm -p 8080:80 wordpress
# Then: http://localhost:8080 → install WordPress → Plugins → Upload Plugin → chattermate-chat.zip
```

1. Activate the plugin.
2. **Settings → ChatterMate Chat** → paste a real Widget ID → Save.
3. Load the site front end and confirm the chat launcher appears and the two `<script>`
   tags render before `</body>`.

Standards checks before submitting:

```bash
php -l chattermate-chat.php     # lint each PHP file
# Install the official "Plugin Check (PCP)" plugin from WordPress.org and run it
# against ChatterMate Chat — clear all errors/warnings.
```

## Publishing to the WordPress.org plugin directory

1. Build the zip (see above), excluding `assets/`.
2. Submit at <https://wordpress.org/plugins/developers/add/> under the ChatterMate
   WordPress.org account. Automated + human review follows (days to a few weeks).
3. On approval, WordPress.org provisions an SVN repo:
   - Plugin files → `/trunk`
   - Tagged release → `/tags/1.0.0` (must match `Stable tag` in `readme.txt`)
   - `assets/` art (`icon-256x256.png`, `banner-772x250.png`, `screenshot-1.png`) → `/assets`
4. `Stable tag` in `readme.txt` must equal the released tag.

### Listing art

The files in `assets/` are placeholders — replace with final artwork before the SVN
`/assets` commit:

- `icon-256x256.png` — plugin icon (also provide `icon-128x128.png`).
- `banner-772x250.png` — header banner (also provide `banner-1544x500.png` for retina).
- `screenshot-1.png` — the settings screen; capture after a local run.

## License

GPLv2 or later. This plugin is new, self-contained code that only *loads* the ChatterMate
widget (it does not bundle it), so it is licensed independently of the Apache-2.0 widget
it embeds.
