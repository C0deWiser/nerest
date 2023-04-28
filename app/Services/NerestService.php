<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\DirectoryListing;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class NerestService
{
    protected ?string $signature = null;
    protected string $path = '';
    protected string $real_path = '';

    public function __construct(
        protected string $secret
    )
    {
        //
    }

    public function withToken(?string $signature): static
    {
        $this->signature = $signature;

        return $this->authorize();
    }

    public function withPath(string $path): static
    {
        $this->path = $path;
        $this->real_path = Storage::path($path);

        return $this;
    }

    public function authorize(): static
    {
        if ($this->signature != hash_hmac('sha256', $this->path, $this->secret)) {
            throw new UnauthorizedHttpException('bearer');
        }

        return $this;
    }

    public function fileShouldExist(): static
    {
        if (Storage::fileMissing($this->path)) {
            throw new NotFoundHttpException();
        }

        return $this;
    }

    public function directoryShouldExist(): static
    {
        if (Storage::directoryMissing($this->path)) {
            throw new NotFoundHttpException();
        }

        return $this;
    }

    public function streamResponse(string|null $name = null, array $headers = [], string|null $disposition = 'inline'): StreamedResponse
    {
        $this->fileShouldExist();

        return Storage::response($this->path, $name, $headers, $disposition);
    }

    public function exists(): bool
    {
        return Storage::exists($this->path);
    }

    public function fileExists(): bool
    {
        return Storage::fileExists($this->path);
    }

    public function directoryExists(): bool
    {
        return Storage::directoryExists($this->path);
    }

    public function fileContent(): ?string
    {
        $this->fileShouldExist();

        return Storage::get($this->path);
    }

    public function metadata(): array
    {
        if ($this->fileExists()) {
            return [
                'type' => 'file',
                'path' => $this->path,
                'file_size' => Storage::fileSize($this->path),
                'visibility' => Storage::visibility($this->path),
                'last_modified' => Storage::lastModified($this->path),
                'mime_type' => Storage::mimeType($this->path),
            ];
        } elseif ($this->directoryExists()) {
            return [
                'type' => 'dir',
                'path' => $this->path,
                'visibility' => Storage::visibility($this->path),
                'last_modified' => Storage::lastModified($this->path),
            ];
        } else {
            throw new NotFoundHttpException();
        }
    }

    public function listContents(bool $deep): DirectoryListing
    {
        $this->directoryShouldExist();

        return Storage::listContents($this->path, $deep);
    }

    public function checksum(array $options = []): bool|string
    {
        $this->fileShouldExist();

        return Storage::checksum($this->path, $options);
    }

    public function write(string $contents, array $config = []): void
    {
        Storage::write($this->path, $contents, $config);
    }

    public function createDirectory(array $config = []): void
    {
        Storage::createDirectory($this->path, $config);
    }

    public function visibility(string $visibility): bool
    {
        $this->fileShouldExist();

        return Storage::setVisibility($this->path, $visibility);
    }

    public function copy(string $destination): bool
    {
        $this->fileShouldExist();

        return Storage::copy($this->path, $destination);
    }

    public function move(string $destination): bool
    {
        $this->fileShouldExist();

        return Storage::move($this->path, $destination);
    }

    public function append(string $contents): bool
    {
        $this->fileShouldExist();

        return file_put_contents($this->real_path, $contents, FILE_APPEND);
    }

    public function delete(): bool
    {
        $this->fileShouldExist();

        return Storage::delete($this->path);
    }

    public function deleteDirectory(): bool
    {
        $this->directoryShouldExist();

        return Storage::deleteDirectory($this->path);
    }
}
