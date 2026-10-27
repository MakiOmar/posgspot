<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SearchCodCustomerEmailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('storefront.settings');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:191'],
        ];
    }
}
