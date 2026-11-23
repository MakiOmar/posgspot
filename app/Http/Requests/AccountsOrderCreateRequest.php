<?php

namespace App\Http\Requests;

use App\BusinessLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AccountsOrderCreateRequest extends FormRequest
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
            'id' => 'required',
            'location_id' => 'required|integer',
            'total' => 'required|numeric',
            'line_items' => 'required|array|min:1',
            'billing' => 'nullable|array',
            'shipping' => 'nullable|array',
            'discount_total' => 'nullable|numeric',
            'shipping_total' => 'nullable|numeric',
            'date_created' => 'nullable|string',
            'date_paid' => 'nullable|string',
            'payment_method_title' => 'nullable|string|max:191',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $businessId = (int) $this->route('business_id');
            $locationId = (int) $this->input('location_id');
            $belongs = BusinessLocation::where('business_id', $businessId)
                ->where('id', $locationId)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('location_id', 'Location does not belong to this business.');
            }
        });
    }
}
