#!/usr/bin/env bash
# Build the installable zip and publish it as a GitHub release.
#
# The zip must unpack to merchant-first-admin/ for WordPress to treat it as an
# update rather than a new plugin — which is why we build it by hand instead of
# letting GitHub generate a zipball.
#
# Usage: ./bin/release.sh 0.9.3
set -euo pipefail

VERSION="${1:?usage: ./bin/release.sh <version>   e.g. ./bin/release.sh 0.9.3}"
SLUG="merchant-first-admin"
HERE="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-$(command -v php || echo "$HOME/.studio/php-bin/8.4.21/php")}"

HEADER=$(grep -m1 '^ \* Version:' "$HERE/$SLUG.php" | tr -d ' ' | cut -d: -f2)
CONST=$(grep -m1 "const VERSION" "$HERE/$SLUG.php" | cut -d"'" -f2)
for got in "$HEADER" "$CONST"; do
  if [ "$got" != "$VERSION" ]; then
    echo "Version mismatch: asked for $VERSION but the plugin says $HEADER (header) / $CONST (const)." >&2
    echo "Bump both in $SLUG.php first." >&2
    exit 1
  fi
done

"$PHP" -l "$HERE/$SLUG.php"
"$HERE/tests/run.sh" >/dev/null

STAGE="$(mktemp -d)"
mkdir -p "$STAGE/$SLUG"
cp "$HERE/$SLUG.php" "$STAGE/$SLUG/"
( cd "$STAGE" && zip -qr "$SLUG.zip" "$SLUG" )

gh release create "v$VERSION" "$STAGE/$SLUG.zip" \
  --repo "$(gh repo view --json nameWithOwner --jq .nameWithOwner)" \
  --title "v$VERSION" \
  --generate-notes

cp "$STAGE/$SLUG.zip" "$HOME/Desktop/$SLUG.zip"
rm -rf "$STAGE"
echo "Released v$VERSION. Zip also copied to ~/Desktop/$SLUG.zip"
