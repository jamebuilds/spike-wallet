<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\SdJwt;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;

final class SdJwtParser
{
    public function parse(string $sdJwt): SdJwtToken
    {
        $parts = explode('~', $sdJwt);

        $issuerJwtRaw = $parts[0];
        $parser = new Parser(new JoseEncoder);

        /** @var Plain $issuerJwt */
        $issuerJwt = $parser->parse($issuerJwtRaw);

        $disclosures = [];
        $keyBindingJwt = null;

        for ($i = 1; $i < count($parts); $i++) {
            $part = $parts[$i];

            if ($part === '') {
                continue;
            }

            if ($i === count($parts) - 1 && substr_count($part, '.') === 2) {
                $keyBindingJwt = $part;
            } else {
                $disclosures[] = Disclosure::fromEncoded($part);
            }
        }

        return new SdJwtToken(
            issuerJwtRaw: $issuerJwtRaw,
            issuerJwt: $issuerJwt,
            disclosures: $disclosures,
            keyBindingJwt: $keyBindingJwt,
        );
    }
}
