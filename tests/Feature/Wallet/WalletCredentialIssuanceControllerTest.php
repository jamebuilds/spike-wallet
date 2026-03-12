<?php

use App\Dto\Oid4vci\CredentialOfferDto;
use App\Dto\Oid4vci\CredentialResponseDto;
use App\Dto\Oid4vci\IssuerMetadataDto;
use App\Dto\Oid4vci\TokenResponseDto;
use App\Models\SdJwtCredential;
use App\Models\User;
use App\Service\Oid4vci\CredentialEndpointService;
use App\Service\Oid4vci\CredentialOfferParser;
use App\Service\Oid4vci\CredentialStorageService;
use App\Service\Oid4vci\IssuerMetadataResolver;
use App\Service\Oid4vci\ProofOfPossessionBuilder;
use App\Service\Oid4vci\TokenEndpointService;

it('requires credential_offer_url parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/wallet/receive')
        ->assertRedirect();
});

it('renders the issuance create page with valid offer', function () {
    $user = User::factory()->create();

    $this->mock(CredentialOfferParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andReturn(new CredentialOfferDto(
            credentialIssuer: 'https://issuer.example.com',
            credentialConfigurationIds: ['IdentityCredential'],
            preAuthorizedCode: 'pre-auth-code-123',
        ));
    });

    $this->mock(IssuerMetadataResolver::class, function ($mock) {
        $mock->shouldReceive('resolve')->once()->andReturn(new IssuerMetadataDto(
            credentialIssuer: 'https://issuer.example.com',
            credentialEndpoint: 'https://issuer.example.com/credential',
            tokenEndpoint: 'https://issuer.example.com/token',
            credentialConfigurationsSupported: [
                'IdentityCredential' => [
                    'format' => 'vc+sd-jwt',
                    'vct' => 'IdentityCredential',
                ],
            ],
        ));
    });

    $this->actingAs($user)
        ->get('/wallet/receive?credential_offer_url=openid-credential-offer://?credential_offer=test')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/issuance/create')
            ->where('credentialIssuer', 'https://issuer.example.com')
            ->where('preAuthorizedCode', 'pre-auth-code-123')
        );
});

it('redirects with error when offer parsing fails', function () {
    $user = User::factory()->create();

    $this->mock(CredentialOfferParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andThrow(new RuntimeException('Invalid offer'));
    });

    $this->actingAs($user)
        ->get('/wallet/receive?credential_offer_url=invalid')
        ->assertRedirect('/wallet')
        ->assertSessionHas('error');
});

it('stores credential via the issuance flow', function () {
    $user = User::factory()->create();

    $this->mock(TokenEndpointService::class, function ($mock) {
        $mock->shouldReceive('exchange')->once()->andReturn(new TokenResponseDto(
            accessToken: 'access-token-123',
            cNonce: 'c-nonce-123',
            cNonceExpiresIn: 300,
            tokenType: 'Bearer',
        ));
    });

    $this->mock(ProofOfPossessionBuilder::class, function ($mock) {
        $mock->shouldReceive('build')->once()->andReturn('proof-jwt-123');
    });

    $this->mock(CredentialEndpointService::class, function ($mock) {
        $mock->shouldReceive('request')->once()->andReturn(new CredentialResponseDto(
            credential: 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdfQ.sig~',
            cNonce: null,
            cNonceExpiresIn: null,
        ));
    });

    $this->mock(CredentialStorageService::class, function ($mock) {
        $mock->shouldReceive('store')->once()->andReturn(SdJwtCredential::factory()->make());
    });

    $this->actingAs($user)
        ->post('/wallet/receive', [
            'credential_issuer' => 'https://issuer.example.com',
            'credential_endpoint' => 'https://issuer.example.com/credential',
            'token_endpoint' => 'https://issuer.example.com/token',
            'pre_authorized_code' => 'pre-auth-code',
            'credential_configuration_id' => 'IdentityCredential',
            'credential_format' => 'vc+sd-jwt',
            'credential_type' => 'IdentityCredential',
        ])
        ->assertRedirect('/wallet')
        ->assertSessionHas('success');
});
