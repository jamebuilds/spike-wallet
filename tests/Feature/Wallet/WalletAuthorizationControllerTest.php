<?php

use App\Dto\Oid4vp\AuthorizationRequestDto;
use App\Models\SdJwtCredential;
use App\Models\User;
use App\Service\Oid4vp\AuthorizationRequest\AuthorizationRequestParser;
use App\Service\Oid4vp\SdJwt\SdJwtPresenter;
use App\Service\Oid4vp\VpTokenResponseService;
use Illuminate\Http\Client\Response;

it('requires auth_request_url parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/wallet/authorize')
        ->assertRedirect();
});

it('renders authorization consent page with matching credential', function () {
    $user = User::factory()->create();
    $credential = SdJwtCredential::factory()->create([
        'user_id' => $user->id,
        'vct' => 'IdentityCredential',
        'disclosed_claims' => ['given_name' => 'John', 'family_name' => 'Doe'],
    ]);

    $this->mock(AuthorizationRequestParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andReturn(new AuthorizationRequestDto(
            clientId: 'https://verifier.example.com',
            responseUri: 'https://verifier.example.com/callback',
            responseType: 'vp_token',
            nonce: 'nonce-123',
            state: 'state-123',
            presentationDefinition: [
                'id' => 'identity-check',
                'input_descriptors' => [
                    [
                        'id' => 'IdentityCredential',
                        'constraints' => [
                            'fields' => [
                                ['path' => ['$.given_name']],
                            ],
                        ],
                    ],
                ],
            ],
            responseMode: 'direct_post',
        ));
    });

    $this->actingAs($user)
        ->get('/wallet/authorize?auth_request_url=openid4vp://test')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/authorization/create')
            ->where('clientId', 'https://verifier.example.com')
            ->where('nonce', 'nonce-123')
            ->has('credentials', 1)
            ->has('requestedClaims')
        );
});

it('returns all matching credentials when multiple match', function () {
    $user = User::factory()->create();
    SdJwtCredential::factory()->create([
        'user_id' => $user->id,
        'vct' => 'IdentityCredential',
        'disclosed_claims' => ['given_name' => 'John'],
    ]);
    SdJwtCredential::factory()->create([
        'user_id' => $user->id,
        'vct' => 'IdentityCredential',
        'disclosed_claims' => ['given_name' => 'Jane'],
    ]);

    $this->mock(AuthorizationRequestParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andReturn(new AuthorizationRequestDto(
            clientId: 'https://verifier.example.com',
            responseUri: 'https://verifier.example.com/callback',
            responseType: 'vp_token',
            nonce: 'nonce-123',
            state: 'state-123',
            presentationDefinition: [
                'id' => 'identity-check',
                'input_descriptors' => [
                    [
                        'id' => 'IdentityCredential',
                        'constraints' => [
                            'fields' => [
                                ['path' => ['$.given_name']],
                            ],
                        ],
                    ],
                ],
            ],
            responseMode: 'direct_post',
        ));
    });

    $this->actingAs($user)
        ->get('/wallet/authorize?auth_request_url=openid4vp://test')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/authorization/create')
            ->has('credentials', 2)
        );
});

it('redirects with error when no matching credential found', function () {
    $user = User::factory()->create();

    $this->mock(AuthorizationRequestParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andReturn(new AuthorizationRequestDto(
            clientId: 'https://verifier.example.com',
            responseUri: 'https://verifier.example.com/callback',
            responseType: 'vp_token',
            nonce: 'nonce-123',
            state: null,
            presentationDefinition: [
                'id' => 'check',
                'input_descriptors' => [
                    [
                        'id' => 'NonExistentCredential',
                        'constraints' => ['fields' => []],
                    ],
                ],
            ],
            responseMode: 'direct_post',
        ));
    });

    $this->actingAs($user)
        ->get('/wallet/authorize?auth_request_url=openid4vp://test')
        ->assertRedirect('/wallet')
        ->assertSessionHas('error');
});

it('submits a VP token successfully', function () {
    $user = User::factory()->create();
    $credential = SdJwtCredential::factory()->create(['user_id' => $user->id]);

    $this->mock(SdJwtPresenter::class, function ($mock) {
        $mock->shouldReceive('present')->once()->andReturn('vp-token-string');
    });

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('successful')->andReturn(true);
    $mockResponse->shouldReceive('status')->andReturn(200);

    $this->mock(VpTokenResponseService::class, function ($mock) use ($mockResponse) {
        $mock->shouldReceive('buildPresentationSubmission')->once()->andReturn([
            'id' => 'sub-id',
            'definition_id' => 'def-id',
            'descriptor_map' => [],
        ]);
        $mock->shouldReceive('submit')->once()->andReturn($mockResponse);
    });

    $this->actingAs($user)
        ->post('/wallet/authorize', [
            'credential_id' => $credential->id,
            'selected_claims' => ['given_name'],
            'client_id' => 'https://verifier.example.com',
            'nonce' => 'nonce-123',
            'response_uri' => 'https://verifier.example.com/callback',
            'state' => 'state-123',
            'definition_id' => 'identity-check',
            'descriptor_id' => 'IdentityCredential',
        ])
        ->assertRedirect('/wallet')
        ->assertSessionHas('success');
});
