<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Models\SdJwtCredential;
use App\Models\User;
use App\Service\Oid4vp\SdJwt\SdJwtParser;

class CredentialStorageService
{
    public function __construct(
        private readonly SdJwtParser $sdJwtParser,
    ) {}

    public function store(User $user, string $rawCredential): SdJwtCredential
    {
        $token = $this->sdJwtParser->parse($rawCredential);

        $issuerClaims = $token->issuerJwt->claims()->all();
        $disclosedClaims = $token->getDisclosedClaims();

        $vc = $issuerClaims['vc'] ?? null;
        $issuer = $issuerClaims['iss'] ?? '';
        $vct = $issuerClaims['vct'] ?? null;

        if ($vc && ! $vct) {
            $vcIssuer = $vc['issuer'] ?? null;
            $issuer = is_array($vcIssuer) ? ($vcIssuer['id'] ?? $issuer) : ($vcIssuer ?? $issuer);
            $types = $vc['type'] ?? [];
            $vct = collect($types)->last(fn ($t) => $t !== 'VerifiableCredential' && $t !== 'VerifiableAttestation');

            if (empty($disclosedClaims) && isset($vc['credentialSubject'])) {
                $subject = $vc['credentialSubject'];
                unset($subject['id']);
                $disclosedClaims = $subject;
            }
        }

        return SdJwtCredential::create([
            'user_id' => $user->id,
            'raw_sd_jwt' => $rawCredential,
            'issuer_claims' => $issuerClaims,
            'disclosed_claims' => $disclosedClaims,
            'issuer' => $issuer,
            'vct' => $vct,
        ]);
    }
}
