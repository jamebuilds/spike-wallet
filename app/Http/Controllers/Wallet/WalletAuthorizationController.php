<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWalletAuthorizationRequest;
use App\Models\SdJwtCredential;
use App\Service\Oid4vp\AuthorizationRequest\AuthorizationRequestParser;
use App\Service\Oid4vp\AuthorizationRequest\PresentationDefinitionMatcher;
use App\Service\Oid4vp\SdJwt\SdJwtPresenter;
use App\Service\Oid4vp\VpTokenResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WalletAuthorizationController extends Controller
{
    public function create(
        Request $request,
        AuthorizationRequestParser $authRequestParser,
        PresentationDefinitionMatcher $matcher,
    ): Response|RedirectResponse {
        $request->validate([
            'auth_request_url' => ['required', 'string'],
        ]);

        try {
            $authRequest = $authRequestParser->parse($request->input('auth_request_url'));
        } catch (\Throwable $e) {
            Log::warning('Failed to parse authorization request', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'Failed to parse the authorization request URL.');
        }

        $credentials = $request->user()->sdJwtCredentials()->get();

        $matches = $matcher->findAllMatchingCredentials($authRequest->presentationDefinition, $credentials);

        if (empty($matches)) {
            return redirect()->route('wallet.index')->with('error', 'No credential matches the verifier\'s request.');
        }

        $definitionId = $authRequest->presentationDefinition['id'] ?? 'default';

        $matchingCredentials = array_map(fn (array $match) => [
            'id' => $match['credential']->id,
            'issuer' => $match['credential']->issuer,
            'vct' => $match['credential']->vct,
            'disclosed_claims' => $match['credential']->disclosed_claims,
            'format' => $match['credential']->format,
            'created_at' => $match['credential']->created_at?->toISOString(),
            'available_claims' => $match['available_claims'],
            'descriptor_id' => $match['descriptor_id'],
        ], $matches);

        return Inertia::render('wallet/authorization/create', [
            'credentials' => array_values($matchingCredentials),
            'requestedClaims' => $matches[0]['requested_claims'],
            'clientId' => $authRequest->clientId,
            'nonce' => $authRequest->nonce,
            'responseUri' => $authRequest->responseUri,
            'state' => $authRequest->state,
            'definitionId' => $definitionId,
        ]);
    }

    public function store(
        StoreWalletAuthorizationRequest $request,
        SdJwtPresenter $presenter,
        VpTokenResponseService $vpTokenResponseService,
    ): RedirectResponse {
        $credential = SdJwtCredential::findOrFail($request->validated('credential_id'));

        Gate::authorize('present', $credential);

        $user = $request->user();
        $token = $credential->parseSdJwt();

        $format = $credential->format;

        $vpToken = $presenter->present(
            user: $user,
            token: $token,
            selectedDisclosureNames: $request->validated('selected_claims'),
            nonce: $request->validated('nonce'),
            audience: $request->validated('client_id'),
            format: $format,
        );

        $submissionFormat = $credential->isSdJwt() ? 'vc+sd-jwt' : 'jwt_vp';

        $presentationSubmission = $vpTokenResponseService->buildPresentationSubmission(
            $request->validated('definition_id'),
            $request->validated('descriptor_id'),
            $submissionFormat,
        );

        try {
            $response = $vpTokenResponseService->submit(
                responseUri: $request->validated('response_uri'),
                vpToken: $vpToken,
                presentationSubmission: $presentationSubmission,
                state: $request->validated('state'),
            );

            Log::info('VP Token submitted', [
                'response_uri' => $request->validated('response_uri'),
                'status' => $response->status(),
            ]);

            if ($response->successful()) {
                return redirect()->route('wallet.index')->with('success', 'Verifiable Presentation submitted successfully.');
            }

            return redirect()->route('wallet.index')->with('error', 'Presentation submission failed (status: '.$response->status().').');
        } catch (\Throwable $e) {
            Log::error('VP Token submission exception', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'An error occurred while submitting the presentation.');
        }
    }
}
