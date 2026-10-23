# Low Yearly Archives PHPUnit tests

These tests use PHP 7.4-compatible syntax and pinned PHPUnit 9.6.38 on every runtime. Tool PHARs are local and ignored; production dependencies and sources are unchanged.

```sh
bash tests/.phpunit/install.sh
./tests/.phpunit/run-phpunit.sh -c phpunit.xml
python3 tests/run-matrix.py
```

Set `PHP_BIN` to an explicit PHP executable for the launcher. The matrix uses the Herd binaries under `~/Library/Application Support/Herd/bin` without changing global PHP or services; `HERD_PHP_DIRECTORY` can override that directory. Every matrix run creates a fresh directory under `tests/reports/`, executes the complete configured suite on PHP 7.4–8.5, and rejects missing/empty JUnit reports. A nonzero exit is expected while documented source regressions remain.

The EE doubles mirror the contracts inspected in `ee_repo_2/system/ee/legacy`: template fetch normalization, Active Record chaining/results, fieldtype `content_id()`, localize-month language keys, helper file IO and the CP service boundaries. Parser rendering and actual SQL execution are outside these unit tests; their passed data and operations are asserted directly. Native subprocess checks execute the original PHAR, bridge dispatcher, direct-access guard, missing-file paths and self-extracting fallback. Linting archive entries uses verified byte-identical temporary copies because PHP CLI `-l` does not accept `phar://` input filenames.

Run method line reports with byte-identical archive measurement copies:

```sh
PHP82='/Users/tomjaeger/Library/Application Support/Herd/bin/php82'
"$PHP82" tests/support/prepare-coverage.php
REPORT="$(mktemp -d /private/tmp/low-yearly-coverage.XXXXXX)"
LOW_YEARLY_MEASURE_ARCHIVE=1 "$PHP82" \
  -d zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-82-arm64.so \
  -d xdebug.mode=coverage tests/.phpunit/phpunit-9.phar -c phpunit.xml \
  --coverage-clover "$REPORT/clover.xml" --coverage-php "$REPORT/coverage.php"
python3 /Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon_method_coverage_report.py \
  --repo-root "$PWD" --target low_yearly_archives --include-magic \
  --coverage-clover "$REPORT/clover.xml" --reuse-clover --json
python3 /Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon_method_coverage_report.py \
  --repo-root "$PWD" --target tests/.coverage-source --include-tests --include-magic \
  --coverage-clover "$REPORT/clover.xml" --reuse-clover --json
```

The copies are hashed against the archive and listed in `SOURCE_MANIFEST.json`; they are never a rebuilt PHAR. Native and measurement coverage are separate runs. The native PHAR stub remains in the coverage filter. All 27 source units remain visible, including unexecuted files. Always use absolute coverage output paths: the PHAR's `Phar::interceptFileFuncs()` affects relative file operations.

The skill's Clover helper reports method executable lines; its `branch_percent: null` and `at_target` label do **not** establish actual branch coverage. Independently collect Xdebug's raw basic blocks and `out`/`out_hit` exits:

```sh
REPORT="$(mktemp -d /private/tmp/low-yearly-branches.XXXXXX)"
LOW_YEARLY_RAW_COVERAGE="$REPORT/native.json" "$PHP82" \
  -d zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-82-arm64.so \
  -d xdebug.mode=coverage tests/.phpunit/phpunit-9.phar -c phpunit.xml
```

The collector merges only equal source/opcode graphs within one PHP process and retains raw path arrays, PHP/Xdebug versions and source hashes. Do not combine it with PHPUnit's `--coverage-*` options. Its zero-count synthetic terminal exits and top-level blocks require interpretation; zero counts are retained, never discarded or presented as covered. Child subprocess behavior is tested but child coverage is not merged into the parent graph. Missing coverage and untouched methods remain incomplete.

The strict error handler fails every source diagnostic, including warnings/notices/deprecations under `@`. Active support diagnostics and suppressed support deprecations also fail with separate attribution. Source defects are regression tests with ordinary correct-behavior assertions, so they stay red rather than expecting an error or deprecation to pass.
