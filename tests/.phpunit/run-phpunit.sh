#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "$0")" && pwd)"
php_bin="${PHP_BIN:-/Users/tomjaeger/Library/Application Support/Herd/bin/php82}"
expected='6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7'
actual="$(shasum -a 256 "$script_dir/phpunit-9.phar")"
if [[ "${actual%% *}" != "$expected" ]]; then
  echo 'Missing or unverified PHPUnit 9.6.38; run tests/.phpunit/install.sh.' >&2
  exit 1
fi

exec "$php_bin" -d error_reporting=-1 -d display_errors=1 "$script_dir/phpunit-9.phar" "$@"
