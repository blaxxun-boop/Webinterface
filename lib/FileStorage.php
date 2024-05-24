<?php

namespace Amp\Http\Server\Session;

use Amp\File as file;
use Amp\Serialization\CompressingSerializer;
use Amp\Serialization\NativeSerializer;
use Amp\Serialization\Serializer;
use Revolt\EventLoop;

final class FileStorage implements SessionStorage
{
    public const DEFAULT_SESSION_LIFETIME = 3600;
    public const DEFAULT_SESSION_CLEANING_CYCLE = 1800;

    private string $directory;
    private string $prefix;
    private Serializer $serializer;

    public function __construct(string $directory, string $prefix = "", ?Serializer $serializer = null, int $sessionLifetime = self::DEFAULT_SESSION_LIFETIME, int $sessionCleaningCycle = self::DEFAULT_SESSION_CLEANING_CYCLE)
    {
        $this->directory = $directory;
        $this->prefix = $prefix;
        $this->serializer = $serializer ?? new CompressingSerializer(new NativeSerializer);
        EventLoop::unreference(EventLoop::repeat($sessionCleaningCycle, static function() use ($prefix, $directory, $sessionLifetime) {
            foreach (file\listFiles($directory) as $file) {
                try {
                    if (($prefix === "" || \str_starts_with($file, $prefix)) && (file\getStatus("$directory/$file")["stat"] ?? PHP_INT_MAX) < time() - $sessionLifetime) {
                        file\deleteFile($file);
                    }
                } catch (file\FilesystemException $e) { }
            }
        }));
    }

    public function read(string $id): array
    {
		try {
			$result = file\read("{$this->directory}/{$this->prefix}$id");
		} catch (file\FilesystemException $e) {
			return [];
		} catch (\Throwable $error) {
			throw new SessionException("Couldn't read data for session '${id}'", 0, $error);
		}

        try {
            $data = $this->serializer->unserialize($result);
        } catch (\Throwable $error) {
            throw new SessionException("Couldn't read data for session '${id}'", 0, $error);
        }

        try {
            \Amp\File\touch("{$this->directory}/{$this->prefix}$id");
        } catch (\Throwable $error) {
            throw new SessionException("Couldn't renew expiry for session '{$id}'", 0, $error);
        }

        return $data;
    }

    public function write(string $id, array $data): void
    {
        if (empty($data)) {
            try {
                file\deleteFile("{$this->directory}/{$this->prefix}$id");
            } catch (\Throwable $error) {
                throw new SessionException("Couldn't delete session '{$id}''", 0, $error);
            }

            return;
        }

        try {
            $serializedData = $this->serializer->serialize($data);
        } catch (\Throwable $error) {
            throw new SessionException("Couldn't serialize data for session '{$id}'", 0, $error);
        }

        try {
            file\write("{$this->directory}/{$this->prefix}$id", $serializedData);
        } catch (\Throwable $error) {
            throw new SessionException("Couldn't persist data for session '{$id}'", 0, $error);
        }
    }
}
