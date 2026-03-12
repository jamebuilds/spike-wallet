<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Dto\Oid4vci\IssuerMetadataDto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IssuerMetadataResolver
{
    public function resolve(string $credentialIssuer): IssuerMetadataDto
    {
        $issuerBase = rtrim($credentialIssuer, '/');

        $issuerMetadata = Http::get($issuerBase.'/.well-known/openid-credential-issuer')->json();

        if (! $issuerMetadata) {
            throw new RuntimeException('Failed to fetch issuer metadata from '.$issuerBase);
        }

        $tokenEndpoint = $issuerMetadata['token_endpoint'] ?? null;

        if (! $tokenEndpoint) {
            $oauthMetadata = Http::get($issuerBase.'/.well-known/oauth-authorization-server')->json();
            $tokenEndpoint = $oauthMetadata['token_endpoint'] ?? null;
        }

        if (! $tokenEndpoint) {
            throw new RuntimeException('Could not resolve token_endpoint for issuer '.$issuerBase);
        }

        return new IssuerMetadataDto(
            credentialIssuer: $issuerMetadata['credential_issuer'] ?? $credentialIssuer,
            credentialEndpoint: $issuerMetadata['credential_endpoint'] ?? '',
            tokenEndpoint: $tokenEndpoint,
            credentialConfigurationsSupported: $issuerMetadata['credential_configurations_supported'] ?? [],
        );
    }
}
