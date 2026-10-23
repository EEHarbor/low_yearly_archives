<?php

$root = dirname(__DIR__, 2);
$archivePath = $root . '/low_yearly_archives/low_yearly_archives.phar';
$archive = new Phar($archivePath);
$manifest = array('archive_sha256' => hash_file('sha256', $archivePath), 'loose_files' => array(), 'files' => array());
foreach (array('addon.setup.php', 'autoload.php', 'pi.low_yearly_archives.php', 'views/license.php') as $relative) {
    $manifest['loose_files'][$relative] = hash_file('sha256', $root . '/low_yearly_archives/' . $relative);
}
foreach (new RecursiveIteratorIterator($archive) as $entry) {
    $relative = substr($entry->getPathname(), strlen('phar://' . $archivePath . '/'));
    if (substr($relative, -4) !== '.php') {
        continue;
    }
    $destination = $root . '/tests/.coverage-source/' . $relative;
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0700, true);
    }
    file_put_contents($destination, $entry->getContent());
    $hash = hash('sha256', $entry->getContent());
    if (hash_file('sha256', $destination) !== $hash) {
        throw new RuntimeException('Archive copy verification failed: ' . $relative);
    }
    $manifest['files'][$relative] = array('bytes' => $entry->getSize(), 'sha256' => $hash);
}
file_put_contents($root . '/tests/SOURCE_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($manifest['files']) . " byte-identical archived PHP files prepared.\n";
