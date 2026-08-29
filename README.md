# Merchant-First Admin

A single-file WordPress plugin that reshapes `wp-admin` around store operations: WooCommerce tasks on the top level, everything WordPress folded behind one **WordPress** door.

Built for WooCommerce sales demos, where the stock admin menu makes a store look like a blog with a shop bolted on.

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

## What it does

| | |
|---|---|
| **Promotes** | Orders, Customers and Settings out of the WooCommerce submenu onto the top level |
| **Invents** | A top-level **Payments** item pointing at WooCommerce → Settings → Payments |
| **Renames** | WooCommerce → Home, Analytics → Reports, Dashboard → WordPress |
| **Folds** | Posts, Pages, Media, Comments, Appearance, Plugins, Users, Tools, Settings and Jetpack into the WordPress drawer |
| **Redistributes** | Leftover Woo submenu items — Coupons to Marketing, Status and Extensions to Settings |
| **Tidies** | One deliberate separator, a cleaned admin bar, no `Howdy,`, no admin notices on store screens, no footer |
| **Hides** | Itself, from the Plugins list |

Menus are located by **runtime discovery**, not hardcoded slugs — the config matches against slug needles with exact matches winning over substrings, so it survives Woo version changes and works with either HPOS (`wc-orders`) or legacy (`edit.php?post_type=shop_order`) order screens.

### The drawer stays usable

The item that owns the current screen is deliberately *not* folded away. Click **Posts** and it pops back onto the top level with its own Categories and Tags submenu intact, rather than stranding you in a section whose sub-pages are unreachable.

### Role awareness

`shop_manager` never sees the WordPress drawer at all — the whole of WordPress-land is removed for that role. Configure via `$hide_drawer_for`.

## Install

Upload the zip through **Plugins → Add New → Upload Plugin**, then activate.

The plugin removes itself from the Plugins list once active. That is intentional — it keeps demos clean. To find it again, use `?mfa=off` (below), or deactivate it over SFTP/WP-CLI.

Prefer it as a must-use plugin? Drop `merchant-first-admin.php` straight into `wp-content/mu-plugins/`. It behaves identically and cannot be deactivated from the UI at all.

## Escape hatches

Administrators only. Append to any admin URL:

| URL | Effect |
|---|---|
| `?mfa=off` | Revert to the stock WordPress menu. Per-user, persists. |
| `?mfa=on` | Turn it back on. |
| `?mfa=notices` | Show admin notices for this pageload. |
| `?mfa=debug` | Dump the live `$menu` / `$submenu` arrays as plain text and stop. |

There is also a **Merchant view: on** toggle in the admin bar.

Defining `MFA_DISABLE` as `true` in `wp-config.php` disables it site-wide.

`?mfa=debug` is the one to reach for when a menu doesn't land where you expected — it prints every registered slug and capability, plus what the plugin resolved, so the config arrays at the top of the file can be tuned against reality.

## Configuration

Everything lives in the properties at the top of the class:

- `$order` — top-level order, by internal key
- `$find_top` — slug needles used to locate each menu
- `$promote` — items lifted out of the WooCommerce submenu
- `$invent` — brand-new top-level items pointing at an existing screen
- `$rename` / `$icons` — titles and dashicons
- `$drawer` — what gets folded into the WordPress drawer, in drawer order
- `$hide_drawer_for` — roles that never see the drawer

## Tests

```bash
./tests/run.sh
```

`tests/menu-harness.php` stubs the WordPress globals and functions, builds a realistic WP 6.x + WooCommerce 9.x + Jetpack + Wholesale menu, runs the restructure, and emulates `wp-admin/menu.php`'s custom-ordering block. It covers five scenarios:

| Scenario | Asserts |
|---|---|
| `store` | The full merchant-first menu |
| `posts` | Posts pops back to the top level with its submenu intact |
| `pages` | Same, discriminating on `post_type` |
| `shopmgr` | The drawer is removed entirely for `shop_manager` |
| `legacy` | Orders resolves on pre-HPOS WooCommerce |

Needs any PHP 7.4+ binary; the runner falls back to the one WordPress Studio ships at `~/.studio/php-bin/*/php`.

The harness caught four real bugs during development: `woocommerce` matching `woocommerce-marketing`, a duplicate Home and a misfiled Coupons under Settings, doubled separators, and `$typenow` breaking current-screen detection on the Posts list.

## Status

Verified against the harness, and syntax-checked on PHP 8.4. **Not yet run against a live WordPress install** — the simulated menu is a good approximation, not the real thing. Expect to tune the config arrays against a `?mfa=debug` dump on first deploy.

## License

GPL-2.0-or-later.
