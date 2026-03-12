<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletIssuanceRequest extends FormRequest
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
            'credential_issuer' => ['required', 'string'],
            'credential_endpoint' => ['required', 'string'],
            'token_endpoint' => ['required', 'string'],
            'pre_authorized_code' => ['required', 'string'],
            'credential_configuration_id' => ['required', 'string'],
            'credential_format' => ['required', 'string'],
            'credential_type' => ['nullable', 'string'],
            'credential_definition' => ['nullable', 'array'],
            'credential_definition.type' => ['nullable', 'array'],
            'credential_definition.type.*' => ['string'],
            'tx_code' => ['nullable', 'string'],
        ];
    }
}
