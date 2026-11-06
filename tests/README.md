Current test scope (2026-11-05): direct tests of bundled Foundation internals
are removed. Plugin behavior, the loose license view, loose-source compilation,
package metadata and strict diagnostic/process controls remain. Shared bootstrap,
source manifests and the unchanged PHAR are retained for indirect add-on execution.
Earlier test counts and Foundation failures below are historical.

Terminal capture follow-up: a killed direct child is polled for at most one second before proc_close, retaining actual terminal status separately from the close return. Already observed terminal statuses are preserved on PHP7.4. An unobserved terminal state is an explicit rejected outcome, never successful evidence. Previous capture matrices with running:true/proc_close9 remain superseded history.

Current scope correction (2026-10-26): the distributed Foundation archive and
shared loader are original develop bytes. Only add-on fixes and strict runner work
remain; Foundation is exercised only through retained add-on behavior tests. The
compatibility document gives the current boundary. Earlier Foundation repair/results below are
historical and are not a current passing claim.

Current production compatibility work and validation: see [COMPATIBILITY.md](COMPATIBILITY.md). Historical source-red/coverage evidence below remains a record of the earlier tests-only campaign.

# Low Yearly Archives PHPUnit tests

These tests use PHP 7.4-compatible syntax and pinned PHPUnit 9.6.38 on every runtime. Tool PHARs are local and ignored; production compatibility changes are documented in COMPATIBILITY.md.

```sh
bash tests/.phpunit/install.sh
./tests/.phpunit/run-phpunit.sh -c phpunit.xml
python3 tests/run-matrix.py
```

Set `PHP_BIN` to an explicit PHP executable for the launcher. The matrix uses the Herd binaries under `~/Library/Application Support/Herd/bin` without changing global PHP or services; `HERD_PHP_DIRECTORY` can override that directory. Every matrix run creates a fresh directory under `tests/reports/`, executes the complete configured suite on PHP 7.4–8.5, and rejects missing/empty JUnit reports. A nonzero exit is expected while documented source regressions remain.

Every native configuration, lint and execution attempt also retains an exclusive `tests/reports/native-*` slot: literal command bytes, owner/PID/deadline, complete binary stdout/stderr and post-reap terminal status. The process joins issued slots and verifies retained bytes at shutdown even if callers ignore a result or catch a failure. These files survive disposal of the child temporary tree. Earlier scope-cleanup matrices retained outer streams only and do not establish this child-capture contract.

The EE doubles mirror the contracts inspected in `ee_repo_2/system/ee/legacy`: template fetch normalization, Active Record chaining/results, fieldtype `content_id()`, localize-month language keys, helper file IO and the CP service boundaries. Parser rendering and actual SQL execution are outside these unit tests; their passed data and operations are asserted directly. Retained native subprocess checks compile the four loose PHP sources and exercise the plugin direct-access guard and strict process controls. The plugin behavior tests load their real distributed PHAR parent indirectly. Each child has a 15-second deadline, file-backed stdout/stderr capped at 128 KiB per stream, and its own temporary tree removed in `finally`.

Historical coverage workflow (not collected for this compatibility repair):
all new validation uses the checked launcher. The old measurement commands below
record the earlier campaign and do not establish current strict publication proof.

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

The copies are hashed against the archive and listed in `SOURCE_MANIFEST.json`; they are byte-identical to the distributed PHAR. Native and measurement coverage are separate runs. The native PHAR stub remains in the coverage filter. All 27 source units remain visible, including unexecuted files. Always use absolute coverage output paths: the PHAR's `Phar::interceptFileFuncs()` affects relative file operations.

The skill's Clover helper reports method executable lines; its `branch_percent: null` and `at_target` label do **not** establish actual branch coverage. Independently collect Xdebug's raw basic blocks and `out`/`out_hit` exits:

```sh
REPORT="$(mktemp -d /private/tmp/low-yearly-branches.XXXXXX)"
LOW_YEARLY_RAW_COVERAGE="$REPORT/native.json" "$PHP82" \
  -d zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-82-arm64.so \
  -d xdebug.mode=coverage tests/.phpunit/phpunit-9.phar -c phpunit.xml
```

The collector merges only equal source/opcode graphs within one PHP process and retains raw path arrays, PHP/Xdebug versions, EE/request context, timestamps and source hashes. Do not combine it with PHPUnit's `--coverage-*` options. Its zero-count synthetic terminal exits and top-level blocks require interpretation; zero counts are retained, never discarded or presented as covered. Native children retain separate `native-child-*.json` graphs beside the parent report; contexts and runtimes are never merged. Lint subprocesses and the early direct-access guard do not produce child graphs. Missing coverage and untouched methods remain incomplete.

The strict error handler fails every source diagnostic, including warnings/notices/deprecations under `@`. Active support diagnostics and suppressed support deprecations also fail with separate attribution. Source defects are regression tests with ordinary correct-behavior assertions, so they stay red rather than expecting an error or deprecation to pass.

The matrix owns a separate POSIX process group and temporary directory for each suite. A suite timeout or keyboard interruption stops the full inherited group before removing that directory; normal completion also stops leftover descendants. Native PHP trees use that owned directory when the matrix supplies it. Verify the outer lifecycle separately with `python3 -m unittest discover -s tests -p test_process.py -v`; its bounded timeout and normal-exit vectors check child activity and file cleanup.

Historical Native EE2 dynamic-view probes loaded AbstractBridge/EE2 directly from the distributed PHAR while using an owned temporary PATH_THIRD/views directory. They check written bytes, loader arguments, original member paths/hashes and cleanup without writing production views. Those child graphs retain the original source provenance and remain separate from the parent Clover report. The adapter assertions do not evaluate templates, exercise the ordinary autoloader or replace installed-EE integration.
