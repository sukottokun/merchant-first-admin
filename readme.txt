=== Merchant-First Admin ===
Contributors: sukottokun
Tags: woocommerce, admin, admin-menu, ecommerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.9.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reshapes wp-admin around store operations: WooCommerce on the top level, everything WordPress folded behind one door.

== Description ==

The stock WordPress admin menu makes a store look like a blog with a shop bolted on. Posts, Pages and Comments sit above the things a merchant actually opens every day.

This plugin reorders that menu around store operations. Orders, Products, Customers and Payments move to the top level; Posts, Pages, Media, Comments, Appearance, Plugins, Users, Tools and Settings fold into a single **WordPress** drawer.

Placement is by rule rather than by name. Only the merchant items and the core WordPress drawer are named in config. Everything else, including whatever extension is installed next, keeps the position it registered and stays in the store group. Anything left under WooCommerce is swept into an **Extensions** menu, so no extension can register a menu that never appears anywhere.

Menus are located by runtime discovery rather than hardcoded slugs, so the plugin survives WooCommerce version changes and handles both HPOS and legacy order screens.

Built for WooCommerce sales demos.

= Other changes =

* Submenus are click-to-expand accordions rather than hover flyouts.
* Home lands on Analytics Overview instead of the stock Home screen.
* Admin notices are hidden on store screens.
* The `shop_manager` role never sees the WordPress drawer.

== Installation ==

1. Upload the plugin zip through **Plugins → Add New → Upload Plugin**.
2. Activate it.

Alternatively, drop `merchant-first-admin.php` into `wp-content/mu-plugins/`.

== Frequently Asked Questions ==

= How do I get the normal menu back? =

Add `?mfa=off` to any admin URL. The setting is per user and persists. There is also a toggle in the admin bar. To disable it for the whole site, define `MFA_DISABLE` as `true` in `wp-config.php`.

= A menu is not where I expected. =

Add `?mfa=debug` to any admin URL for a plain-text dump of the live menu registrations and what the plugin resolved.

= Is this on WordPress.org? =

No. It updates itself from GitHub releases, which is why it carries an `Update URI` header. WordPress.org does not permit that header on plugins it hosts.

== Changelog ==

= 0.9.7 =
* Fixed: Updates was missing from the WordPress drawer, leaving no menu route to update-core.php.
* The admin bar updates indicator is no longer hidden.

= 0.9.6 =
* Tidied code comments. No functional change.

= 0.9.5 =
* Added readme.txt.

= 0.9.4 =
* Clean against WordPress and Automattic VIP coding standards.
* Styles and scripts now go through the enqueue pipeline.

= 0.9.3 =
* Updates delivered from GitHub releases.

= 0.9.2 =
* Wordmark no longer resized by third-party admin stylesheets.

= 0.9.1 =
* Submenus became accordions instead of hover flyouts.

= 0.9.0 =
* First numbered release.
