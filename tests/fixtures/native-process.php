<?php

$mode = $argv[1];
if ($mode === 'settings') {
    echo json_encode(array(PHP_BINARY, error_reporting(), ini_get('display_errors'), ini_get('display_startup_errors')));
    exit;
}
if ($mode === 'transport') {
    fwrite(STDOUT, 'out');
    fwrite(STDERR, 'err');
    exit(3);
}
if ($mode === 'limit') {
    echo str_repeat('x', 131073);
    exit;
}
if ($mode === 'nonzero') {
    exit(7);
}
if ($mode === 'caught') {
    try {
        @trigger_error('Owned generic native diagnostic', E_USER_DEPRECATED);
    } catch (Throwable $error) {
    }
    exit;
}

throw new RuntimeException('Unknown owned native process fixture.');
