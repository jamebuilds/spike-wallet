<?php

declare(strict_types=1);

namespace App\Http\Resources\Wallet;

use App\Models\SdJwtCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SdJwtCredential */
class SdJwtCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issuer' => $this->issuer,
            'vct' => $this->vct,
            'disclosed_claims' => $this->disclosed_claims,
            'format' => $this->format,
            'raw_sd_jwt' => $this->raw_sd_jwt,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
