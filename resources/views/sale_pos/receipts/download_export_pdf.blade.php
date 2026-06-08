@include('sale_pos.receipts.download_pdf', [
    'receipt_details' => $receipt_details,
    'location_details' => $location_details ?? null,
    'is_email_attachment' => $is_email_attachment ?? false,
])
