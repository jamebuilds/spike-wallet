<?php

declare(strict_types=1);

namespace App\Dto\Oid4vp;

use InvalidArgumentException;

class AuthorizationRequestDto
{
    /**
     * @param  array<string, mixed>  $presentationDefinition
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $responseUri,
        public readonly string $responseType,
        public readonly string $nonce,
        public readonly ?string $state,
        public readonly array $presentationDefinition,
        public readonly string $responseMode,
    ) {
        if (empty($this->clientId)) {
            throw new InvalidArgumentException('client_id cannot be empty');
        }

        if (empty($this->responseUri)) {
            throw new InvalidArgumentException('response_uri cannot be empty');
        }

        if (empty($this->nonce)) {
            throw new InvalidArgumentException('nonce cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'response_uri' => $this->responseUri,
            'response_type' => $this->responseType,
            'nonce' => $this->nonce,
            'state' => $this->state,
            'presentation_definition' => $this->presentationDefinition,
            'response_mode' => $this->responseMode,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'] ?? '',
            responseUri: $data['response_uri'] ?? '',
            responseType: $data['response_type'] ?? 'vp_token',
            nonce: $data['nonce'] ?? '',
            state: $data['state'] ?? null,
            presentationDefinition: $data['presentation_definition'] ?? [],
            responseMode: $data['response_mode'] ?? 'direct_post',
        );
    }
}
