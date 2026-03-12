<?php

declare(strict_types=1);

namespace App\Service\Oid4vp;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VpTokenResponseService
{
    /**
     * POST VP Token to verifier's response_uri via direct_post.
     *
     * @param  array<string, mixed>  $presentationSubmission
     */
    public function submit(
        string $responseUri,
        string $vpToken,
        array $presentationSubmission,
        ?string $state = null,
    ): Response {
        $payload = [
            'vp_token' => $vpToken,
            'presentation_submission' => json_encode($presentationSubmission, JSON_THROW_ON_ERROR),
        ];

        if ($state !== null) {
            $payload['state'] = $state;
        }

        try {
            return Http::asForm()->post($responseUri, $payload);
        } catch (Exception $exception) {
            Log::error('VP Token submission failed', [
                'response_uri' => $responseUri,
                'trace' => $exception,
            ]);

            report($exception);

            throw $exception;
        }
    }

    /**
     * Build a minimal presentation_submission object.
     *
     * @return array<string, mixed>
     */
    public function buildPresentationSubmission(string $definitionId, string $descriptorId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'definition_id' => $definitionId,
            'descriptor_map' => [
                [
                    'id' => $descriptorId,
                    'format' => 'vc+sd-jwt',
                    'path' => '$',
                ],
            ],
        ];
    }
}
