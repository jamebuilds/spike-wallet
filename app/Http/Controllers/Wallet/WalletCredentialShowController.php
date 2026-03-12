<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\SdJwtCredentialResource;
use App\Models\SdJwtCredential;
use Inertia\Inertia;
use Inertia\Response;

class WalletCredentialShowController extends Controller
{
    public function __invoke(SdJwtCredential $sdJwtCredential): Response
    {
        return Inertia::render('wallet/show', [
            'credential' => (new SdJwtCredentialResource($sdJwtCredential))->resolve(),
        ]);
    }
}
