<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\SdJwt;

final class Disclosure
{
    private function __construct(
        public readonly string $encoded,
        public readonly string $salt,
        public readonly string $claimName,
        public readonly mixed $claimValue,
    ) {}

    public static function fromEncoded(string $encoded): self
    {
        $json = Base64Url::decode($encoded);

        /** @var array{0: string, 1: string, 2: mixed} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(
            encoded: $encoded,
            salt: $data[0],
            claimName: $data[1],
            claimValue: $data[2],
        );
    }

    public static function create(string $claimName, mixed $claimValue): self
    {
        $salt = Base64Url::encode(random_bytes(16));

        $json = json_encode([$salt, $claimName, $claimValue], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $encoded = Base64Url::encode($json);

        return new self(
            encoded: $encoded,
            salt: $salt,
            claimName: $claimName,
            claimValue: $claimValue,
        );
    }

    public function hash(): string
    {
        return Base64Url::encode(hash('sha256', $this->encoded, binary: true));
    }
}
