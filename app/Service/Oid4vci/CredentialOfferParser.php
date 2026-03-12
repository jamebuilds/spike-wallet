<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Dto\Oid4vci\CredentialOfferDto;
use Illuminate\Support\Facades\Http;

class CredentialOfferParser
{
    public function parse(string $uri): CredentialOfferDto
    {
        $query = $this->extractQueryString($uri);

        parse_str($query, $params);

        if (isset($params['credential_offer_uri'])) {
            $offerData = Http::get($params['credential_offer_uri'])->json();
        } elseif (isset($params['credential_offer'])) {
            $offerData = is_string($params['credential_offer'])
                ? json_decode($params['credential_offer'], true, flags: JSON_THROW_ON_ERROR)
                : $params['credential_offer'];
        } else {
            throw new \InvalidArgumentException('Missing credential_offer or credential_offer_uri parameter');
        }

        $grants = $offerData['grants'] ?? [];
        $preAuthGrant = $grants['urn:ietf:params:oauth:grant-type:pre-authorized_code'] ?? [];

        return new CredentialOfferDto(
            credentialIssuer: $offerData['credential_issuer'] ?? '',
            credentialConfigurationIds: $offerData['credential_configuration_ids'] ?? [],
            preAuthorizedCode: $preAuthGrant['pre-authorized_code'] ?? '',
            txCode: isset($preAuthGrant['tx_code']) ? ($preAuthGrant['tx_code']['value'] ?? null) : null,
        );
    }

    private function extractQueryString(string $uri): string
    {
        if (preg_match('/\?(.+)$/', $uri, $matches)) {
            return $matches[1];
        }

        $parsed = parse_url($uri);

        return $parsed['query'] ?? $parsed['fragment'] ?? '';
    }
}
