<?php

use App\Http\Controllers\Wallet\WalletAuthorizationController;
use App\Http\Controllers\Wallet\WalletCredentialDeleteController;
use App\Http\Controllers\Wallet\WalletCredentialIssuanceController;
use App\Http\Controllers\Wallet\WalletCredentialShowController;
use App\Http\Controllers\Wallet\WalletDashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('wallet')->group(function () {
        Route::get('/', WalletDashboardController::class)->name('wallet.index');
        Route::get('/authorize', [WalletAuthorizationController::class, 'create'])->name('wallet.authorize.create');
        Route::post('/authorize', [WalletAuthorizationController::class, 'store'])->name('wallet.authorize.store');
        Route::get('/receive', [WalletCredentialIssuanceController::class, 'create'])->name('wallet.receive.create');
        Route::post('/receive', [WalletCredentialIssuanceController::class, 'store'])->name('wallet.receive.store');
        Route::get('/{sdJwtCredential}', WalletCredentialShowController::class)
            ->can('view', 'sdJwtCredential')
            ->name('wallet.credentials.show');
        Route::delete('/{sdJwtCredential}', WalletCredentialDeleteController::class)
            ->can('delete', 'sdJwtCredential')
            ->name('wallet.credentials.destroy');
    });
});

require __DIR__.'/settings.php';
