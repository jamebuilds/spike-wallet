<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\SdJwtCredentialResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $credentials = $request->user()->sdJwtCredentials()->latest()->get();

        return Inertia::render('wallet/index', [
            'credentials' => SdJwtCredentialResource::collection($credentials)->resolve(),
        ]);
    }
}
