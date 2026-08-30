# Merchant-First Admin

A single-file WordPress plugin that reorders wp-admin around store operations. WooCommerce on the top level, WordPress behind one door. Built for Woo demos.

```
Home ▸                        WordPress ▾
   Overview · Products           Dashboard
   Revenue · Orders · Stock      Posts
Orders                           Pages
Subscriptions                    Media
Products                         Comments
Customers                        Links
Marketing                        Stats
Payments                         Appearance
Wholesale  ◂ unnamed             Plugins
Extensions ▸                     Users
Settings                         Tools
──────────                       Site settings
WordPress ▸                      Jetpack
```

Placement is by rule, not by name. Only the merchant items and the WordPress drawer are named in config. Everything else, including whatever extension you install next, keeps the position it registered and stays in the store group. Anything left under WooCommerce lands in Extensions, so nothing an extension registers can go missing.

Menus are found at runtime rather than by hardcoded slugs, so this survives Woo version changes and handles both HPOS and legacy order screens. `shop_manager` never sees the WordPress drawer.

Home opens Analytics Overview instead of the stock Home screen. Submenus are click-to-expand accordions, not hover flyouts.

## Install

Download the zip from [the latest release](https://github.com/sukottokun/merchant-first-admin/releases/latest), upload it under **Plugins → Add New → Upload Plugin**, and activate. Or drop `merchant-first-admin.php` into `wp-content/mu-plugins/`.

After the first install it updates itself from GitHub releases. New versions appear under **Dashboard → Updates** like any other plugin.

## Two URLs to know

* `…/wp-admin/index.php?mfa=off` reverts to the standard menu. It works per user and stays saved. There is also a "Merchant view" toggle in the admin bar.
* `…/wp-admin/index.php?mfa=debug` dumps the live `$menu` and `$submenu` as plain text. Reach for it when a menu is not where you expected.

Also `?mfa=on` and `?mfa=notices`. All are administrators only. `define( 'MFA_DISABLE', true )` turns it off site wide.

## Configure

The properties at the top of the class: `$order`, `$find_top`, `$promote`, `$invent`, `$rename`, `$icons`, `$extensions`, `$drawer`, `$hide_drawer_for`.

## Tests and linting

```bash
./tests/run.sh     # menu restructure, five scenarios
composer install   # once
composer lint      # PHPCS, WordPress and Automattic VIP standards
```

Clean under both PHPCS and [Plugin Check](https://wordpress.org/plugins/plugin-check/), with one expected finding. Plugin Check reports `plugin_updater_detected` because WordPress.org does not allow the `Update URI` header on plugins it hosts. This one is not hosted there and updates from GitHub on purpose. Putting it on WordPress.org would mean dropping the updater.

## Releasing

Bump `Version:` and `const VERSION`, then:

```bash
./bin/release.sh 0.9.6
```

It refuses to run unless both versions match, then lints, runs the tests, builds a zip that unpacks to `merchant-first-admin/`, and attaches it to the release. The folder name matters. GitHub's own zipball unpacks to `owner-repo-sha/`, which WordPress installs as a second plugin instead of an update.

GPL-2.0-or-later.
