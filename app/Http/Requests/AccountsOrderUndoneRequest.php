<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountsOrderUndoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (int) $user->business_id === (int) $this->route('business_id');
    }

    /**
     * Only sales-order (`so-`) and POS sell (`pos-`) ledger rows are reset; storefront rows are not.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'storefront_order_id' => ['required', 'string', 'max:191', 'regex:/^(so|pos)-\d+$/'],
            'storefront_line_key' => 'required|string|max:191',
            'accounts_order_id' => 'nullable|integer|min:1',
        ];
    }
}
