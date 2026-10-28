#!/usr/bin/env bash
set -euo pipefail

tools_dir="$(cd "$(dirname "$0")" && pwd)"

for tool in \
  '9.6.38 6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7' \
  '10.5.66 42bcac97bbf9fb1aecf5a7d6a1b37123a11e7e295458978f0e268999f6d9e50f' \
  '11.5.57 c704fdd37c6c6ab7adb386f31b4e186cd51156801fa9028b5ea8099a2cd7f106'; do
  version="${tool%% *}"
  digest="${tool#* }"
  destination="$tools_dir/phpunit-${version%%.*}.phar"
  if [[ ! -f "$destination" ]]; then
    curl --fail --location --silent --show-error "https://phar.phpunit.de/phpunit-$version.phar" --output "$destination"
  fi
  actual="$(shasum -a 256 "$destination")"
  if [[ "${actual%% *}" != "$digest" ]]; then
    echo "PHPUnit $version checksum mismatch" >&2
    exit 1
  fi
done
