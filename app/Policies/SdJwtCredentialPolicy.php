<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SdJwtCredential;
use App\Models\User;

class SdJwtCredentialPolicy
{
    public function view(User $user, SdJwtCredential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function present(User $user, SdJwtCredential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function delete(User $user, SdJwtCredential $credential): bool
    {
        return $credential->user_id === $user->id;
    }
}
