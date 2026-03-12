<?php

declare(strict_types=1);

namespace App\Dto\Oid4vci;

class IssuerMetadataDto
{
    /**
     * @param  array<string, mixed>  $credentialConfigurationsSupported
     */
    public function __construct(
        public readonly string $credentialIssuer,
        public readonly string $credentialEndpoint,
        public readonly string $tokenEndpoint,
        public readonly array $credentialConfigurationsSupported,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'credential_issuer' => $this->credentialIssuer,
            'credential_endpoint' => $this->credentialEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'credential_configurations_supported' => $this->credentialConfigurationsSupported,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credentialIssuer: $data['credential_issuer'] ?? '',
            credentialEndpoint: $data['credential_endpoint'] ?? '',
            tokenEndpoint: $data['token_endpoint'] ?? '',
            credentialConfigurationsSupported: $data['credential_configurations_supported'] ?? [],
        );
    }
}
