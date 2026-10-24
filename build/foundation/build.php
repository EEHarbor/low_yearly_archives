<?php

// Derived from packettide/tools foundation/phar.php, scoped to this add-on.
require_once __DIR__ . '/Manifest.php';
$root = dirname(__DIR__, 2);
$source = __DIR__ . '/source';
$target = $root . '/low_yearly_archives/low_yearly_archives.phar';
$temporary = sys_get_temp_dir() . '/low_yearly_archives-foundation-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);

try {
    $contents = $temporary . '/contents';
    mkdir($contents, 0700);
    $provenance = json_decode(file_get_contents(__DIR__ . '/provenance.json'), true, 512, JSON_THROW_ON_ERROR);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    $expected = array();

    foreach ($files as $file) {
        $member = substr($file->getPathname(), strlen($source) + 1);
        $destination = $contents . '/' . $member;

        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0700, true);
        }

        $bytes = file_get_contents($file->getPathname());
        file_put_contents($destination, $bytes);
        chmod($destination, $provenance['members'][$member]['permissions'] ?? 0644);
        $expected[$member] = hash('sha256', $bytes);
    }

    $archive = new Phar($temporary . '/low_yearly_archives.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'low_yearly_archives.phar');
    $archive->buildFromDirectory($contents);
    $stub = file_get_contents(__DIR__ . '/stub.php');
    $archive->setStub($stub);
    $archive->setSignatureAlgorithm(Phar::SHA256);

    if ($archive->getStub() !== $stub || count($archive) !== count($expected) || $archive->getMetadata() !== null) {
        throw new RuntimeException('Foundation archive layout differs from source.');
    }

    $layout = LowYearlyFoundationManifest::read($temporary . '/low_yearly_archives.phar', strlen($stub));

    if ($layout['alias'] !== 'low_yearly_archives.phar' || $layout['metadata_bytes'] !== 0 || count($layout['members']) !== count($expected)) {
        throw new RuntimeException('Foundation raw manifest layout differs from source.');
    }

    foreach ($expected as $member => $hash) {
        $file = $archive[$member];
        $mode = $provenance['members'][$member]['permissions'] ?? 0644;

        if (hash('sha256', $file->getContent()) !== $hash || ($layout['members'][$member] ?? null) !== array('permissions' => $mode, 'compression' => 0, 'metadata_bytes' => 0) || $file->isCompressed() || $file->getMetadata() !== null) {
            throw new RuntimeException('Foundation archive member differs from source: ' . $member);
        }
    }

    // PharFileInfo keeps its archive alias registered on PHP 8.5.
    unset($file, $archive);

    if (!rename($temporary . '/low_yearly_archives.phar', $target)) {
        throw new RuntimeException('Unable to publish the rebuilt archive.');
    }

    ob_start();
    require $root . '/tests/source-manifest.php';
    $manifest = ob_get_clean();
    json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
    file_put_contents($root . '/tests/SOURCE_MANIFEST.json', $manifest);

    echo hash_file('sha256', $target) . "  low_yearly_archives/low_yearly_archives.phar\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
            continue;
        }

        unlink($file->getPathname());
    }

    rmdir($temporary);
}
