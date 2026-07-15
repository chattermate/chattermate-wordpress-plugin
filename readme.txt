=== ChatterMate Chat ===
Contributors: chattermate
Tags: chat, live chat, chatbot, ai, customer support
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add the ChatterMate AI customer-support chat widget to your WordPress site. Enter your Widget ID and the chat launcher appears on every page.

== Description ==

ChatterMate is an AI-powered customer-support chat widget. This plugin adds the ChatterMate chat launcher to your WordPress site without editing any theme files.

Set your Widget ID once under **Settings → ChatterMate Chat**, and the chat bubble appears on the front end of every page. Because the widget is loaded from ChatterMate's hosted service, it always stays up to date — there is nothing to rebuild when ChatterMate ships improvements.

**Features**

* One-click setup — paste your Widget ID and you're done.
* No theme edits — the widget is injected via the standard footer hook and survives theme changes and updates.
* Self-host friendly — an optional Script URL field lets ChatterMate self-hosters point at their own instance.
* Lightweight — the plugin ships no bundled JavaScript; it only loads the widget from ChatterMate.

You'll need a ChatterMate account and a Widget ID. Create one at [chattermate.chat](https://chattermate.chat).

== External services ==

This plugin connects to ChatterMate, a third-party hosted service, to provide the chat widget. This is required for the plugin to function.

* When a page on your site loads, the plugin loads the ChatterMate widget script from `https://app.chattermate.chat/webclient/chattermate.min.js` (or a custom Script URL you configure for a self-hosted ChatterMate instance).
* The loaded widget then communicates with the ChatterMate API (`https://api.chattermate.chat`) to fetch widget configuration and to send and receive chat messages entered by your site visitors.
* Data sent to ChatterMate includes your Widget ID and any chat messages, name, or email a visitor voluntarily provides in the chat, along with standard request metadata (such as IP address and browser user-agent) sent with any web request.

No data is sent to ChatterMate until a page containing the widget is loaded. For details, see the ChatterMate [Terms of Service](https://chattermate.chat/terms) and [Privacy Policy](https://chattermate.chat/privacy).

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it through the WordPress **Plugins** screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → ChatterMate Chat**.
4. Enter your **Widget ID** from your ChatterMate dashboard and save.
5. Visit the front end of your site — the chat launcher now appears.

== Frequently Asked Questions ==

= Where do I find my Widget ID? =

Log in to your ChatterMate dashboard, open the AI Agent you want to embed, and copy the Widget ID shown in its embed / launch settings. See the [widget documentation](https://docs.chattermate.chat/features/widget).

= Do I need a ChatterMate account? =

Yes. The plugin displays a widget hosted by ChatterMate, so you need a ChatterMate account and a Widget ID. You can create one at [chattermate.chat](https://chattermate.chat).

= I self-host ChatterMate. Can I use this plugin? =

Yes. Set the **Script URL** field on the settings page to your own instance's `/webclient/chattermate.min.js` URL.

= The widget isn't showing up. =

Confirm you saved a Widget ID under **Settings → ChatterMate Chat**, that your theme calls `wp_footer()` (nearly all do), and that no caching or ad-blocking layer is stripping the script. The widget renders only on the front end, not in the admin.

== Screenshots ==

1. The ChatterMate Chat settings screen where you enter your Widget ID.

== Changelog ==

= 1.0.0 =
* Initial release: inject the ChatterMate chat widget via a Widget ID setting, with an optional Script URL override for self-hosted instances.
