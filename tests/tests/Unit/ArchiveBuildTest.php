<?php

class ArchiveBuildTest extends LowYearlyTestCase
{
    /**
     * Rebuild a complete owned copy and reopen the renamed artifact on the selected runtime.
     *
     * @return void
     */
    public function testOwnedBuildRefreshesItsManifest(): void
    {
        $result = LowYearlyNativeProcess::run(array('-d', 'phar.readonly=0', __DIR__ . '/../../support/build-check.php'));
        $this->assertSame(0, $result[0], $result[1] . $result[2]);
        $this->assertSame('', $result[2]);
        $this->assertSame(array('members' => 23, 'manifest_refreshed' => true), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Match every distributed member to its build source and original layout.
     *
     * @return void
     */
    public function testDistributedArchiveMatchesBuildSource(): void
    {
        $root = rtrim(PATH_THIRD, "/") . '/build/foundation';
        $provenance = json_decode(file_get_contents($root . '/provenance.json'), true, 512, JSON_THROW_ON_ERROR);
        $archive = new \Phar(rtrim(PATH_THIRD, "/") . '/low_yearly_archives/low_yearly_archives.phar');
        $stub = $archive->getStub();
        require_once $root . '/Manifest.php';
        $layout = \LowYearlyFoundationManifest::read(rtrim(PATH_THIRD, "/") . '/low_yearly_archives/low_yearly_archives.phar', strlen($stub));
        $this->assertSame('low_yearly_archives.phar', $archive->getAlias());
        $this->assertSame($provenance['signature_algorithm'], $archive->getSignature()['hash_type']);
        $this->assertNull($archive->getMetadata());
        $this->assertSame('low_yearly_archives.phar', $layout['alias']);
        $this->assertSame(0, $layout['metadata_bytes']);
        $this->assertSame(file_get_contents($root . '/stub.php'), $stub);
        $this->assertSame(1, preg_match('/const LEN = ([0-9]+);/', $stub, $length));
        $this->assertSame(strlen($stub), (int) $length[1]);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/source', \FilesystemIterator::SKIP_DOTS));
        $count = 0;

        foreach ($files as $file) {
            $member = substr($file->getPathname(), strlen($root . '/source/'));
            $mode = $provenance['members'][$member]['permissions'] ?? 0644;
            $this->assertSame(file_get_contents($file->getPathname()), $archive[$member]->getContent(), $member);
            $this->assertSame(array('permissions' => $mode, 'compression' => 0, 'metadata_bytes' => 0), $layout['members'][$member], $member);
            $this->assertFalse($archive[$member]->isCompressed(), $member);
            $this->assertNull($archive[$member]->getMetadata(), $member);
            $count++;
        }

        $this->assertSame($count, count($archive));
    }

    /**
     * Verify the complete source hashes and declaration positions against reality.
     *
     * @return void
     */
    public function testCurrentInventoryMatchesActualSource(): void
    {
        $actual = (static function () {
            ob_start();

            try {
                require rtrim(PATH_THIRD, "/") . '/tests/source-manifest.php';
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        })();
        $this->assertSame(json_decode(file_get_contents(rtrim(PATH_THIRD, "/") . '/tests/SOURCE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR), json_decode($actual, true, 512, JSON_THROW_ON_ERROR));
    }
}
