<?php

$kind = substr($mode, strlen('cleanup-root-'));
$owned = sys_get_temp_dir() . '/low_yearly_archives-cleanup-' . bin2hex(random_bytes(8));
$before = getcwd();
mkdir($owned, 0700);
mkdir($owned . '/target', 0700);
file_put_contents($owned . '/target/sentinel', 'retained');
$path = $owned . '/root';
if (strpos($kind, 'backslash') === 0) { $path .= '\\'; }
if ($kind === 'backslash-directory') {
    mkdir($path, 0700);
    file_put_contents($path . '/.stale', 'remove');
}
if ($kind !== 'backslash-directory') {
    symlink($owned . (strpos($kind, 'dangling') === 0 ? '/absent' : '/target'), $path);
}
$argument = $path . (strpos($kind, '-slash') !== false ? '//' : '');
try {
    require dirname(__DIR__, 2) . '/low_yearly_archives/low_yearly_archives.phar';
    Extract_low_yearly_archives::_removeTmpFiles($argument, $before);
    $result = array('target_preserved' => file_get_contents($owned . '/target/sentinel') === 'retained',
        'root_removed' => !is_link($path) && !file_exists($path), 'cwd_preserved' => getcwd() === $before);
} finally {
    chdir($before);
    if (is_link($path)) { unlink($path); }
    if (is_dir($path)) {
        if (is_file($path . '/.stale')) { unlink($path . '/.stale'); }
        rmdir($path);
    }
    unlink($owned . '/target/sentinel');
    rmdir($owned . '/target');
    rmdir($owned);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
