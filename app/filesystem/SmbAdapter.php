<?php

namespace App\Filesystem;

use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\Exception;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\DirectoryListing;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

class SmbAdapter implements FilesystemAdapter
{
    protected $client;

    public function __construct($config)
    {
        $auth = new BasicAuth($config['username'], $config['host'], $config['password']);
        $serverFactory = new ServerFactory();
        $server = $serverFactory->createServer($config['host'], $auth);
        $this->client = $server->getShare($config['share']);
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->stat($path) !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $stat = $this->client->stat($path);
            return $stat && $stat->isDirectory();
        } catch (Exception $e) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            $this->client->put($path, $stream);
            fclose($stream);
        } catch (Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    }

    /* public function writeStream(string $path, $resource, Config $config): void
    {
        try {
            if (!is_resource($resource)) {
                throw new \InvalidArgumentException("Expected a resource, got " . gettype($resource));
            }
            $this->client->put($path, $resource);
        } catch (Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    } */

    public function delete(string $path): void
    {
        try {
            $this->client->del($path);
        } catch (Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->client->rmdir($path, true);
        } catch (Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->mkdir($path);
        } catch (Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // SMB does not support visibility
        throw UnableToSetVisibility::atLocation($path);
    }

    public function visibility(string $path): FileAttributes
    {
        // SMB does not support visibility
        throw UnableToRetrieveMetadata::visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $stream = $this->client->read($path);
            $contents = stream_get_contents($stream);
            fclose($stream);
            $tempFile = tmpfile();
            fwrite($tempFile, $contents);
            fseek($tempFile, 0);
            $mimetype = mime_content_type(stream_get_meta_data($tempFile)['uri']);
            fclose($tempFile);
            return new FileAttributes($path, null, null, null, $mimetype);
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage());
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $timestamp = $this->client->stat($path)->getMTime();
            return new FileAttributes($path, null, null, $timestamp);
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage());
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $size = $this->client->stat($path)->getSize();
            return new FileAttributes($path, $size);
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage());
        }
    }

    public function read(string $path): string
    {
        try {
            $stream = $this->client->read($path);
            $contents = stream_get_contents($stream);
            fclose($stream);
            return $contents;
        } catch (Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
    }

    public function readStream(string $path)
    {
        try {
            return $this->client->read($path);
        } catch (Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
    }

    public function listContents(string $path, bool $deep): DirectoryListing
    {
        $files = [];
        foreach ($this->client->dir($path) as $file) {
            $files[] = new StorageAttributes(
                $file->getName(),
                $file->isDirectory() ? StorageAttributes::TYPE_DIRECTORY : StorageAttributes::TYPE_FILE,
                $file->getMTime(),
                $file->getSize()
            );
        }
        return new DirectoryListing($files);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->rename($source, $destination);
        } catch (Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    /* public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $stream = $this->client->read($source);
            $this->client->put($destination, $stream);
        } catch (Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    } */
}
