<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWalletIssuanceRequest;
use App\Service\Oid4vci\CredentialEndpointService;
use App\Service\Oid4vci\CredentialOfferParser;
use App\Service\Oid4vci\CredentialStorageService;
use App\Service\Oid4vci\IssuerMetadataResolver;
use App\Service\Oid4vci\ProofOfPossessionBuilder;
use App\Service\Oid4vci\TokenEndpointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WalletCredentialIssuanceController extends Controller
{
    public function create(
        Request $request,
        CredentialOfferParser $offerParser,
        IssuerMetadataResolver $metadataResolver,
    ): Response|RedirectResponse {
        $request->validate([
            'credential_offer_url' => ['required', 'string'],
        ]);

        try {
            $offer = $offerParser->parse($request->input('credential_offer_url'));
        } catch (\Throwable $e) {
            Log::warning('Failed to parse credential offer', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'Failed to parse the credential offer URL.');
        }

        try {
            $metadata = $metadataResolver->resolve($offer->credentialIssuer);
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve issuer metadata', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'Failed to resolve issuer metadata.');
        }

        $credentialConfigurationId = $offer->credentialConfigurationIds[0] ?? '';
        $configSupported = $metadata->credentialConfigurationsSupported[$credentialConfigurationId] ?? [];
        $credentialFormat = $configSupported['format'] ?? 'vc+sd-jwt';
        $credentialDefinition = $configSupported['credential_definition'] ?? null;
        $credentialType = $configSupported['vct']
            ?? $credentialDefinition['type'][0]
            ?? null;

        return Inertia::render('wallet/issuance/create', [
            'credentialIssuer' => $offer->credentialIssuer,
            'credentialConfigurationId' => $credentialConfigurationId,
            'credentialFormat' => $credentialFormat,
            'credentialDefinition' => $credentialDefinition,
            'credentialType' => $credentialType,
            'tokenEndpoint' => $metadata->tokenEndpoint,
            'credentialEndpoint' => $metadata->credentialEndpoint,
            'preAuthorizedCode' => $offer->preAuthorizedCode,
            'txCode' => $offer->txCode,
        ]);
    }

    public function store(
        StoreWalletIssuanceRequest $request,
        TokenEndpointService $tokenService,
        ProofOfPossessionBuilder $popBuilder,
        CredentialEndpointService $credentialService,
        CredentialStorageService $storageService,
    ): RedirectResponse {
        $user = $request->user();

        try {
            $tokenResponse = $tokenService->exchange(
                tokenEndpoint: $request->validated('token_endpoint'),
                preAuthorizedCode: $request->validated('pre_authorized_code'),
                txCode: $request->validated('tx_code'),
            );

            $proofJwt = $popBuilder->build(
                user: $user,
                credentialIssuer: $request->validated('credential_issuer'),
                cNonce: $tokenResponse->cNonce ?? '',
            );

            $credentialResponse = $credentialService->request(
                credentialEndpoint: $request->validated('credential_endpoint'),
                accessToken: $tokenResponse->accessToken,
                proofJwt: $proofJwt,
                credentialConfigurationId: $request->validated('credential_configuration_id'),
                format: $request->validated('credential_format'),
                vct: $request->validated('credential_type'),
                credentialDefinition: $request->validated('credential_definition'),
            );

            $storageService->store($user, $credentialResponse->credential);

            Log::info('Credential received and stored', [
                'issuer' => $request->validated('credential_issuer'),
                'user_id' => $user->id,
            ]);

            return redirect()->route('wallet.index')->with('success', 'Credential received and stored successfully.');
        } catch (\Throwable $e) {
            Log::error('Credential issuance failed', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'An error occurred while receiving the credential.');
        }
    }
}
