<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Simple PSR-7 StreamInterface implementation using PHP's native stream functions.
 * Provides a zero-dependency solution for creating streams from strings.
 */

class Stream implements StreamInterface
{
    /** @var resource|null */
    private $stream;

    private bool $seekable;
    private bool $readable;
    private bool $writable;

    /**
     * @param resource|string $body Stream resource or string content
     * @param string $mode Mode for fopen (default: r+)
     */
    public function __construct($body = '', string $mode = 'r+')
    {
        if (is_string($body)) {
            $this->stream = fopen('php://temp', $mode);
            if ($this->stream === false) {
                throw new RuntimeException('Unable to create stream');
            }
            if ($body !== '') {
                fwrite($this->stream, $body);
                rewind($this->stream);
            }
        } elseif (is_resource($body)) {
            $this->stream = $body;
        } else {
            throw new RuntimeException('Body must be a string or resource');
        }

        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'] ?? false;
        $this->readable = $this->isReadableMode($meta['mode'] ?? '');
        $this->writable = $this->isWritableMode($meta['mode'] ?? '');
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->seekable = $this->readable = $this->writable = false;

        return $result;
    }

    public function getSize(): ?int
    {
        if (!isset($this->stream)) {
            return null;
        }

        $stats = fstat($this->stream);
        if ($stats !== false) {
            return $stats['size'];
        }

        return null;
    }

    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        $result = ftell($this->stream);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    public function eof(): bool
    {
        return !isset($this->stream) || feof($this->stream);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek to stream position');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        if (!$this->writable) {
            throw new RuntimeException('Cannot write to a non-writable stream');
        }

        $result = fwrite($this->stream, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new RuntimeException('Cannot read from non-readable stream');
        }

        if ($length < 0) {
            throw new RuntimeException('Length parameter must be >= 0');
        }

        if ($length === 0) {
            return '';
        }

        $result = fread($this->stream, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    private function isReadableMode(string $mode): bool
    {
        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    private function isWritableMode(string $mode): bool
    {
        return str_contains($mode, 'w')
            || str_contains($mode, 'a')
            || str_contains($mode, 'x')
            || str_contains($mode, 'c')
            || str_contains($mode, '+');
    }
}
