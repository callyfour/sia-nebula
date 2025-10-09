<?php
session_start();
require "db.php";
require "../vendor/autoload.php";

\Stripe\Stripe::setApiKey("sk_test_51SBbPeFY7PfuKJeXlIKyQFBXSNhTgG9GQAJmgS4IMbzfK6dDtEwH8CjQhXHDKI9EPBhmD0fUBSvO5Jtpc7QXy9GS00Rm99vv0j");

$userId = $_SESSION['user']['id'] ?? null;
$session_id = $_GET['session_id'] ?? null;

if (!$userId) {
    header("Location: login.php");
    exit;
}

if (!$session_id) {
    die("No session ID provided.");
}

// Retrieve Stripe session
try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);
} catch (\Exception $e) {
    die("Invalid Stripe session ID.");
}

// Find the order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE stripe_session_id = ?");
$stmt->execute([$session_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Update order status to 'paid' if not already
if ($order['status'] !== 'paid') {
    $pdo->prepare("UPDATE orders SET status = 'paid' WHERE stripe_session_id = ?")
        ->execute([$session_id]);
}

// Fetch order items from database
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, p.name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total dynamically
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
}

$name = htmlspecialchars($order['shipping_name']);
$address = htmlspecialchars($order['shipping_address']);

// Clear user's cart
$pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Confirmation</title>
<link rel="stylesheet" href="../style/navbar.css" />
<link rel="stylesheet" href="../style/cart.css" />
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
<style>
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 4px;
    margin: 20px 0;
    text-align: center;
}
.success-message a {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
}
</style>
</head>
<body>
<div class="navbar">
    <div class="nav-logo" onclick="window.location.href='index.php'">
        <img src="../assets/logo.png" alt="Logo" />
    </div>
</div>

<div class="cart-wrapper">
    <div class="shopping-cart">
        <h2>Order Confirmation</h2>
        <div class="success-message">
            <h3>Payment Successful!</h3>
            <p>Thank you, <?= $name ?>. Your order has been confirmed.</p>
            <p>Total: ₱ <?= number_format($totalPrice, 2) ?></p>
            <a href="shop.php">Continue Shopping</a> | 
            <a href="orders.php">Track My Order</a> |
            <a href="index.php" class="home-btn">Go to Home</a>
        </div>

        <?php if (!empty($cartItems)): ?>
            <h3>Order Summary</h3>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #444;">
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr style="border-bottom:1px solid #333;">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td>₱ <?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱ <?= number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3>Total: ₱ <?= number_format($totalPrice, 2) ?></h3>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
