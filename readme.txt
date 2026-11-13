=== ChatterMate Chat ===
Contributors: chattermate
Tags: ai chatbot, live chat, customer support, chat, woocommerce
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI customer support for WordPress. Answers from your own content, hands over to your team, and turns hard questions into tickets.

== Description ==

ChatterMate is customer support rebuilt around AI. It answers your visitors from your own website, docs and FAQs, hands the conversation to your team when a person should step in, and turns hard questions into tickets so nothing is lost. It runs the same way for a single shop and for a support team handling thousands of conversations a month.

This plugin puts the ChatterMate chat on your WordPress site without touching your theme. Connect it in one click, decide where the chat appears, and let the AI see what your visitor is looking at.

**What you get**

* **One-click connect.** Click Connect with ChatterMate, pick your AI agent, and you are back in WordPress with the chat live. Pasting a Widget ID works too.
* **A real connection check.** The settings screen confirms your widget with ChatterMate and shows which AI agent answers, or tells you plainly what is wrong.
* **Choose where the chat appears.** Every page, only the pages you pick, or every page except the ones you pick. Rules can also cover your homepage, all blog posts, and with WooCommerce all product pages and the shop.
* **Answers that know the page.** The AI can see what page the visitor is on and, in a WooCommerce store, the product they are looking at and what is in their cart.
* **Signed-in customers.** Visitors who are logged in to your site can chat under their own name and email, so your team knows who they are.
* **Chat button block.** Add a button anywhere that opens the chat, optionally with a question already typed in. There is a `[chattermate_button]` shortcode too, and any menu link to `#chattermate` opens the chat.
* **Store-friendly.** Hide the chat on the WooCommerce cart and checkout so buyers stay focused on paying.
* **Fast pages.** Load the chat as soon as the page loads, or only when the visitor first scrolls, taps, moves the mouse or types, so it stays out of PageSpeed tests.
* **Try it before visitors do.** A live preview loads your real chat on the settings screen.
* **Hide from administrators** while you work on the site.
* **Self-host friendly.** Point the plugin at your own ChatterMate install under Advanced.

**Pricing**

