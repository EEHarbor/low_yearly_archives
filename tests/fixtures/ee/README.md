`Input.php` is an unmodified ExpressionEngine core fixture from
`/Users/tomjaeger/Sites/ee_repo_2/system/ee/legacy/core/Input.php`.
Its original copyright and Apache 2.0 license header are retained.

Native license regressions instantiate `EE_Input` without its request-wide
constructor and call its actual `post()` and `_fetch_from_array()` methods.
The XSS service is an identity collaborator; database, alerts and redirects use
the existing suite collaborators. This verifies absent/null POST fields return
`FALSE` and runs the actual distributed license action without a live EE site.

Source repository commit: `ad6358a2b67db30cd2286fa39711caa975aceee1`.
Fixture SHA-256: `768f10a7cbf680c5fd19c0346917e6f6eb1cbbe5c1c8354c8c502dabf0c60d17`.
