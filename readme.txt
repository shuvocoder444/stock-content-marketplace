=== Stock Content Marketplace ===
Contributors: yourname
Tags: stock photos, marketplace, digital downloads, freepik clone, media library
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A premium stock content marketplace plugin inspired by Freepik and Magnific.

== Description ==

Stock Content Marketplace transforms your WordPress site into a full-featured stock asset platform supporting:

* Photos, Videos, Mockups, PSDs, Icons, Fonts, Vectors, Illustrations, Templates, Sound Effects, Music, 3D Models
* Hierarchical categories and tags
* Free and Premium assets with WooCommerce payment integration
* Crown icon badges for premium assets (Freepik-style)
* Premium popup modal for upsell
* Download tracking and access control
* Collections and Favorites system
* AJAX search with sidebar filters
* REST API endpoints
* Elementor widgets
* SEO with Schema, Open Graph, Canonical URLs
* User Dashboard (downloads, purchases, favorites, collections)
* Subscription support (WooCommerce Subscriptions, PMPro, MemberPress)

== Installation ==

1. Upload the `stock-content-marketplace` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins > Installed Plugins**
3. Go to **SCM Settings** to configure branding, colors, downloads, and storage
4. Add stock assets via **Stock Assets > Add New**
5. Use shortcodes on any page:

   [stock_home]          — Full home grid with search + sidebar
   [stock_photos]        — Photos grid
   [stock_videos]        — Videos grid
   [stock_mockups]       — Mockups grid
   [stock_icons]         — Icons grid
   [stock_fonts]         — Fonts grid
   [stock_vectors]       — Vectors grid
   [stock_templates]     — Templates grid
   [stock_psds]          — PSDs grid
   [stock_music]         — Music grid
   [stock_sound_effects] — Sound Effects grid
   [stock_3d_models]     — 3D Models grid
   [stock_category id="123"]  — Specific category
   [stock_single id="456"]    — Embed a single asset
   [stock_related]            — Related assets (on single post)
   [stock_favorites]          — User favorites page
   [stock_collections]        — User collections page
   [stock_dashboard]          — Full user dashboard
   [stock_pricing]            — Pricing plans table
   [stock_buy_button id="123"] — Buy/Download button
   [stock_price id="123"]     — Display price

== Recommended Pages Setup ==

Create these pages and add the corresponding shortcode:

| Page Title     | Shortcode             |
|----------------|-----------------------|
| Stock Home     | [stock_home]          |
| Photos         | [stock_photos]        |
| Videos         | [stock_videos]        |
| Collections    | [stock_collections]   |
| Favorites      | [stock_favorites]     |
| My Dashboard   | [stock_dashboard]     |
| Pricing        | [stock_pricing]       |

== WooCommerce Integration ==

1. Install and activate WooCommerce
2. Enable WooCommerce in **SCM Settings > General**
3. When editing a stock asset, enter a Regular Price and click **Auto-Create WC Product**
4. The plugin links the WooCommerce product to the asset
5. After successful payment, the download is automatically unlocked for the buyer

== AWS S3 Storage ==

1. Go to **SCM Settings > Storage**
2. Check "Enable AWS S3"
3. Enter your Bucket, Access Key, Secret Key, and Region
4. Upload files normally — they'll be served from S3

== Elementor Widgets ==

After activating Elementor:
* SCM Asset Grid — Displays a filterable asset grid
* SCM Search Box — Full search bar
* SCM Category List — Sidebar taxonomy list

All widgets are in the "Stock Marketplace" category in the Elementor panel.

== REST API Endpoints ==

Base URL: `https://yoursite.com/wp-json/scm/v1/`

| Endpoint                      | Method | Auth     | Description            |
|-------------------------------|--------|----------|------------------------|
| /assets                       | GET    | Public   | List assets            |
| /assets/{id}                  | GET    | Public   | Single asset           |
| /categories                   | GET    | Public   | All categories         |
| /favorites                    | GET    | Required | User favorites list    |
| /favorites/{asset_id}         | POST   | Required | Toggle favorite        |
| /collections                  | GET    | Required | User collections list  |
| /collections                  | POST   | Required | Create collection      |
| /downloads/{asset_id}         | GET    | Required | Get download URL       |

== Changelog ==

= 1.0.0 =
* Initial release

== Frequently Asked Questions ==

= Does it require WooCommerce? =
No. WooCommerce is optional. Free assets work without it. Premium payments require WooCommerce.

= Can I use it with any theme? =
Yes. The plugin has its own templates and CSS. It works with any theme.

= Does it support subscriptions? =
Yes. Compatible with WooCommerce Subscriptions, Paid Memberships Pro, and MemberPress.
