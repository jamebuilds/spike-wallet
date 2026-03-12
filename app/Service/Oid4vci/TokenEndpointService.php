<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Dto\Oid4vci\TokenResponseDto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TokenEndpointService
{
    public function exchange(string $tokenEndpoint, string $preAuthorizedCode, ?string $txCode = null): TokenResponseDto
    {
        $formData = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:pre-authorized_code',
            'pre-authorized_code' => $preAuthorizedCode,
        ];

        if ($txCode !== null) {
            $formData['tx_code'] = $txCode;
        }

        $response = Http::asForm()->post($tokenEndpoint, $formData);

        if (! $response->successful()) {
            throw new RuntimeException('Token exchange failed (status: '.$response->status().'): '.$response->body());
        }

        return TokenResponseDto::fromArray($response->json());
    }
}
