# Merchant-First Admin

A single-file WordPress plugin that reshapes `wp-admin` around store operations: WooCommerce on the top level, everything WordPress folded behind one door. Built for Woo sales demos.

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

Placement is by rule, not by name. Only the merchant items above and the core WordPress drawer are listed in config; everything else — Wholesale, and whatever extension you install next — keeps the position it registered and stays in the store group. Anything left under WooCommerce is swept into **Extensions**, so no extension can register a menu that never appears. Settings always closes the store group.

Menus are found by runtime discovery rather than hardcoded slugs, so it survives Woo version changes and handles both HPOS and legacy order screens. The item owning the current screen stays on the top level, keeping its own submenu reachable. `shop_manager` never sees the WordPress drawer.

**Home** points at Analytics Overview rather than Woo's stock Home screen — Overview already renders the performance tiles and charts a merchant wants first, where stock Home is an onboarding checklist. Trim it to the metrics you want from the ⋮ menus on the Performance and Charts sections. The analytics sub-reports hang off Home, so there is no separate Reports item; two items pointing at the same route made WordPress highlight both. The trade-off: Woo's onboarding tasks are no longer one click away.

Submenus are click-to-expand accordions rather than hover flyouts. The section you are in opens on load; a caret toggles any other without navigating, and clicking the label still follows the link. The collapsed icon rail keeps its flyouts, since that is the only thing that works at that width.

## Install

Grab the zip from [the latest release](https://github.com/sukottokun/merchant-first-admin/releases/latest), upload it via **Plugins → Add New → Upload Plugin**, then activate. Or drop `merchant-first-admin.php` into `wp-content/mu-plugins/`.

## Updates

The plugin reports its own updates from GitHub releases, so after the first install you update it from **Dashboard → Updates** like any other plugin — no re-uploading. This uses core's `Update URI` header and the `update_plugins_{$hostname}` filter, available since WordPress 5.8, rather than an update library. Release checks are cached for 12 hours.

To publish one:

```bash
# bump Version: and const VERSION in merchant-first-admin.php, then
./bin/release.sh 0.9.3
```

The script refuses to run if the version you pass doesn't match both places in the plugin file, lints, runs the tests, builds a zip that unpacks to `merchant-first-admin/`, and attaches it to the release. That folder name matters: GitHub's generated zipball unpacks to `owner-repo-sha/`, which WordPress installs as a *separate* plugin instead of an update.

## Two URLs to know

* `…/wp-admin/index.php?mfa=off` — This instantly reverts to the standard menu. It works per user and stays saved. There is also a "Merchant view: on" toggle in the admin bar.
* `…/wp-admin/index.php?mfa=debug` — This dumps the live `$menu` and `$submenu` as plain text.

Also `?mfa=on` and `?mfa=notices`. All are administrators only. `define( 'MFA_DISABLE', true )` disables it site-wide.

## Configure

The properties at the top of the class: `$order`, `$find_top`, `$promote`, `$invent`, `$rename`, `$icons`, `$extensions`, `$drawer`, `$hide_drawer_for`.

## Tests

```bash
./tests/run.sh
```

Stubs the WordPress globals and runs the restructure against a simulated WP + Woo menu across five scenarios. Needs any PHP 7.4+ binary.

## Status

Not yet run against a live WordPress install. Expect to tune the config against a `?mfa=debug` dump on first deploy.

GPL-2.0-or-later.
