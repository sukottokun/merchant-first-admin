# Merchant-First Admin

A simple WordPress plugin that cleans up the WooCommerce dashboard to provide a more "merchant-first" user experience. Built for Woo demos.

Placement is by heuristic: if Woo or wc_ is in the name. Home opens Analytics Overview instead of the stock Home screen. Submenus are click-to-expand accordions, not hover flyouts.

You can switch between this view and the default admin view via the `Merchant View` control at the top of the dashboard.

## Install

Download the zip from [the latest release](https://github.com/sukottokun/merchant-first-admin/releases/latest), upload it under **Plugins → Add New → Upload Plugin**, and activate. Or drop `merchant-first-admin.php` into `wp-content/mu-plugins/`.

New versions appear under **Dashboard → Updates** like any other plugin.

## Two URLs to know

* `…/wp-admin/index.php?mfa=off` reverts to the standard menu. It works per user and stays saved. There is also a "Merchant view" toggle in the admin bar.
* `…/wp-admin/index.php?mfa=debug` dumps the live `$menu` and `$submenu` as plain text. Reach for it when a menu is not where you expected.

Also, `?mfa=on` and `?mfa=notices`. All are administrators only. `define( 'MFA_DISABLE', true )` turns it off site-wide.

## Tests and linting

```bash
./tests/run.sh     # menu restructure
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
