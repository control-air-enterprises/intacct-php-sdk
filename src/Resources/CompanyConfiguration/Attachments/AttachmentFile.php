<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;

/**
 * A file to upload in an attachment's `files[]`, sent as JSON `{name, data}`.
 *
 * The data is plain base64 of the raw file bytes, which is what the spec examples send.
 * The spec also declares the field as format `base64zip`, described as "Base64-encoded
 * (zipped) binary file data". Whether Sage expects the bytes to be zipped before encoding
 * has not been verified against a live tenant, so the SDK does not zip. Verify uploads,
 * binary files in particular, against a sandbox before relying on them.
 */
final readonly class AttachmentFile
{
    private function __construct(
        public string $name,
        #[\SensitiveParameter]
        private string $data,
        public int $size,
    ) {
        Assert::notBlank($this->name, 'The attachment file name');
    }

    /**
     * @param  string  $name  The file name including its extension, such as "invoice.pdf".
     * @param  string  $contents  The raw file bytes; they are base64-encoded here.
     */
    public static function fromContents(string $name, #[\SensitiveParameter] string $contents): self
    {
        return new self($name, base64_encode($contents), strlen($contents));
    }

    /**
     * Reads the file at the moment it is called. The name defaults to the path's base name.
     */
    public static function fromPath(string $path, ?string $name = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgument(sprintf('The attachment file "%s" is not a readable file.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgument(sprintf('The attachment file "%s" could not be read.', $path));
        }

        return self::fromContents($name ?? basename($path), $contents);
    }

    public function base64Data(): string
    {
        return $this->data;
    }

    /** @return array{name: string, data: string} */
    public function toWriteArray(): array
    {
        return ['name' => $this->name, 'data' => $this->data];
    }

    /** @return array{name: string, size: int, data: string} */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'size' => $this->size,
            'data' => '[redacted]',
        ];
    }
}
