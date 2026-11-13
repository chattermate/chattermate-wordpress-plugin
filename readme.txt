=== ChatterMate Chat ===
Contributors: chattermate
Tags: ai chatbot, live chat, customer support, chat, woocommerce
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI customer support for WordPress. Answers from your own content, hands over to your team, and turns hard questions into tickets.

== Description ==

ChatterMate is customer support rebuilt around AI. It answers your visitors from your own website, docs and FAQs, hands the conversation to your team when a person should step in, and turns hard questions into tickets so nothing is lost. It runs the same way for a single shop and for a support team handling thousands of conversations a month.

This plugin puts the ChatterMate chat on your WordPress site without touching your theme. Connect it once with your Widget ID and decide where the chat appears.

**What you get**

* **A real connection check.** The settings screen confirms your Widget ID with ChatterMate and shows which AI agent will answer, or tells you plainly what is wrong.
* **Choose where the chat appears.** Every page, only the pages you pick, or every page except the ones you pick. Rules can also cover your homepage, all blog posts, and with WooCommerce all product pages and the shop.
* **Store-friendly.** Hide the chat on the WooCommerce cart and checkout so buyers stay focused on paying.
* **Fast pages.** Load the chat as soon as the page loads, or only when the visitor first scrolls, taps, moves the mouse or types, so it stays out of PageSpeed tests. The plugin adds one small script and nothing else to your pages.
* **Try it before visitors do.** A live preview loads your real chat on the settings screen.
* **Hide from administrators** while you work on the site.
* **Self-host friendly.** Point the plugin at your own ChatterMate install under Advanced.

**Pricing**

You need a ChatterMate account. The Free plan includes 50 AI replies a month. Paid plans start at $4.99 a month and are priced by AI replies. New accounts can try the Growth plan free for 14 days. See [chattermate.chat](https://chattermate.chat) for current plans.

== External services ==

This plugin connects to ChatterMate, a hosted customer-support service run by ChatterMate. The service is required: the chat itself runs on ChatterMate.

**1. The chat widget, on your site's pages**

* What: the widget script from `https://app.chattermate.chat/webclient/chattermate.min.js`, which then opens the chat window from `app.chattermate.chat` and talks to the ChatterMate API at `https://api.chattermate.chat`.
* When: on every front-end page where your visibility rules allow the chat. With "Load the chat" set to first interaction, nothing is loaded until the visitor scrolls, taps, moves the mouse or types.
* What is sent: your Widget ID; anything the visitor types in the chat, and any name or email they choose to give; standard request details such as IP address and browser user agent. The widget keeps a token in the visitor's browser so the conversation continues across pages.

**2. The connection check, in your WordPress admin only**

* What: a request to `https://api.chattermate.chat/api/v1/widgets/{your Widget ID}/status`. It only reads; it starts no chat.
* When: only when the ChatterMate settings are saved or an administrator clicks "Check again". Never on visitor page views.
* What is sent: your Widget ID, plus standard request details from your server (its IP address and a `ChatterMate-WordPress/<version>` user agent).
* Why: to confirm the Widget ID exists and show the name of the AI agent that will answer.

**3. The live preview, in your WordPress admin only**

* What: the same widget as in 1, loaded on the ChatterMate settings screen.
* When: only when an administrator clicks "Show live preview".
* What is sent: the same as in 1, for the administrator's own test chat.

If you self-host ChatterMate and set your own URLs under Advanced, both go to your own servers instead.

ChatterMate [Terms and Conditions](https://chattermate.chat/terms-and-conditions/) and [Privacy Policy](https://chattermate.chat/privacy-policy/).

The plugin also adds suggested text for your own privacy policy under **Settings → Privacy**.

== Installation ==

1. Install the plugin from **Plugins → Add New**, or upload the zip under **Plugins → Add New → Upload Plugin**.
2. Activate it.
3. Open **ChatterMate** in the admin menu.
4. No account yet? Click **Create free account**. Then train your AI agent on your website from the ChatterMate dashboard.
5. Copy the Widget ID from **AI Agents → your agent → Widget** in ChatterMate, paste it into the plugin and click **Save changes**.
6. Click **Show live preview** to try the chat, then choose where it appears.

== Frequently Asked Questions ==

= Where do I find my Widget ID? =

In ChatterMate, open **AI Agents**, choose the agent you want on your site and open its **Widget** tab. The [widget guide](https://docs.chattermate.chat/features/widget) has screenshots.

= Do I need a ChatterMate account? =

Yes. The chat runs on ChatterMate, and the Free plan is enough to start: it includes 50 AI replies a month.

= Will it slow my site down? =

The plugin adds one small script to the pages where the chat is allowed, loaded without blocking the page. For the best PageSpeed scores, set **Load the chat** to load on the visitor's first interaction.

= I use a caching or optimisation plugin. Anything to do? =

Page caching works as is: the visibility rules are decided per page. If your optimisation plugin delays or combines JavaScript (for example WP Rocket "Delay JavaScript execution", LiteSpeed Cache "JS Delayed" or Autoptimize), exclude `chattermate` from it so the Widget ID and the widget script keep their order.

= Does it work with WooCommerce? =

Yes. You can show or hide the chat on all product pages and the shop page, and hide it on the cart and checkout.

= Is it GDPR friendly? =

The chat only loads on pages your rules allow, and the plugin adds suggested privacy-policy text you can adapt. ChatterMate keeps a token in the visitor's browser so the conversation continues across pages; if your cookie banner blocks functional storage until consent, add the chat script to its functional category.

= Where are conversations stored? =

On ChatterMate, where your team answers them. Nothing about conversations is stored in your WordPress database.

= What happens when I deactivate or delete the plugin? =

The chat disappears from your site. Deleting the plugin also removes its settings from WordPress. Your ChatterMate account and conversations are not touched.

= I self-host ChatterMate. Can I use this plugin? =

Yes. Under **Advanced**, set the widget script URL (your app URL followed by `/webclient/chattermate.min.js`) and your API URL (ending in `/api/v1`).

== Screenshots ==

1. The ChatterMate screen when connected, with the live preview.
2. Choose where the chat appears, hide it on checkout, and load it on first interaction.
3. First-run setup with a clear message when a Widget ID is wrong.
4. The ChatterMate chat on a WordPress site.

== Changelog ==

= 1.0.1 =
* New ChatterMate menu with a branded settings screen.
* Checks your Widget ID with ChatterMate and shows the AI agent that will answer.
* Visibility rules: every page, only chosen pages, or every page except chosen ones, plus homepage, blog posts, product pages and shop.
* Hide the chat from logged-in administrators and on the WooCommerce cart and checkout.
* Option to load the chat on the visitor's first interaction for faster pages.
* Live preview on the settings screen.
* Setup notice after activation, on the Dashboard and Plugins screens.
* Suggested privacy-policy text.
* Self-hosters can set the API URL as well as the script URL.
* The widget is now enqueued the WordPress way instead of printed into the footer.

= 1.0.0 =
* Initial release: add the ChatterMate chat with a Widget ID, with an optional script URL for self-hosted installs.

== Upgrade Notice ==

= 1.0.1 =
A new ChatterMate screen with a connection check, visibility rules and faster loading.
