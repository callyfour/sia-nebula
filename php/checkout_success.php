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

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);
} catch (\Exception $e) {
    die("Invalid Stripe session ID.");
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE stripe_session_id = ?");
$stmt->execute([$session_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) die("Order not found.");

if ($order['status'] !== 'paid') {
    $pdo->prepare("UPDATE orders SET status = 'paid' WHERE stripe_session_id = ?")
        ->execute([$session_id]);
}

$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, oi.product_id, p.name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");

$stmt->execute([$order['id']]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
}

$name = htmlspecialchars($order['shipping_name']);
$address = htmlspecialchars($order['shipping_address']);

$pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);

if ($order['status'] !== 'paid') {
    // Mark order as paid
    $pdo->prepare("UPDATE orders SET status = 'paid' WHERE stripe_session_id = ?")
        ->execute([$session_id]);

    // Decrease stock for each product in this order
    $stmtStock = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
    foreach ($cartItems as $item) {
        $qty = $item['quantity'] ?? 1;
        $stmtStock->execute([$qty, $item['product_id'] ?? null]);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Confirmation</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
<link rel="stylesheet" href="../style/navbar.css" />
<link rel="stylesheet" href="../style/cart.css" />
<style>
body {
  background: var(--bg-primary);
  color: var(--text-primary);
  font-family: "Poppins", sans-serif;
  display: flex;
  justify-content: center;
  padding: 140px 20px;
  min-height: 100vh;
}

.shopping-cart {
  background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
  color: var(--text-primary);
  padding: 40px;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow);
  width: 100%;
  max-width: 900px;
  text-align: center;
  border: 1px solid var(--border-color);
}

h2 {
  color: var(--text-primary);
  font-weight: 700;
  margin-bottom: 25px;
}

.success-message {
  background: linear-gradient(135deg, rgba(0,255,127,0.15), rgba(0,255,127,0.05));
  color: #d4fcd4;
  padding: 25px;
  border-radius: 12px;
  border: 1px solid rgba(0,255,127,0.3);
  margin-bottom: 30px;
  box-shadow: 0 0 25px rgba(0,255,127,0.1);
}

.success-message h3 {
  font-size: 26px;
  margin-bottom: 8px;
  color: #90ff90;
}

.success-message a {
  color: #00b4d8;
  text-decoration: none;
  font-weight: 600;
  margin: 0 8px;
}

.success-message a:hover {
  color: var(--accent-red);
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 25px;
  color: var(--text-secondary);
}

th, td {
  text-align: center;
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
}

th {
  color: var(--accent-red);
  text-transform: uppercase;
  font-size: 0.9rem;
}

tr:hover {
  background-color: rgba(255,255,255,0.03);
}

h3 {
  text-align: right;
  margin-top: 25px;
  color: var(--text-primary);
}
</style>
</head>
<body>
<div class="shopping-cart">
    <h2>Order Confirmation</h2>

    <div class="success-message" 
     style="background: linear-gradient(135deg, rgba(0,255,127,0.15), rgba(0,255,127,0.05)); 
            color: #d4fcd4; padding: 25px; border-radius: 12px; border: 1px solid rgba(0,255,127,0.3); 
            margin-bottom: 30px; box-shadow: 0 0 25px rgba(0,255,127,0.1); text-align: center;">
    <h3 style="font-size: 26px; margin-bottom: 8px; color: #90ff90; text-align: center;">
        <i class='bx bx-check-circle'></i> Payment Successful!
    </h3>
    <p style="margin: 6px 0;">Thank you, <strong><?= $name ?></strong> — your order has been confirmed.</p>
    <p style="margin: 6px 0;"><strong>Total:</strong> ₱ <?= number_format($totalPrice, 2) ?></p>
    <div style="margin-top: 12px;">
        <a href="shop.php" style="color:#00b4d8; text-decoration:none; font-weight:600; margin:0 8px;">Continue Shopping</a> |
        <a href="orders.php" style="color:#00b4d8; text-decoration:none; font-weight:600; margin:0 8px;">Track My Order</a> |
        <a href="index.php" style="color:#00b4d8; text-decoration:none; font-weight:600; margin:0 8px;">Go to Home</a>
    </div>
</div>


    <?php if (!empty($cartItems)): ?>
        <h3 style="text-align:left;">Order Summary</h3>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                    <tr>
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
</body>
</html>
