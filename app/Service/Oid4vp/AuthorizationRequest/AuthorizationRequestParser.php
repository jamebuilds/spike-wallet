<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\AuthorizationRequest;

use App\Dto\Oid4vp\AuthorizationRequestDto;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;

class AuthorizationRequestParser
{
    public function parse(string $uri): AuthorizationRequestDto
    {
        $query = $this->extractQueryString($uri);

        parse_str($query, $params);

        if (isset($params['request_uri'])) {
            $jwtClaims = $this->resolveRequestUri($params['request_uri']);

            $params = array_merge($jwtClaims, $params);

            unset($params['request_uri']);
        }

        $presentationDefinition = $this->resolvePresentationDefinition($params);

        $responseUri = $params['response_uri'] ?? $params['redirect_uri'] ?? '';

        return AuthorizationRequestDto::fromArray([
            'client_id' => $params['client_id'] ?? '',
            'response_uri' => $responseUri,
            'response_type' => $params['response_type'] ?? 'vp_token',
            'nonce' => $params['nonce'] ?? '',
            'state' => $params['state'] ?? null,
            'presentation_definition' => $presentationDefinition,
            'response_mode' => $params['response_mode'] ?? 'direct_post',
        ]);
    }

    private function extractQueryString(string $uri): string
    {
        if (preg_match('/\?(.+)$/', $uri, $matches)) {
            return $matches[1];
        }

        $parsed = parse_url($uri);

        return $parsed['query'] ?? $parsed['fragment'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function resolvePresentationDefinition(array $params): array
    {
        if (isset($params['presentation_definition'])) {
            $value = $params['presentation_definition'];

            return is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        }

        if (isset($params['presentation_definition_uri'])) {
            $response = Http::get($params['presentation_definition_uri']);

            return $response->json();
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRequestUri(string $requestUri): array
    {
        $response = Http::get($requestUri);

        $jwt = trim($response->body());

        $parser = new Parser(new JoseEncoder);

        /** @var Plain $token */
        $token = $parser->parse($jwt);

        $claims = [];
        foreach ($token->claims()->all() as $key => $value) {
            $claims[$key] = $value;
        }

        return $claims;
    }
}
