#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/revertshield.zip}"
if [[ "$OUT" != /* ]]; then
  OUT="$(pwd)/$OUT"
fi
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/revertshield"

for file in revertshield.php index.php uninstall.php readme.txt LICENSE; do
  cp "$ROOT/$file" "$TMP/revertshield/"
done

for dir in src assets languages; do
  cp -R "$ROOT/$dir" "$TMP/revertshield/"
done

find "$TMP/revertshield" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

rm -f "$OUT"
(
  cd "$TMP"
  zip -qr "$OUT" revertshield
)

echo "$OUT"
