<?php

declare(strict_types=1);

namespace App\Dto\Oid4vci;

class CredentialResponseDto
{
    public function __construct(
        public readonly string $credential,
        public readonly ?string $cNonce,
        public readonly ?int $cNonceExpiresIn,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credential: $data['credential'] ?? '',
            cNonce: $data['c_nonce'] ?? null,
            cNonceExpiresIn: isset($data['c_nonce_expires_in']) ? (int) $data['c_nonce_expires_in'] : null,
        );
    }
}
