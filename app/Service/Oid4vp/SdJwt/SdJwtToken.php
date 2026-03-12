<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\SdJwt;

use Lcobucci\JWT\Token\Plain;

final class SdJwtToken
{
    /**
     * @param  array<int, Disclosure>  $disclosures
     */
    public function __construct(
        public readonly string $issuerJwtRaw,
        public readonly Plain $issuerJwt,
        public readonly array $disclosures,
        public readonly ?string $keyBindingJwt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDisclosedClaims(): array
    {
        $claims = [];

        foreach ($this->disclosures as $disclosure) {
            $claims[$disclosure->claimName] = $disclosure->claimValue;
        }

        return $claims;
    }

    public function findDisclosureByClaimName(string $claimName): ?Disclosure
    {
        foreach ($this->disclosures as $disclosure) {
            if ($disclosure->claimName === $claimName) {
                return $disclosure;
            }
        }

        return null;
    }
}
