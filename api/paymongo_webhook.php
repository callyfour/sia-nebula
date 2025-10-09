<?php
$secret = "whsk_f8Us2kHu5va3RW2GeZLoDSYx"; // Get this from your PayMongo dashboard
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

$expected_sig = hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected_sig, $sig_header)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);

