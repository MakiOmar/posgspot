<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account deletion request</title>
</head>
<body>
    <!-- Operator notification for storefront deletion request -->
    <h1>Account deletion request</h1>
    <p>A storefront customer requested account deletion.</p>
    <ul>
        <li>Contact ID: {{ $contact->id }}</li>
        <li>Name: {{ $contact->name }}</li>
        <li>Email: {{ $contact->email }}</li>
        <li>Mobile: {{ $contact->mobile }}</li>
        <li>Requested at: {{ $contact->storefront_delete_requested_at }}</li>
    </ul>
    <p>Review in POS and process according to your privacy policy. Do not hard-delete without checking open orders.</p>
</body>
</html>
