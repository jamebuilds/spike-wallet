<?php

use App\Service\Oid4vp\SdJwt\Disclosure;
use App\Service\Oid4vp\SdJwt\SdJwtParser;

it('parses an sd-jwt with disclosures', function () {
    $disclosure = Disclosure::create('given_name', 'John');
    $dummyJwt = 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdfQ.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    $sdJwt = $dummyJwt.'~'.$disclosure->encoded.'~';

    $parser = new SdJwtParser;
    $token = $parser->parse($sdJwt);

    expect($token->issuerJwtRaw)->toBe($dummyJwt);
    expect($token->disclosures)->toHaveCount(1);
    expect($token->disclosures[0]->claimName)->toBe('given_name');
    expect($token->disclosures[0]->claimValue)->toBe('John');
    expect($token->keyBindingJwt)->toBeNull();
});

it('parses disclosed claims as key-value pairs', function () {
    $d1 = Disclosure::create('given_name', 'John');
    $d2 = Disclosure::create('family_name', 'Doe');
    $dummyJwt = 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdfQ.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    $sdJwt = $dummyJwt.'~'.$d1->encoded.'~'.$d2->encoded.'~';

    $parser = new SdJwtParser;
    $token = $parser->parse($sdJwt);

    $claims = $token->getDisclosedClaims();

    expect($claims)->toBe([
        'given_name' => 'John',
        'family_name' => 'Doe',
    ]);
});

it('finds a disclosure by claim name', function () {
    $d1 = Disclosure::create('given_name', 'John');
    $d2 = Disclosure::create('family_name', 'Doe');
    $dummyJwt = 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdfQ.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    $sdJwt = $dummyJwt.'~'.$d1->encoded.'~'.$d2->encoded.'~';

    $parser = new SdJwtParser;
    $token = $parser->parse($sdJwt);

    $found = $token->findDisclosureByClaimName('family_name');
    expect($found)->not->toBeNull();
    expect($found->claimValue)->toBe('Doe');

    $notFound = $token->findDisclosureByClaimName('nonexistent');
    expect($notFound)->toBeNull();
});
