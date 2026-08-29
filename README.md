# Merchant-First Admin

A single-file WordPress plugin that reshapes `wp-admin` around store operations: WooCommerce on the top level, everything WordPress folded behind one door. Built for Woo sales demos.

```
Home                          WordPress ▾
Orders                          Dashboard
Products                        Posts
Customers                       Pages
Reports                         Media
Marketing                       Comments
Payments                        Appearance
Wholesale                       Plugins
Settings                        Users
──────────                      Tools
WordPress ▸                     Site settings
                                Jetpack
```

Menus are found by runtime discovery rather than hardcoded slugs, so it survives Woo version changes and handles both HPOS and legacy order screens. The item owning the current screen stays on the top level, keeping its own submenu reachable. `shop_manager` never sees the WordPress drawer.

## Install

Upload the zip via **Plugins → Add New → Upload Plugin**, then activate. Or drop `merchant-first-admin.php` into `wp-content/mu-plugins/`.

## Two URLs to know

* `…/wp-admin/index.php?mfa=off` — This instantly reverts to the standard menu. It works per user and stays saved. There is also a "Merchant view: on" toggle in the admin bar.
* `…/wp-admin/index.php?mfa=debug` — This dumps the live `$menu` and `$submenu` as plain text.

Also `?mfa=on` and `?mfa=notices`. All are administrators only. `define( 'MFA_DISABLE', true )` disables it site-wide.

## Configure

The properties at the top of the class: `$order`, `$find_top`, `$promote`, `$invent`, `$rename`, `$icons`, `$drawer`, `$hide_drawer_for`.

## Tests

```bash
./tests/run.sh
```

Stubs the WordPress globals and runs the restructure against a simulated WP + Woo menu across five scenarios. Needs any PHP 7.4+ binary.

## Status

Not yet run against a live WordPress install. Expect to tune the config against a `?mfa=debug` dump on first deploy.

GPL-2.0-or-later.
