<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\SdJwt;

use Lcobucci\JWT\Encoding\JoseEncoder;

final class Base64Url
{
    private static ?JoseEncoder $encoder = null;

    private static function encoder(): JoseEncoder
    {
        return self::$encoder ??= new JoseEncoder;
    }

    public static function encode(string $data): string
    {
        return self::encoder()->base64UrlEncode($data);
    }

    public static function decode(string $data): string
    {
        return self::encoder()->base64UrlDecode($data);
    }
}
