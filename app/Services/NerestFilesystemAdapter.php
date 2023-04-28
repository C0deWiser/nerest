<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\Flysystem\Visibility;

class NerestFilesystemAdapter implements FilesystemAdapter, ChecksumProvider
{
    private PathPrefixer $prefixer;
    private VisibilityConverter $visibilityConverter;

    public function __construct(
        readonly public PendingRequest $pendingRequest,
        readonly public string         $secret,
        string                         $prefix = '',
    )
    {
        $this->prefixer = new PathPrefixer($prefix);
        $this->visibilityConverter = new PortableVisibilityConverter();
    }

    protected function pendingRequest(string $path): PendingRequest
    {
        return $this->pendingRequest
            ->withToken(hash_hmac('sha256', $path, $this->secret));
    }

    protected function url(string $path, string $query = null): string
    {
        return $this->prefixer->prefixPath($path) . ($query ? '?' . $query : null);
    }

    public function fileExists(string $path): bool
    {
        return $this->pendingRequest($path)
            ->get($this->url($path), 'fileExists')
            ->onError(fn() => throw new UnableToCheckExistence)
            ->json();
    }

    public function directoryExists(string $path): bool
    {
        return $this->pendingRequest($path)
            ->get($this->url($path), 'directoryExists')
            ->onError(fn() => throw new UnableToCheckExistence)
            ->json();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->pendingRequest($path)
            ->post($this->url($path), ['contents' => $contents])
            ->onError(fn() => throw new UnableToWriteFile);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $append = false;

        while (!feof($contents)) {

            // 5 MiB chunk
            $chunk = fread($contents, 1024 * 1024 * 5);

            if ($append) {

                $this->pendingRequest($path)
                    ->put($this->url($path), ['contents' => $chunk])
                    ->onError(fn() => throw new UnableToWriteFile);

            } else {
                $this->write($path, $chunk, $config);
                $append = true;
            }
        }
    }

    public function read(string $path): string
    {
        return $this->pendingRequest($path)
            ->get($this->url($path), 'contents')
            ->onError(fn() => throw new UnableToReadFile)
            ->body();
    }

    public function readStream(string $path)
    {
        return $this->pendingRequest($path)
            ->get($this->url($path))
            ->onError(fn() => throw new UnableToReadFile)
            ->toPsrResponse()
            ->getBody()
            ->detach();
    }

    public function delete(string $path): void
    {
        $this->pendingRequest($path)
            ->delete($this->url($path))
            ->onError(fn() => throw new UnableToDeleteFile);
    }

    public function deleteDirectory(string $path): void
    {
        $this->pendingRequest($path)
            ->delete($this->url($path))
            ->onError(fn() => throw new UnableToDeleteDirectory);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->pendingRequest($path)
            ->post($this->url($path, 'dir'))
            ->onError(fn() => throw new UnableToCreateDirectory);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->visibilityConverter->forFile($visibility);

        $this->pendingRequest($path)
            ->put($this->url($path), ['visibility' => $visibility])
            ->onError(fn() => throw new UnableToSetVisibility);
    }

    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        $stat = $this->pendingRequest($path)
            ->get($this->url($path), 'metadata')
            ->onError(fn() => throw new UnableToRetrieveMetadata)
            ->json();

        if (!is_array($stat)) {
            throw UnableToRetrieveMetadata::create($path, $type);
        }

        $attributes = $this->convertListingToAttributes($path, $stat);

        if (!$attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create($path, $type, 'path is not a file');
        }

        return $attributes;
    }

    private function convertListingToAttributes(string $path, array $attributes): StorageAttributes
    {
        if (($attributes['type'] ?? null) == 'dir') {
            return new DirectoryAttributes(
                ltrim($path, '/'),
                $attributes['visibility'] ?? Visibility::PRIVATE,
                $attributes['last_modified'] ?? null
            );
        }

        return new FileAttributes(
            $path,
            $attributes['file_size'],
            $attributes['visibility'] ?? Visibility::PRIVATE,
            $attributes['last_modified'] ?? null,
            $attributes['mime_type'] ?? null,
        );
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_MIME_TYPE);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, StorageAttributes::ATTRIBUTE_FILE_SIZE);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $response = $this->pendingRequest($path)
            ->get($this->url($path), ['list' => $deep])
            ->throw();

        $listing = [];

        foreach ($response->json() ?? [] as $item) {
            $listing[] = $this->convertListingToAttributes($this->prefixer->stripPrefix($item['path']), $item);
        }

        return new DirectoryListing($listing);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->pendingRequest($source)
            ->put($this->url($source), [
                'move' => $this->prefixer->prefixPath($destination)
            ])
            ->onError(fn() => throw new UnableToMoveFile);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->pendingRequest($source)
            ->put($this->url($source), [
                'copy' => $this->prefixer->prefixPath($destination)
            ])
            ->onError(fn() => throw new UnableToCopyFile);
    }

    public function checksum(string $path, Config $config): string
    {
        return $this->pendingRequest($path)
            ->get($this->url($path), 'checksum')
            ->onError(fn(Response $response) => throw new UnableToProvideChecksum($response->reason(), $path))
            ->json();
    }

}
