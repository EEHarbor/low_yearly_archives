<?php

require dirname(__DIR__) . '/support/Diagnostics.php';
ob_start();
require __DIR__ . '/phpunit-9.phar';
$output = ob_get_clean();
if ($output !== '' && $output !== "#!/usr/bin/env php\n") {
    throw new RuntimeException('Unexpected pinned PHPUnit loader output.');
}
$status = \PHPUnit\TextUI\Command::main(false);
exit($status === 0 && LowYearlyDiagnostics::events() !== array() ? 1 : $status);