You need a ChatterMate account. The Free plan includes 50 AI replies a month. Paid plans start at $4.99 a month and are priced by AI replies. New accounts can try the Growth plan free for 14 days. See [chattermate.chat](https://chattermate.chat) for current plans.

== External services ==

This plugin connects to ChatterMate, a hosted customer-support service run by ChatterMate. The service is required: the chat itself runs on ChatterMate.

**1. The chat widget, on your site's pages**

* What: the widget script from `https://app.chattermate.chat/webclient/chattermate.min.js`, which opens the chat window from `app.chattermate.chat` and talks to the ChatterMate API at `https://api.chattermate.chat`.
* When: on front-end pages where your visibility rules allow the chat. With "Load the chat" set to first interaction, nothing is loaded until the visitor scrolls, taps, moves the mouse or types.
* What is sent: your Widget ID; anything the visitor types in the chat, and any name or email they choose to give; standard request details such as IP address and browser user agent. The widget keeps a token in the visitor's browser (`localStorage` key `ctid`) so the conversation continues across pages.
* With "Tell the AI what the visitor is looking at" on (the default for new installs; turn it off under Personal answers): the page type and title, the store currency and, on WooCommerce product pages, the product's name, price, SKU, stock and sale status and its URL. When the visitor opens the chat, the names, quantities, count and total of the items in their cart, read in their own browser from your site's WooCommerce Store API (`/wp-json/wc/store/v1/cart`). Search terms are never sent.

**2. The connection check, in your WordPress admin only**

* What: a request to `https://api.chattermate.chat/api/v1/widgets/{your Widget ID}/status`, which only reads. (Against a ChatterMate API older than that endpoint, the plugin asks `/widgets/{your Widget ID}` instead, which also starts an anonymous chat visitor.)
* When: only when the ChatterMate settings are saved (including after one-click connect) or an administrator clicks "Check again". Never on visitor page views.
* What is sent: your Widget ID, plus standard request details from your server (its IP address and a `ChatterMate-WordPress/<version>` user agent).
* Why: to confirm the widget exists and show the name of the AI agent that answers.

**3. Signed-in customers, only if you turn it on**

* What: a request from your server to `https://api.chattermate.chat/api/v1/generate-token`.
* When: when a visitor who is logged in to your site loads a page with the chat, at most about twice a day per user (a failed attempt is retried after 10 minutes, and not at all for an hour if ChatterMate refuses the key), or when the widget reports that its token was refused (at most once a minute). Also when an administrator clicks "Show live preview".
* What is sent: your Widget app API key, your Widget ID, and the logged-in user's email address and name.
* Why: so the visitor chats under their own name and your team sees who they are. The API key stays on your server; the visitor's browser only receives their own short-lived token, which is never written into the page, and keeps it for the browser tab (`sessionStorage` key `chattermate_wp_token`). A browser that signs out forgets it (`localStorage` key `chattermate_wp_identified`).
* ChatterMate trusts the email your site sends. Turn this on only if your site confirms email addresses; developers can limit it with the `chattermate_identify_user` filter.

**4. The live preview and one-click connect, in your WordPress admin only**

* The live preview loads the same widget as in 1 on the ChatterMate settings screen, only when an administrator clicks "Show live preview".
* "Connect with ChatterMate" opens `https://app.chattermate.chat/connect/wordpress` in the administrator's browser with your site's name and address, the wp-admin address to return to and a one-time code. ChatterMate sends the administrator back with the chosen Widget ID. Nothing is sent from your server. (Offered on ChatterMate cloud; self-hosters paste the Widget ID.)

If you self-host ChatterMate and set your own URLs under Advanced, all of this goes to your own servers instead.

ChatterMate [Terms and Conditions](https://chattermate.chat/terms-and-conditions/) and [Privacy Policy](https://chattermate.chat/privacy-policy/).

The plugin also adds suggested text for your own privacy policy under **Settings → Privacy**.

== Installation ==

1. Install the plugin from **Plugins → Add New**, or upload the zip under **Plugins → Add New → Upload Plugin**.
2. Activate it and open **ChatterMate** in the admin menu.
3. No account yet? Click **Create free account**, then train your AI agent on your website from the ChatterMate dashboard.
4. Click **Connect with ChatterMate**, sign in if asked, pick your AI agent and click **Connect**. Or paste the Widget ID from **AI Agents → your agent → Widget** and click **Save changes**.
5. Click **Show live preview** to try the chat, then choose where it appears.

== Frequently Asked Questions ==

= Where do I find my Widget ID? =

You don't need to if you use **Connect with ChatterMate**. Otherwise, in ChatterMate open **AI Agents**, choose the agent you want on your site and open its **Widget** tab. The [widget guide](https://docs.chattermate.chat/features/widget) has screenshots.

= Do I need a ChatterMate account? =

Yes. The chat runs on ChatterMate, and the Free plan is enough to start: it includes 50 AI replies a month.

= How do I add a chat button to a page? =

Add the **Chat button** block, or the shortcode `[chattermate_button label="Chat with us" message="Where is my order?"]`. The message is typed into the chat for the visitor, who decides whether to send it. To open the chat from a menu, add a custom link to `#chattermate`. Buttons only show on pages where the chat is allowed.

= What does "Tell the AI what the visitor is looking at" share? =

The page type and title, and in a WooCommerce store the product on a product page and, when the visitor opens the chat, what is in their cart. Turn it off under **Personal answers**. Developers can change what is sent with the `chattermate_page_context` filter.

= How do signed-in customers work? =

In ChatterMate, open **Settings → Widget Apps** and create an API key. Paste it into the plugin under **Personal answers** and turn on **Recognise signed-in customers**. Logged-in visitors then chat under their WordPress name and email. If your AI agent requires a token, only signed-in visitors see the chat.

= I use a caching or optimisation plugin. Anything to do? =

Page caching works as is: the visibility rules are decided per page, and no visitor's details are ever written into the page. If you use signed-in customers, make sure your cache does not serve cached pages to logged-in users (most caches skip them already). If your optimisation plugin delays or combines JavaScript (for example WP Rocket "Delay JavaScript execution", LiteSpeed Cache "JS Delayed" or Autoptimize), exclude `chattermate` from it.

= Will it slow my site down? =

The plugin adds one small script to the pages where the chat is allowed, loaded without blocking the page. For the best PageSpeed scores, set **Load the chat** to load on the visitor's first interaction.

= Does it work with WooCommerce? =

Yes. Show or hide the chat on product pages and the shop, hide it on the cart and checkout, and let the AI see the product and the visitor's cart.

= Is it GDPR friendly? =

The chat only loads on pages your rules allow, page and cart details can be turned off, and signed-in customers is off until you turn it on. The plugin adds suggested privacy-policy text you can adapt. ChatterMate keeps a token in the visitor's browser so the conversation continues across pages; if your cookie banner blocks functional storage until consent, add the chat script to its functional category.

= Where are conversations stored? =

On ChatterMate, where your team answers them. Nothing about conversations is stored in your WordPress database.

= What happens when I deactivate or delete the plugin? =

The chat disappears from your site. Deleting the plugin also removes its settings, the saved API key and cached tokens from WordPress. Your ChatterMate account and conversations are not touched.

= I self-host ChatterMate. Can I use this plugin? =

Yes. Under **Advanced**, set the widget script URL (your app URL followed by `/webclient/chattermate.min.js`) and your API URL (ending in `/api/v1`); until the API URL is set, the plugin makes no calls to ChatterMate cloud for a self-hosted widget. WordPress only lets the plugin reach public addresses, so an API on a private network address needs the `http_request_host_is_external` filter.

== Screenshots ==

1. Connected, with the live preview, quick links and your AI agent's name.
2. Choose where the chat appears, hide it on checkout, and load it on first interaction.
3. Personal answers: page and cart details for the AI, and signed-in customers.
4. First run: connect in one click, or paste your Widget ID.
5. The ChatterMate chat on a WordPress store.

== Changelog ==

= 1.1.0 =
* New ChatterMate menu with a branded settings screen.
* One-click connect: pick your AI agent in ChatterMate and come back connected.
* Checks your widget with ChatterMate and shows the AI agent that answers.
* Visibility rules: every page, only chosen pages, or every page except chosen ones, plus homepage, blog posts, product pages and shop.
* Hide the chat from logged-in administrators and on the WooCommerce cart and checkout.
* The AI can see the page, the WooCommerce product and the visitor's cart.
* Signed-in customers chat under their own name and email.
* Chat button block, `[chattermate_button]` shortcode and `#chattermate` links.
* Option to load the chat on the visitor's first interaction for faster pages.
* Live preview on the settings screen.
* Setup notice after activation, on the Dashboard and Plugins screens.
* Suggested privacy-policy text.
* Self-hosters can set the API URL as well as the script URL.
* The widget is now enqueued the WordPress way instead of printed into the footer.

= 1.0.0 =
* Initial release: add the ChatterMate chat with a Widget ID, with an optional script URL for self-hosted installs.

== Upgrade Notice ==

= 1.1.0 =
A new ChatterMate screen with one-click connect, visibility rules, page-aware answers, signed-in customers and a Chat button block.
