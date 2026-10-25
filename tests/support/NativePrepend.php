<?php

require_once __DIR__ . '/Diagnostics.php';

/**
 * Reject caught diagnostics even when the native caller restores class-static state.
 *
 * @return void
 */
register_shutdown_function(static function () {
    if (LowYearlyDiagnostics::events() !== array()) {
        exit(70);
    }
});
