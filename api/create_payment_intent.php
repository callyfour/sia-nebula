<?php
// File: api/create_payment_intent.php
require '../vendor/autoload.php';
require '../db.php';

use Paymongo\Paymongo;

$paymongo = new Paymongo('sk_test_uyfMtjkGA6sxZsRNwwVbhntN'); // Replace with your secret key

session_start();
$userId = $_SESSION['user']['id'] ?? null;

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Calculate total cart amount from DB
$stmt = $pdo->prepare("
    SELECT SUM(c.quantity * p.price) AS total
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$totalAmount = $stmt->fetchColumn();
$amountInCents = $totalAmount * 100; // PayMongo uses cents

// Create Payment Intent
$paymentIntent = $paymongo->paymentIntents->create([
    'data' => [
        'attributes' => [
            'amount' => $amountInCents,
            'currency' => 'PHP',
            'payment_method_allowed' => ['card'],
            'description' => 'Order Payment',
            'metadata' => [
                'user_id' => $userId
            ]
        ]
    ]
]);

echo json_encode([
    'status' => 'success',
    'client_secret' => $paymentIntent['data']['attributes']['client_key']
]);
