<?php

use App\Models\SdJwtCredential;
use App\Models\User;

it('requires authentication to access wallet', function () {
    $this->get('/wallet')->assertRedirect('/login');
});

it('displays the wallet dashboard with credentials', function () {
    $user = User::factory()->create();
    SdJwtCredential::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/index')
            ->has('credentials', 3)
        );
});

it('shows empty state when user has no credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/index')
            ->has('credentials', 0)
        );
});

it('only shows credentials belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    SdJwtCredential::factory()->count(2)->create(['user_id' => $user->id]);
    SdJwtCredential::factory()->count(3)->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get('/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('credentials', 2)
        );
});
