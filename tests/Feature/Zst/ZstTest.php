<?php

namespace Tests\Feature\Zst;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZstTest extends TestCase
{
    public Filesystem $disk;
    public string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::disk('zst');
        $this->domain = '/';
//        $this->disk = Storage::disk('s3');
//        $this->domain = 'https://zmb.storage.yandexcloud.net';
    }

    protected function tearDown(): void
    {
        foreach (Storage::allFiles() as $file) {
            Storage::delete($file);
        }

        foreach (Storage::allDirectories() as $directory) {
            Storage::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    protected function filename(string $ext): string
    {
        return fake()->uuid() . '.' . $ext;
    }

    protected function dirname(): string
    {
        return fake()->uuid();
    }

    public function testFileExists()
    {
        $filename = $this->filename('txt');

        $this->assertFalse($this->disk->exists($filename));
    }

    public function testDirectoryExists()
    {
        $dirname = $this->dirname();

        $this->assertFalse($this->disk->exists($dirname));
    }

    public function testPutContent()
    {
        $text = fake()->text();
        $filename = $this->filename('txt');

        $this->disk->put($filename, $text);

        $this->assertEquals($text, $this->disk->get($filename));

        $this->disk->delete($filename);
    }

    public function testPutFile()
    {
        $text = fake()->text();
        $dirname = $this->dirname();
        $filename = tempnam(sys_get_temp_dir(), 'zst');

        file_put_contents($filename, $text);
        $file = new File($filename);
        $this->disk->put($dirname, $file);

        $savedAs = $dirname . '/' . $file->hashName();

        $this->assertEquals(
            $text,
            $this->disk->get($savedAs)
        );

        $this->disk->delete($savedAs);
        $this->disk->deleteDirectory($dirname);
    }

    public function testPutUploadedFile()
    {
        $text = fake()->text();
        $filename = $this->filename('txt');
        $dirname = $this->dirname();

        $uploadedFile = UploadedFile::fake()
            ->createWithContent(
                $filename, $text
            );

        $this->disk->put($dirname, $uploadedFile);

        $savedAs = $dirname . '/' . $uploadedFile->hashName();

        $this->assertEquals(
            $text,
            $this->disk->get($savedAs)
        );

        $this->disk->delete($savedAs);
        $this->disk->deleteDirectory($dirname);
    }

    public function testPutHugeFile()
    {
        $filename = $this->filename('txt');
        $dirname = $this->dirname();
        // 15 MiB
        $text = str_repeat(fake()->randomLetter(), 1024 * 1024 * 15);

        $uploadedFile = UploadedFile::fake()
            ->createWithContent($filename, $text);

        $this->disk->put($dirname, $uploadedFile);

        $savedAs = $dirname . '/' . $uploadedFile->hashName();

        $this->assertEquals(
            $text,
            $this->disk->get($savedAs)
        );

        $this->disk->delete($savedAs);
        $this->disk->deleteDirectory($dirname);
    }

    public function testPutResource()
    {
        $text = fake()->text();
        $filename = $this->filename('txt');
        $resource = tmpfile();
        fputs($resource, $text);

        $this->disk->put($filename, $resource);

        $this->assertEquals(
            $text,
            $this->disk->get($filename)
        );

        $this->disk->delete($filename);
    }

    public function testGet()
    {
        $filename = $this->filename('txt');
        $text = fake()->text();

        $this->assertNull($this->disk->get($filename));

        $this->disk->put($filename, $text);

        $this->assertEquals($text, $this->disk->get($filename));

        $this->get($this->domain . '/' . $filename)->assertStatus(404);

        $this->disk->delete($filename);
    }

    public function testGetStream()
    {
        $filename = $this->filename('txt');
        $text = fake()->text();

        $this->assertNull($this->disk->readStream($filename));

        $this->disk->put($filename, $text);

        $resource = $this->disk->readStream($filename);

        $this->assertTrue(is_resource($resource));

        $this->assertEquals($text, fread($resource, 1024));

        $this->disk->delete($filename);
    }

    public function testDelete()
    {
        $filename = $this->filename('txt');

        $this->assertFalse($this->disk->exists($filename));

        $this->disk->put($filename, fake()->text());

        $this->assertTrue($this->disk->exists($filename));

        $this->disk->delete($filename);

        $this->assertFalse($this->disk->exists($filename));
    }

    public function testCreateDirectory()
    {
        $dirname = $this->dirname();

        $this->assertFalse($this->disk->exists($dirname));

        $this->disk->makeDirectory($dirname);

        $this->assertTrue($this->disk->exists($dirname));

        $this->disk->deleteDirectory($dirname);
    }

    public function testDeleteDirectory()
    {
        $dirname = $this->dirname();

        $this->assertFalse($this->disk->exists($dirname));

        $this->disk->makeDirectory($dirname);

        $this->assertTrue($this->disk->exists($dirname));

        $this->disk->deleteDirectory($dirname);

        $this->assertFalse($this->disk->exists($dirname));
    }

    public function testVisibility()
    {
        $filename = $this->filename('txt');

        $this->disk->put($filename, fake()->text());

        $this->disk->setVisibility($filename, 'public');
        $this->assertEquals('public', $this->disk->getVisibility($filename));

        $this->disk->setVisibility($filename, 'private');
        $this->assertEquals('private', $this->disk->getVisibility($filename));

        $this->disk->delete($filename);
    }

    public function testSize()
    {
        $filename = $this->filename('txt');
        $text = fake()->text();

        $this->disk->put($filename, $text);
        $this->assertEquals(strlen($text), $this->disk->size($filename));

        $this->disk->delete($filename);
    }

    public function testLastModified()
    {
        $filename = $this->filename('txt');
        $text = fake()->text();

        $lm = [time()];
        $this->disk->put($filename, $text);
        $lm[] = time();

        $this->assertTrue(
            $lm[0] >= $this->disk->lastModified($filename) &&
            $this->disk->lastModified($filename) >= $lm[1]
        );

        $this->disk->delete($filename);
    }

    public function testMimeType()
    {
        $filename = $this->filename('txt');
        $text = fake()->text();

        $this->disk->put($filename, $text);
        $this->assertEquals('text/plain', $this->disk->mimeType($filename));

        $this->disk->delete($filename);
    }

    public function testListDirectories()
    {
        $this->disk->makeDirectory('/path');
        $this->disk->makeDirectory('/path/to');
        $this->disk->makeDirectory('/path/to/one');
        $this->disk->makeDirectory('/path/to/two');

        $dirs = $this->disk->directories('/path', true);
        $this->assertCount(3, $dirs);

        $dirs = $this->disk->allDirectories();
        $this->assertCount(4, $dirs);

        $this->disk->deleteDirectory('/path/to/one');
        $this->disk->deleteDirectory('/path/to/two');
        $this->disk->deleteDirectory('/path/to');
        $this->disk->deleteDirectory('/path');
    }

    public function testListFiles()
    {
        $this->disk->makeDirectory('/path');
        $this->disk->put('/path/readme.txt', '123');
        $this->disk->makeDirectory('/path/to');
        $this->disk->makeDirectory('/path/to/one');
        $this->disk->put('/path/to/one/readme.txt', '123');
        $this->disk->makeDirectory('/path/to/two');
        $this->disk->put('/path/to/two/readme.txt', '123');

        $files = $this->disk->files('/path/to', true);
        $this->assertCount(2, $files);

        $files = $this->disk->allFiles();
        $this->assertCount(3, $files);

        $this->disk->delete('/path/to/one/readme.txt');
        $this->disk->delete('/path/to/two/readme.txt');
        $this->disk->deleteDirectory('/path/to/one');
        $this->disk->deleteDirectory('/path/to/two');
        $this->disk->deleteDirectory('/path/to');
        $this->disk->deleteDirectory('/path');
    }

    public function testMove()
    {
        $this->disk->makeDirectory('/path/to/one');
        $this->disk->makeDirectory('/path/to/two');
        $this->disk->put('/path/to/one/readme.txt', '123');

        $this->assertTrue($this->disk->move('/path/to/one/readme.txt', '/path/to/two/readme.txt'));
        $this->assertFalse($this->disk->exists('/path/to/one/readme.txt'));
        $this->assertTrue($this->disk->exists('/path/to/two/readme.txt'));

        $this->disk->delete('/path/to/two/readme.txt');
        $this->disk->deleteDirectory('/path/to/one');
        $this->disk->deleteDirectory('/path/to/two');
        $this->disk->deleteDirectory('/path/to');
        $this->disk->deleteDirectory('/path');
    }

    public function testCopy()
    {
        $this->disk->makeDirectory('/path/to/one');
        $this->disk->put('/path/to/one/readme.txt', '123');

        $this->assertTrue($this->disk->copy('/path/to/one/readme.txt', '/path/to/readme.txt'));
        $this->assertTrue($this->disk->exists('/path/to/one/readme.txt'));
        $this->assertTrue($this->disk->exists('/path/to/readme.txt'));

        $this->disk->delete('/path/to/one/readme.txt');
        $this->disk->deleteDirectory('/path/to/one');
        $this->disk->delete('/path/to/readme.txt');
        $this->disk->deleteDirectory('/path/to');
        $this->disk->deleteDirectory('/path');

    }

    public function testChecksum()
    {
        $text = fake()->text();
        $filename = $this->filename('txt');
        $this->disk->put($filename, $text);

        $this->assertEquals(md5($text), $this->disk->checksum($filename));

        $this->disk->delete($filename);
    }

    public function testFetch()
    {
        $this
            ->get($this->domain . '/' . fake()->uuid())
            ->assertStatus(404);
    }
}
