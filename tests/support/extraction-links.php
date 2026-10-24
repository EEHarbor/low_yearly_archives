<?php

$parts = explode('-', $mode);
$entry = $parts[2];
$kind = $parts[3];
$owned = sys_get_temp_dir() . '/low_yearly_archives-link-' . bin2hex(random_bytes(8));
$temporary = $owned . '/temporary';
$target = $owned . '/target';
$extraction = $temporary . '/pharextract/low_yearly_archives';
$before = getcwd();
$originalTemporary = getenv('TMPDIR');
mkdir($temporary, 0700, true);
mkdir($target, 0700);

$remove = static function ($path) use (&$remove) {
    foreach (new DirectoryIterator($path) as $file) {
        if ($file->isDot()) { continue; }
        if ($file->isDir() && !$file->isLink()) {
            $remove($file->getPathname());
            continue;
        }
        unlink($file->getPathname());
    }
    rmdir($path);
};

try {
    if ($kind === 'container') {
        mkdir($target . '/low_yearly_archives', 0700);
        $sentinel = $target . '/low_yearly_archives/sentinel';
        $link = $temporary . '/pharextract';
    }
    if ($kind === 'root') {
        mkdir(dirname($extraction), 0700);
        $sentinel = $target . '/sentinel';
        $link = $extraction;
    }
    if ($kind === 'stale') {
        mkdir($extraction . '/old/nested', 0700, true);
        file_put_contents($extraction . '/old/nested/value', 'stale');
        file_put_contents($extraction . '/.hidden', 'stale');
        $sentinel = $target . '/sentinel';
        $link = $extraction . '/contained';
    }
    file_put_contents($sentinel, 'retained');
    symlink($target, $link);
    putenv('TMPDIR=' . $temporary);
    $refused = false;
    try {
        if ($entry === 'fallback') { stream_wrapper_unregister('phar'); }
        require dirname(__DIR__, 2) . '/low_yearly_archives/low_yearly_archives.phar';
        if ($entry === 'direct') { Extract_low_yearly_archives::go(true); }
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Refusing linked PHAR extraction directory.') { throw $error; }
        $refused = true;
    }
    $result = array(
        'refused' => $refused,
        'target_preserved' => is_file($sentinel) && file_get_contents($sentinel) === 'retained',
        'link_preserved' => is_link($link),
        'stale_removed' => !file_exists($extraction . '/old') && !file_exists($extraction . '/.hidden'),
        'cwd_valid' => getcwd() === ($refused ? $before : realpath($extraction)),
    );
} finally {
    chdir($before);
    putenv($originalTemporary === false ? 'TMPDIR' : 'TMPDIR=' . $originalTemporary);
    $remove($owned);
}
$result['temporary_removed'] = !file_exists($owned);
echo json_encode($result, JSON_THROW_ON_ERROR);
