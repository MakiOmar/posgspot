<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Allowlisted contact fields for Passport contacts-read (never includes business secrets).
 *
 * @mixin \App\Contact
 */
class ApiContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'business_id' => (int) $this->business_id,
            'type' => $this->type,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'landline' => $this->landline,
            'alternate_number' => $this->alternate_number,
            'contact_status' => $this->contact_status,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'zip_code' => $this->zip_code,
            'tax_number' => $this->tax_number,
            'total_rp' => $this->total_rp,
            'total_rp_used' => $this->total_rp_used,
            'total_rp_expired' => $this->total_rp_expired,
            'balance' => $this->balance ?? null,
            'total_invoice' => $this->total_invoice ?? null,
            'invoice_received' => $this->invoice_received ?? null,
            'total_sell_return' => $this->total_sell_return ?? null,
            'sell_return_paid' => $this->sell_return_paid ?? null,
        ];
    }
}
