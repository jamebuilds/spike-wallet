<?php

namespace Database\Factories;

use App\Models\SdJwtCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SdJwtCredential>
 */
class SdJwtCredentialFactory extends Factory
{
    protected $model = SdJwtCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'raw_sd_jwt' => 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdfQ.sig~',
            'issuer_claims' => ['iss' => 'https://test-issuer.example.com'],
            'disclosed_claims' => ['given_name' => 'John', 'family_name' => 'Doe'],
            'issuer' => 'https://test-issuer.example.com',
            'vct' => 'IdentityCredential',
            'format' => 'vc+sd-jwt',
        ];
    }

    public function jwtVc(): static
    {
        return $this->state(fn () => [
            'raw_sd_jwt' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJpc3MiOiJodHRwczovL3Rlc3QtaXNzdWVyLmV4YW1wbGUuY29tIn0.sig',
            'issuer_claims' => [
                'iss' => 'https://test-issuer.example.com',
                'vc' => [
                    'type' => ['VerifiableCredential', 'BankId'],
                    'credentialSubject' => ['given_name' => 'John'],
                ],
            ],
            'vct' => 'BankId',
            'format' => 'jwt_vc_json',
        ]);
    }
}
