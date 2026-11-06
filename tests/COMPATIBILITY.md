Current test scope (2026-11-05): direct tests of bundled Foundation internals
are removed. Plugin behavior, the loose license view, loose-source compilation,
package metadata and strict diagnostic/process controls remain. Shared bootstrap,
source manifests and the unchanged PHAR are retained for indirect add-on execution.
Earlier test counts and Foundation failures below are historical.

# Current add-on compatibility scope

The pinned tests baseline is `1cbdc3dac0cf51dff4976deff5341ac68f49a6f8`;
local develop is `0f61057a193e7a7e82156038bfe892f87f95bbaa`.

The current product change is the plugin `_explode_param` null-to-empty guard.
The distributed Foundation PHAR is restored byte-for-byte to develop/original
SHA256 `3959e83638da756c85860d736bb13b52d735526ec817f0bfad4f7d7383ed5356`.
No Foundation source, build, copied decoder, loader repair or new Foundation-only
regression is part of this candidate. Direct baseline Foundation tests are removed
because the shared library has its own suite. Retained add-on diagnostics and assertion failures remain visible.

Run the complete suite with an explicit runtime:

```sh
PHP_BIN='/absolute/path/to/php' tests/.phpunit/run-phpunit.sh -c phpunit.xml
```

The checked launcher preserves pinned PHPUnit 9.6.38 and sets error_reporting=-1,
display_errors=stderr and display_startup_errors=1 before discovery/providers.
The durable handler and parent/native ownership checks still reject caught or
ignored diagnostics, startup/native stderr and unexpected process outcomes.
Generic transport/deadline/output and caller-prelude controls remain. No product
diagnostic is filtered or expected away. No tests are excluded to obtain green.
SOURCE_MANIFEST binds original archive members plus the current loose plugin.
No coverage collection or global PHP/INI/service change is required.

Earlier Foundation implementation/results are historical at checkpoint
`4b9b5f90140edc14026efa46b3b9db32a9da8367`; recovery snapshots and complete
scope-correction results are retained in the campaign evidence. Those prior green
results do not certify the restored original Foundation or this current candidate.
Current final matrix results are reported externally without weakening retained add-on
oracles. CLI fixtures do not establish live EE, SQL, templates, licensing, browser
or other filesystem/platform behavior.
