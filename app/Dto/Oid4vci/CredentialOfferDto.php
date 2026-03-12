<?php

declare(strict_types=1);

namespace App\Dto\Oid4vci;

use InvalidArgumentException;

class CredentialOfferDto
{
    /**
     * @param  list<string>  $credentialConfigurationIds
     */
    public function __construct(
        public readonly string $credentialIssuer,
        public readonly array $credentialConfigurationIds,
        public readonly string $preAuthorizedCode,
        public readonly ?string $txCode = null,
    ) {
        if (empty($this->credentialIssuer)) {
            throw new InvalidArgumentException('credential_issuer cannot be empty');
        }

        if (empty($this->preAuthorizedCode)) {
            throw new InvalidArgumentException('pre-authorized_code cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'credential_issuer' => $this->credentialIssuer,
            'credential_configuration_ids' => $this->credentialConfigurationIds,
            'pre_authorized_code' => $this->preAuthorizedCode,
            'tx_code' => $this->txCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credentialIssuer: $data['credential_issuer'] ?? '',
            credentialConfigurationIds: $data['credential_configuration_ids'] ?? [],
            preAuthorizedCode: $data['pre_authorized_code'] ?? '',
            txCode: $data['tx_code'] ?? null,
        );
    }
}
