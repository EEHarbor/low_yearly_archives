# Low Yearly Archives Foundation build

This source is processed Foundation 4.10.0 from `packettide/tools` commit
`cc1d34bd8db0119bbe2b9e38ebb337add61c7fbb`. The substitutions recorded in
`provenance.json`, including the exact shipped validation JavaScript, reproduce
all 22 original archive members byte for byte before the compatibility edits.
The source was obtained from Git objects in the read-only tools repository; no
shared source or broad gulp task was changed or executed.

The scoped builder follows the established `foundation/phar.php` process:
`Phar::buildFromDirectory`, the original `low_yearly_archives.phar` alias, SHA-256 signature,
uncompressed members, original mixed 0755/0644 member modes and null
metadata. Raw manifest flags provide the original permissions; PharFileInfo
projects away write bits when phar.readonly is enabled. The builder and archive
regression read the stored flags directly and check every member by name. The extraction stub retains its original behavior and final CRLF;
redundant directory creation is guarded and its manifest offset LEN is
recalculated. Linked extraction roots/containers are refused before creation or
cleanup. Stale hidden/nested files are removed only within the extraction tree;
contained symbolic links are unlinked without following their separate targets. New build timestamps are expected to change archive hashes.

Run with an explicit binary and process-local settings:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php85" \
  -d error_reporting=-1 -d display_errors=stderr -d display_startup_errors=1 \
  -d phar.readonly=0 build/foundation/build.php
```

The builder verifies member bytes/modes/layout before replacing this add-on's
archive and refreshing `tests/SOURCE_MANIFEST.json`. It releases the final
PharFileInfo and Phar references before reopening the renamed archive, avoiding
a PHP 8.5 alias collision. The configured ArchiveBuildTest runs an actual complete owned-copy rebuild
under the selected native PHP binary and verifies the refreshed manifest. Configured tests compare the actual distributed archive with every
build member, original layout and complete source inventory.

`SerializedData` retains native `unserialize` types, object callbacks and shared
references. Historical escaped `S` string tokens are normalized to ordinary
byte-counted `s` tokens before native hydration. The token normalizer is adapted
from Fieldpack `e1b07ae668c405ff214d006f718d3f56eb07c571`,
`fieldpack/ff2ee2/serialization.php`; ordinary string contents and custom `C`
payloads are skipped by their byte lengths. Disk/global-setting bytes are not
rewritten, and native reference numbers and callbacks are retained. Complete
native value prefixes followed by unused bytes retain the PHP 7.4 read contract.
It handles only the native parser's exact malformed-input messages
at its own unserialize file/line, returning false on that documented failure
contract. Unexpected diagnostics reach the previous handler and it is restored
in finally. Error reporting stays enabled; no suppression operator is added.

Native controller/storage corrections are independently retargeted from this
author's Geotagger source after exact original-member parity. Only the new base
MCP data declaration is removed; the scoped dynamic-property attribute preserves
bare/public/protected/private/magic/ArrayObject consumers. Original declarations
remain. The serializer accepts signed counts, preserves normalized prefixes and
native malformed-tail callbacks, native integer clamps and PHP74 context.
Global settings retain truthy native values/direct ArrayAccess and non-strict
carriers; valid array/string offsets stay readable while invalid/non-indexable
getSetting inputs take the quiet null fallback. No known-bad warning/TypeError
is restored as a supported input contract and no diagnostic allowance is added.

Modern navigation canonicalizes an active-map root alias to the same index key
used when constructing its header. An unset active title uses null, so valid
textual zero remains the heading and breadcrumb trigger. Actual archived
root/child/zero-label regressions cover EE3–5 and inherited EE6.

Wire lengths are compared as decimal text with the available input before integer
conversion. Oversized and negative custom lengths retain the untouched malformed
remainder for native parser cleanup; signed zero and leading zeroes remain valid.
Direct extraction cleanup removes root links themselves, including dangling links
and roots with native trailing separators, without traversing their targets.
POSIX backslashes remain literal path characters. Cache creation rejects parents
without directory search permission before calling `mkdir`.
