<?php

declare(strict_types=1);

namespace App\Service\Oid4vci;

use App\Models\User;
use App\Service\Oid4vp\HolderKeyService;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class ProofOfPossessionBuilder
{
    public function __construct(
        private readonly HolderKeyService $holderKeyService,
    ) {}

    public function build(User $user, string $credentialIssuer, string $cNonce): string
    {
        $encoder = new JoseEncoder;
        $builder = new Builder($encoder, ChainedFormatter::withUnixTimestampDates());

        $key = InMemory::plainText($this->holderKeyService->getPrivateKeyPem($user));
        $publicJwk = $this->holderKeyService->getPublicKeyJwk($user);
        $didJwk = $this->buildDidJwk($publicJwk);

        $token = $builder
            ->withHeader('typ', 'openid4vci-proof+jwt')
            ->withHeader('jwk', $publicJwk)
            ->permittedFor($credentialIssuer)
            ->issuedAt(new DateTimeImmutable)
            ->withClaim('nonce', $cNonce)
            ->getToken(new Sha256, $key);

        return $token->toString();
    }

    /**
     * @param  array{kty: string, crv: string, x: string, y: string}  $publicJwk
     */
    private function buildDidJwk(array $publicJwk): string
    {
        $jwkJson = json_encode($publicJwk, JSON_UNESCAPED_SLASHES);

        return 'did:jwk:'.rtrim(strtr(base64_encode($jwkJson), '+/', '-_'), '=');
    }
}
