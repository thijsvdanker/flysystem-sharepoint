<?php
namespace BitsnBolts\Flysystem\Sharepoint\Test;

use League\Flysystem\Filesystem;
use BitsnBolts\Flysystem\Sharepoint\GetUrl;
use BitsnBolts\Flysystem\Sharepoint\SharepointAdapter;
use Office365\Runtime\Http\RequestException;

class SharepointTest extends TestBase
{
    private $fs;

    private $filesToPurge = [];
    private $directoriesToPurge = [];

    protected function setUp(): void
    {
        parent::setUp();
        $adapter = new SharepointAdapter([
            'url' => SHAREPOINT_SITE_URL,
            'username' => SHAREPOINT_USERNAME,
            'password' => SHAREPOINT_PASSWORD,
        ]);

        $this->fs = new Filesystem($adapter);
    }

    /** @group write */
    public function testWrite()
    {
        $this->assertEquals(true, $this->fs->write(TEST_FILE_PREFIX . 'testWrite.txt', 'testing'));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testWrite.txt';
    }

    public function testWriteToDirectory()
    {
        $this->assertEquals(true, $this->fs->write(TEST_FILE_PREFIX . 'testDir/testWriteInDir.txt', 'testing'));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testDir/testWriteInDir.txt';
        $this->directoriesToPurge[] = TEST_FILE_PREFIX . 'testDir';
    }

    /** @group nest */
    public function testWriteToNestedDirectory()
    {
        $this->markTestSkipped('This does not work yet');

        $this->assertEquals(true, $this->fs->write(TEST_FILE_PREFIX . 'testDir/nested/testWriteInDir.txt', 'testing'));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testDir/testWriteInDir.txt';
        $this->directoriesToPurge[] = TEST_FILE_PREFIX . 'testDir/nested';
        $this->directoriesToPurge[] = TEST_FILE_PREFIX . 'testDir';
    }

