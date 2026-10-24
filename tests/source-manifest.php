<?php

$root = dirname(__DIR__);
$path = $root . '/low_yearly_archives/low_yearly_archives.phar';
$archive = new Phar($path);
$manifest = array('archive_sha256' => hash_file('sha256', $path), 'loose_files' => array(), 'files' => array());
foreach (array('addon.setup.php', 'autoload.php', 'pi.low_yearly_archives.php', 'views/license.php') as $relative) {
    $manifest['loose_files'][$relative] = hash_file('sha256', $root . '/low_yearly_archives/' . $relative);
}
foreach (new RecursiveIteratorIterator($archive) as $entry) {
    $relative = substr($entry->getPathname(), strlen('phar://' . $path . '/'));
    $manifest['files'][$relative] = array('bytes' => $entry->getSize(), 'sha256' => hash('sha256', $entry->getContent()));
}
ksort($manifest['files']);
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
