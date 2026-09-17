<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\SellToUsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellToUsController extends StorefrontController
{
    public function __construct(private SellToUsService $sellToUs)
    {
    }

    public function meta()
    {
        if (! $this->sellToUs->isEnabled()) {
            return $this->jsonError('Sell to us is not available.', 404);
        }

        return $this->jsonSuccess($this->sellToUs->meta());
    }

    public function verifyInvoice(Request $request)
    {
        if (! $this->sellToUs->isEnabled()) {
            return $this->jsonError('Sell to us is not available.', 404);
        }

        $validated = $request->validate([
            'invoice_no' => 'required|string|max:191',
        ]);

        $result = $this->sellToUs->verifyInvoice(
            $this->businessId($request),
            $request->user(),
            $validated['invoice_no']
        );

        return $this->jsonSuccess($result);
    }

    public function store(Request $request)
    {
        if (! $this->sellToUs->isEnabled()) {
            return $this->jsonError('Sell to us is not available.', 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['account', 'disc', 'device'])],
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:191',
            'city' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:5000',
            'purchased_from_us' => 'nullable|boolean',
            'invoice_no' => 'nullable|string|max:191',
            'transaction_id' => 'nullable|integer|min:1',
            'details' => 'nullable',
            'photos' => 'nullable|array|max:'.$this->sellToUs->maxPhotos(),
            'photos.*' => 'file|image|max:'.$this->sellToUs->maxPhotoKb(),
        ]);

        // Multipart often sends purchased_from_us as "1"/"0"/"true".
        $validated['purchased_from_us'] = $request->boolean('purchased_from_us');

        $photos = [];
        if ($request->hasFile('photos')) {
            $photos = $request->file('photos');
            if (! is_array($photos)) {
                $photos = [$photos];
            }
        }

        $row = $this->sellToUs->createRequest(
            $this->businessId($request),
            $request->user(),
            $validated,
            array_values(array_filter($photos))
        );

        return $this->jsonSuccess($this->sellToUs->present($row), [], 201);
    }
}
