<?php

use App\Service\Oid4vp\SdJwt\Disclosure;

it('creates a disclosure with salt, claim name, and value', function () {
    $disclosure = Disclosure::create('given_name', 'John');

    expect($disclosure->claimName)->toBe('given_name');
    expect($disclosure->claimValue)->toBe('John');
    expect($disclosure->salt)->not->toBeEmpty();
    expect($disclosure->encoded)->not->toBeEmpty();
});

it('can round-trip encode and decode a disclosure', function () {
    $original = Disclosure::create('email', 'test@example.com');
    $decoded = Disclosure::fromEncoded($original->encoded);

    expect($decoded->claimName)->toBe('email');
    expect($decoded->claimValue)->toBe('test@example.com');
    expect($decoded->salt)->toBe($original->salt);
});

it('generates a hash of the disclosure', function () {
    $disclosure = Disclosure::create('given_name', 'John');
    $hash = $disclosure->hash();

    expect($hash)->not->toBeEmpty();
    expect($hash)->toBeString();
});

it('handles complex claim values', function () {
    $complexValue = ['street' => '123 Main St', 'city' => 'Singapore'];
    $disclosure = Disclosure::create('address', $complexValue);

    $decoded = Disclosure::fromEncoded($disclosure->encoded);

    expect($decoded->claimName)->toBe('address');
    expect($decoded->claimValue)->toBe($complexValue);
});
