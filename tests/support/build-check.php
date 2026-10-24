<?php

require __DIR__ . '/Diagnostics.php';
$root = dirname(__DIR__, 2);
$target = sys_get_temp_dir() . '/build-check';
mkdir($target, 0700);
foreach (array('build', 'low_yearly_archives') as $name) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $name, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    mkdir($target . '/' . $name, 0700);
    foreach ($files as $file) {
        $destination = $target . '/' . substr($file->getPathname(), strlen($root) + 1);
        if ($file->isDir()) {
            mkdir($destination, 0700);
            continue;
        }
        copy($file->getPathname(), $destination);
    }
}
mkdir($target . '/tests', 0700);
copy($root . '/tests/source-manifest.php', $target . '/tests/source-manifest.php');
$expected = json_decode(file_get_contents($root . '/tests/SOURCE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
$output = (static function ($script) {
    ob_start();
    try {
        require $script;
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
})($target . '/build/foundation/build.php');
$actual = json_decode(file_get_contents($target . '/tests/SOURCE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
if ($actual['files'] !== $expected['files'] || $actual['loose_files'] !== $expected['loose_files']
    || $output !== $actual['archive_sha256'] . "  low_yearly_archives/low_yearly_archives.phar\n") {
    throw new RuntimeException('The owned rebuild changed source bytes or failed to refresh its manifest.');
}
echo json_encode(array('members' => count($actual['files']), 'manifest_refreshed' => true), JSON_THROW_ON_ERROR);
