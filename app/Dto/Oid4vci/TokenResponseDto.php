<?php

declare(strict_types=1);

namespace App\Dto\Oid4vci;

class TokenResponseDto
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $cNonce,
        public readonly ?int $cNonceExpiresIn,
        public readonly string $tokenType,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'] ?? '',
            cNonce: $data['c_nonce'] ?? null,
            cNonceExpiresIn: isset($data['c_nonce_expires_in']) ? (int) $data['c_nonce_expires_in'] : null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }
}
