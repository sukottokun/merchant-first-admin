#!/usr/bin/env bash
# Exercises the menu restructure against a simulated WP + WooCommerce admin menu.
# Needs any PHP 7.4+ binary. WordPress Studio ships one at ~/.studio/php-bin/*/php
set -euo pipefail
PHP="${PHP:-$(command -v php || echo "$HOME/.studio/php-bin/8.4.21/php")}"
HERE="$(cd "$(dirname "$0")" && pwd)"

"$PHP" -l "$HERE/../merchant-first-admin.php"
for scenario in store posts pages shopmgr legacy; do
  echo
  "$PHP" "$HERE/menu-harness.php" "$scenario"
done
