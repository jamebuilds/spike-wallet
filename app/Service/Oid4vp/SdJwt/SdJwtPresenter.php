<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\SdJwt;

use App\Models\User;
use App\Service\Oid4vp\HolderKeyService;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class SdJwtPresenter
{
    public function __construct(
        private readonly HolderKeyService $holderKeyService,
    ) {}

    /**
     * Create a VP Token appropriate for the credential format.
     *
     * @param  array<int, string>  $selectedDisclosureNames
     */
    public function present(User $user, SdJwtToken $token, array $selectedDisclosureNames, string $nonce, string $audience, string $format = 'vc+sd-jwt'): string
    {
        if ($format !== 'vc+sd-jwt') {
            return $this->buildVpJwt($user, $token->issuerJwtRaw, $nonce, $audience);
        }

        return $this->buildSdJwtPresentation($user, $token, $selectedDisclosureNames, $nonce, $audience);
    }

    /**
     * SD-JWT-VC presentation: issuer JWT + selected disclosures + KB-JWT.
     *
     * @param  array<int, string>  $selectedDisclosureNames
     */
    private function buildSdJwtPresentation(User $user, SdJwtToken $token, array $selectedDisclosureNames, string $nonce, string $audience): string
    {
        $selectedDisclosures = [];
        foreach ($selectedDisclosureNames as $name) {
            $disclosure = $token->findDisclosureByClaimName($name);
            if ($disclosure !== null) {
                $selectedDisclosures[] = $disclosure;
            }
        }

        $sdJwtWithoutKb = $token->issuerJwtRaw.'~';
        foreach ($selectedDisclosures as $disclosure) {
            $sdJwtWithoutKb .= $disclosure->encoded.'~';
        }

        $sdHash = Base64Url::encode(hash('sha256', $sdJwtWithoutKb, binary: true));

        $kbJwt = $this->buildKeyBindingJwt($user, $nonce, $audience, $sdHash);

        return $sdJwtWithoutKb.$kbJwt;
    }

    /**
     * Plain JWT VC presentation: wrap in a VP JWT envelope with nonce.
     */
    private function buildVpJwt(User $user, string $vcJwt, string $nonce, string $audience): string
    {
        $encoder = new JoseEncoder;
        $builder = new Builder($encoder, ChainedFormatter::withUnixTimestampDates());

        $key = InMemory::plainText($this->holderKeyService->getPrivateKeyPem($user));

        $token = $builder
            ->issuedAt(new DateTimeImmutable)
            ->permittedFor($audience)
            ->withClaim('nonce', $nonce)
            ->withClaim('vp', [
                '@context' => ['https://www.w3.org/2018/credentials/v1'],
                'type' => ['VerifiablePresentation'],
                'verifiableCredential' => [$vcJwt],
            ])
            ->getToken(new Sha256, $key);

        return $token->toString();
    }

    private function buildKeyBindingJwt(User $user, string $nonce, string $audience, string $sdHash): string
    {
        $encoder = new JoseEncoder;
        $builder = new Builder($encoder, ChainedFormatter::withUnixTimestampDates());

        $key = InMemory::plainText($this->holderKeyService->getPrivateKeyPem($user));

        $token = $builder
            ->withHeader('typ', 'kb+jwt')
            ->issuedAt(new DateTimeImmutable)
            ->permittedFor($audience)
            ->withClaim('nonce', $nonce)
            ->withClaim('sd_hash', $sdHash)
            ->getToken(new Sha256, $key);

        return $token->toString();
    }
}
