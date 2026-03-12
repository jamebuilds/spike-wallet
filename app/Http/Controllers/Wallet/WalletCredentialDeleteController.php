<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\SdJwtCredential;
use Illuminate\Http\RedirectResponse;

class WalletCredentialDeleteController extends Controller
{
    public function __invoke(SdJwtCredential $sdJwtCredential): RedirectResponse
    {
        $sdJwtCredential->delete();

        return redirect()->route('wallet.index')->with('success', 'Credential deleted successfully.');
    }
}
