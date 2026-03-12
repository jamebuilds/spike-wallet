<?php

declare(strict_types=1);

namespace App\Service\Oid4vp;

use App\Models\User;

class HolderKeyService
{
    /**
     * Get or generate the holder's EC P-256 key pair for KB-JWT signing.
     * Keys are stored on the User model (wallet_private_jwk / wallet_public_jwk).
     */
    public function ensureKeyPair(User $user): void
    {
        if ($user->wallet_private_jwk && $user->wallet_public_jwk) {
            return;
        }

        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);

        openssl_pkey_export($key, $pem);

        $publicJwk = $this->derivePublicKeyJwk($pem);

        $user->update([
            'wallet_private_jwk' => $pem,
            'wallet_public_jwk' => $publicJwk,
        ]);
    }

    public function getPrivateKeyPem(User $user): string
    {
        $this->ensureKeyPair($user);

        return $user->wallet_private_jwk;
    }

    /**
     * @return array{kty: string, crv: string, x: string, y: string}
     */
    public function getPublicKeyJwk(User $user): array
    {
        $this->ensureKeyPair($user);

        return $user->wallet_public_jwk;
    }

    /**
     * @return array{kty: string, crv: string, x: string, y: string}
     */
    private function derivePublicKeyJwk(string $pem): array
    {
        $key = openssl_pkey_get_private($pem);
        $details = openssl_pkey_get_details($key);

        $x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '=');
        $y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '=');

        return [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
        ];
    }
}
