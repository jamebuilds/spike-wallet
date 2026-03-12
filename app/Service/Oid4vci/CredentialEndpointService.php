<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Dto\Oid4vci\CredentialResponseDto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CredentialEndpointService
{
    /**
     * @param  array<string, mixed>|null  $credentialDefinition
     */
    public function request(
        string $credentialEndpoint,
        string $accessToken,
        string $proofJwt,
        string $credentialConfigurationId,
        string $format = 'vc+sd-jwt',
        ?string $vct = null,
        ?array $credentialDefinition = null,
    ): CredentialResponseDto {
        $body = [
            'format' => $format,
            'proof' => [
                'proof_type' => 'jwt',
                'jwt' => $proofJwt,
            ],
        ];

        if ($credentialDefinition !== null) {
            $body['credential_definition'] = $credentialDefinition;
        } elseif ($vct !== null) {
            $body['vct'] = $vct;
        } else {
            $body['credential_identifier'] = $credentialConfigurationId;
        }

        $response = Http::withToken($accessToken)->post($credentialEndpoint, $body);

        if (! $response->successful()) {
            throw new RuntimeException('Credential request failed (status: '.$response->status().'): '.$response->body());
        }

        return CredentialResponseDto::fromArray($response->json());
    }
}
