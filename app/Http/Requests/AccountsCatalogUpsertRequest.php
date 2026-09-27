<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountsCatalogUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (int) $user->business_id === (int) $this->route('business_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => 'required|in:game,card',
            'code' => 'nullable|string|max:64',
            'items' => 'required|array|min:1|max:32',
            'items.*.sku' => ['required', 'string', 'max:64', 'distinct', 'regex:/^ACCOUNTS-(GAME|CARD)-[A-Z0-9-]+$/'],
            'items.*.name' => 'required|string|max:191',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.active' => 'sometimes|boolean',
        ];
    }
}
