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
        ];
    }
}
