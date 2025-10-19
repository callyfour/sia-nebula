<?php
session_start();
require "db.php";

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(["error" => "not_logged_in"]);
    exit;
}

$userId = $_SESSION['user']['id'];
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    echo json_encode(["error" => "invalid_product"]);
    exit;
}

// Check if already in wishlist
$check = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
$check->execute([$userId, $productId]);

if ($check->fetch()) {
    // Remove
    $del = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $del->execute([$userId, $productId]);
    echo json_encode(["inWishlist" => false]);
} else {
    // Add
    $add = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $add->execute([$userId, $productId]);
    echo json_encode(["inWishlist" => true]);
}
?>
