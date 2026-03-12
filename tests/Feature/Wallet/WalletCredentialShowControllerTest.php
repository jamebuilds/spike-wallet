<?php

use App\Models\SdJwtCredential;
use App\Models\User;

it('displays a credential detail page', function () {
    $user = User::factory()->create();
    $credential = SdJwtCredential::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get("/wallet/{$credential->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wallet/show')
            ->has('credential')
            ->where('credential.id', $credential->id)
        );
});

it('prevents viewing another users credential', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $credential = SdJwtCredential::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get("/wallet/{$credential->id}")
        ->assertForbidden();
});

it('can delete own credential', function () {
    $user = User::factory()->create();
    $credential = SdJwtCredential::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete("/wallet/{$credential->id}")
        ->assertRedirect('/wallet');

    $this->assertDatabaseMissing('sd_jwt_credentials', ['id' => $credential->id]);
});

it('prevents deleting another users credential', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $credential = SdJwtCredential::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->delete("/wallet/{$credential->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('sd_jwt_credentials', ['id' => $credential->id]);
});
