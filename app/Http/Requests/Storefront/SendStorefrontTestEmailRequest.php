<?php

namespace App\Http\Requests\Storefront;

use App\Services\Storefront\StorefrontMailService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SendStorefrontTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('storefront.settings');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(StorefrontMailService::NOTIFY_TYPES)],
            'email' => ['nullable', 'email', 'max:191'],
        ];
    }

    /** Same {success, msg} JSON shape as the rest of the settings page AJAX. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'msg' => $validator->errors()->first(),
        ], 422));
    }
}
