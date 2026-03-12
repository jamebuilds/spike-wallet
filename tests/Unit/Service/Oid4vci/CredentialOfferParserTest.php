<?php

use App\Service\Oid4vci\CredentialOfferParser;

it('parses a credential offer from inline json', function () {
    $offer = json_encode([
        'credential_issuer' => 'https://issuer.example.com',
        'credential_configuration_ids' => ['IdentityCredential'],
        'grants' => [
            'urn:ietf:params:oauth:grant-type:pre-authorized_code' => [
                'pre-authorized_code' => 'code-123',
            ],
        ],
    ]);

    $uri = 'openid-credential-offer://?credential_offer='.urlencode($offer);

    $parser = new CredentialOfferParser;
    $result = $parser->parse($uri);

    expect($result->credentialIssuer)->toBe('https://issuer.example.com');
    expect($result->credentialConfigurationIds)->toBe(['IdentityCredential']);
    expect($result->preAuthorizedCode)->toBe('code-123');
    expect($result->txCode)->toBeNull();
});

it('parses a credential offer with tx_code', function () {
    $offer = json_encode([
        'credential_issuer' => 'https://issuer.example.com',
        'credential_configuration_ids' => ['BankId'],
        'grants' => [
            'urn:ietf:params:oauth:grant-type:pre-authorized_code' => [
                'pre-authorized_code' => 'code-456',
                'tx_code' => ['value' => 'pin-789'],
            ],
        ],
    ]);

    $uri = 'openid-credential-offer://?credential_offer='.urlencode($offer);

    $parser = new CredentialOfferParser;
    $result = $parser->parse($uri);

    expect($result->preAuthorizedCode)->toBe('code-456');
    expect($result->txCode)->toBe('pin-789');
});

it('throws when neither credential_offer nor credential_offer_uri is present', function () {
    $parser = new CredentialOfferParser;
    $parser->parse('openid-credential-offer://?other_param=value');
})->throws(InvalidArgumentException::class);
