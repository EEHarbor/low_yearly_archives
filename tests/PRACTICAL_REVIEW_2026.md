# Practical test review — 2026-10-28

This update keeps tests focused on ordinary supported add-on behavior. Dedicated PHAR/Foundation internal contracts belong in the Foundation repository. Production code and bundled dependencies are unchanged.

- Use a portable PHP default in the test entry points.

The complete retained suites ran under all seven PHP versions with strict diagnostics. Errors and failures remain visible for the current source; these tests do not certify compatibility or a green suite. Earlier campaign measurements are historical and were not recomputed for this review.

| PHP | Suite | Tests | Assertions | Errors | Failures |
| --- | --- | ---: | ---: | ---: | ---: |
| 7.4 | main | 24 | 80 | 0 | 0 |
| 8.0 | main | 24 | 80 | 0 | 0 |
| 8.1 | main | 24 | 80 | 0 | 0 |
| 8.2 | main | 24 | 80 | 0 | 0 |
| 8.3 | main | 24 | 80 | 0 | 0 |
| 8.4 | main | 24 | 80 | 0 | 0 |
| 8.5 | main | 24 | 80 | 0 | 0 |

Test code revision: `1faae55e5ac9783904adad18908cf517ac5d96cd`. Validation uses isolated EE collaborators and does not replace an installed ExpressionEngine/database/browser integration run.
