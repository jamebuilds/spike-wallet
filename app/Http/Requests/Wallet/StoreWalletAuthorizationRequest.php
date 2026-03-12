<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'credential_id' => ['required', 'uuid', 'exists:sd_jwt_credentials,id'],
            'selected_claims' => ['required', 'array', 'min:1'],
            'selected_claims.*' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'nonce' => ['required', 'string'],
            'response_uri' => ['required', 'string', 'url'],
            'state' => ['nullable', 'string'],
            'definition_id' => ['required', 'string'],
            'descriptor_id' => ['required', 'string'],
        ];
    }
}