    public function testWriteStream()
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'testing');
        rewind($stream);

        $this->assertEquals(true, $this->fs->writeStream(TEST_FILE_PREFIX . 'testWriteStream.txt', $stream));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testWriteStream.txt';
    }

    public function testRead()
    {
        $this->fs->write(TEST_FILE_PREFIX . 'testRead.txt', 'read content');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testRead.txt';

        $this->assertEquals('read content', $this->fs->read(TEST_FILE_PREFIX . 'testRead.txt'));
    }

    /** @group read */
    public function testReadInDirectory()
    {
        $this->fs->write(TEST_FILE_PREFIX . 'testDir/testReadInDir.txt', 'read content in directory');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testDir/testReadInDir.txt';
        $this->directoriesToPurge[] = TEST_FILE_PREFIX . 'testDir';

        $this->assertEquals('read content in directory', $this->fs->read(TEST_FILE_PREFIX . 'testDir/testReadInDir.txt'));
    }

    /** @group word */
    public function testReadWord()
    {
        $path = __DIR__ . '/files/word.docx';
        $this->fs->writeStream(TEST_FILE_PREFIX . 'testWord.docx', fopen($path, 'r'));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testWord.docx';

        $this->assertNotEmpty($this->fs->read(TEST_FILE_PREFIX . 'testWord.docx'));
    }


    public function testDelete()
    {
        // Create file
        $this->fs->write(TEST_FILE_PREFIX . 'testDelete.txt', 'testing');
        // Ensure it exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'testDelete.txt'));
        // Now delete
        $this->assertEquals(true, $this->fs->delete(TEST_FILE_PREFIX . 'testDelete.txt'));
        // Ensure it no longer exists
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'testDelete.txt'));
    }

    /** @group del */
    public function testDeleteDirectory()
    {
        // Create directory
        $result = $this->fs->createDir(TEST_FILE_PREFIX . 'delete-dir');
        // Ensure it exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'delete-dir'));
        // Now delete
        $this->assertEquals(true, $this->fs->delete(TEST_FILE_PREFIX . 'delete-dir'));
        // Ensure it no longer exists
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'delete-dir'));
    }

    public function testHas()
    {
        // Test that file does not exist
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'testHas.txt'));

        // Create file
        $this->createFile('testHas.txt');

        // Test that file exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'testHas.txt'));
    }

    /** @group has */
    public function testHasInFolder()
    {
        // Test that file does not exist
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'folder/testHasInFolder.txt'));

        // Create file
        $this->createFile('folder/testHasInFolder.txt');

        // Test that file exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'folder/testHasInFolder.txt'));
    }

    public function testListContents()
    {
        // Create files
        $this->createFile('first.txt');
        $this->createFile('second.txt');

        [$first, $second] = $this->fs->listContents(TEST_FILE_PREFIX);

        $this->assertEquals('first.txt', $first['basename']);
        $this->assertEquals('second.txt', $second['basename']);
    }

    public function testListContentsContainsDirectories()
    {
        // Create files
        $this->createFile('file.txt');
        $this->createFile('test-list-contents-contains-directory/in-folder.txt');

        [$first, $directory] = $this->fs->listContents(TEST_FILE_PREFIX);

        $this->assertEquals('file.txt', $first['basename']);
        $this->assertEquals('test-list-contents-contains-directory', $directory['basename']);
    }


    /** @group thijs2 */
    public function testListContentsOfDirectory()
    {
        // Create files
        $this->createFile('list-directory/ld-first.txt');
        $this->createFile('list-directory/ld-second.txt');

        try {
            [$first, $second] = $this->fs->listContents(TEST_FILE_PREFIX . 'list-directory');

            $this->assertEquals('ld-first.txt', $first['basename']);
            $this->assertEquals('ld-second.txt', $second['basename']);
        } catch (RequestException $e) {
            $this->fail($e->getMessage());
        }
    }

    /** @group rec */
    public function testListContentsRecursive()
    {
        // Create files
        $this->createFile('1-root-first.txt');
        $this->createFile('2-list-recursive/3-recursive-first.txt');
        $this->createDir('4-empty-dir');

        // More then one level fails ATM.
        // $this->createFile('list-recursive/nested/recursive-second.txt');

        [$first, $directory, $nested, $emptyDir] = $this->fs->listContents(TEST_FILE_PREFIX, true);

        $this->assertEquals('1-root-first.txt', $first['basename']);
        $this->assertEquals('2-list-recursive', $directory['basename']);
        $this->assertEquals('3-recursive-first.txt', $nested['basename']);
        $this->assertEquals('4-empty-dir', $emptyDir['basename']);
    }



    public function testGetUrl()
    {
        // Create file
        $this->createFile('testGetUrl.txt');

        // Get url
        $this->assertNotEmpty($this->fs->getAdapter()->getUrl(TEST_FILE_PREFIX . 'testGetUrl.txt'));
    }

    public function testGetUrlPlugin()
    {
        $this->fs->addPlugin(new GetUrl());

        $this->createFile('testGetUrlPlugin.txt');

        // Get url
        $this->assertNotEmpty($this->fs->getAdapter()->getUrl(TEST_FILE_PREFIX . 'testGetUrlPlugin.txt'));
    }

    public function testGetMetadata()
    {
        // Create file
        $this->createFile('testMetadata.txt');

        // Call metadata
        $metadata = $this->fs->getMetadata(TEST_FILE_PREFIX.'testMetadata.txt');
        $this->assertEquals(TEST_FILE_PREFIX.'testMetadata.txt', $metadata['path']);
    }

    public function testTimestamp()
    {
        // Create file
        $this->createFile('testTimestamp.txt');

        // Call metadata
        $this->assertIsInt($this->fs->getTimestamp(TEST_FILE_PREFIX.'testTimestamp.txt'));
    }

    public function testMimetype()
    {
        $this->markTestSkipped('SPO doesnt return a mimetype');

        // Create file
        $this->createFile('testMimetype.txt');

        // Call metadata
        $this->assertEquals('text/plain', $this->fs->getMimetype(TEST_FILE_PREFIX.'testMimetype.txt'));
    }

    public function testSize()
    {
        // Create file
        $this->createFile('testSize.txt', 'testing metadata functionality');

        // Get the file size
        $this->assertEquals(30, $this->fs->getSize(TEST_FILE_PREFIX.'testSize.txt'));
    }

    /**
     * @return void
     */
    public function testLargeFileUploads()
    {
        // Create file
        $path = __DIR__ . '/files/50MB.bin';
        $this->fs->writeStream(TEST_FILE_PREFIX . 'testLargeUpload.txt', fopen($path, 'r'));
        // fclose($path);
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testLargeUpload.txt';

        // Get the file size
        $this->assertEquals(50000000, $this->fs->getSize(TEST_FILE_PREFIX.'testLargeUpload.txt'));
    }

    public function testListContentsForNonExistingDirectoriesReturnAnEmptyArray()
    {
        $result = $this->fs->listContents('non-existing-directory');
        $this->assertEquals([], $result);
    }

    protected function createFile($path, $content = '::content::')
    {
        $this->fs->write(TEST_FILE_PREFIX . $path, $content);
        $this->filesToPurge[] = TEST_FILE_PREFIX . $path;

        if (strpos($path, '/')) {
            $dir = $path;
            while (dirname($dir) !== '.') {
                $dir = dirname($dir);
                $this->directoriesToPurge[] = TEST_FILE_PREFIX . $dir;
            }
        }
    }

    public function createDir($path)
    {
        $this->fs->createDir(TEST_FILE_PREFIX . $path);
        $this->directoriesToPurge[] = TEST_FILE_PREFIX . $path;
    }

    /**
     * Tears down the test suite by attempting to delete all files written, clearing things up
     *
     * @todo Implement functionality
     */
    protected function tearDown(): void
    {
        foreach ($this->filesToPurge as $path) {
            try {
                $this->fs->delete($path);
            } catch (\Exception $e) {
                echo 'file purge failed: ' .$e->getMessage();
                // Do nothing, just continue. We obviously can't clean it
            }
        }
        $this->filesToPurge = [];

        // Deleting directories doensnt work.
        // @see https://github.com/bitsnbolts/flysystem-sharepoint/issues/6

        foreach ($this->directoriesToPurge as $path) {
            try {
                $this->fs->delete($path);
            } catch (\Exception $e) {
                echo 'dir purge failed: ' .$e->getMessage();
                // Do nothing, just continue. We obviously can't clean it
            }
        }
        $this->directoriesToPurge = [];
    }
}
