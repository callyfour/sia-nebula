<?php
session_start();
require "db.php";

$orderId = $_GET['id'] ?? null;

if (!$orderId) {
    die("Order ID is missing.");
}

// Fetch the order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Fetch items from order_items table
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, p.name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $order['total_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order #<?= htmlspecialchars($order['id']) ?></title>
<link rel="stylesheet" href="../style/order_view.css" />
</head>
<body>
    <div class="order-wrapper">
        <div class="order-container">
            <a href="javascript:history.back()" class="back-button">← Back to Orders</a>
            
            <h2 class="order-title">Order #<?= htmlspecialchars($order['id']) ?></h2>
            
            <div class="order-details">
                <p><strong>Status:</strong> <span class="status <?= htmlspecialchars($order['status'] ?? 'paid') ?>"><?= htmlspecialchars(ucfirst($order['status'] ?? 'paid')) ?></span></p>
                <p><strong>Shipping To:</strong> <?= htmlspecialchars($order['shipping_name'] ?? '-') ?>, <?= htmlspecialchars($order['shipping_address'] ?? '-') ?></p>
                <p><strong>Placed On:</strong> <?= htmlspecialchars($order['created_at'] ?? '-') ?></p>
            </div>

            <h3 class="order-subtitle">Items</h3>
            <?php if (empty($items)): ?>
                <p>No items found in this order.</p>
            <?php else: ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td data-label="Product"><?= htmlspecialchars($item['name'] ?? 'Unknown') ?></td>
                                <td data-label="Price">₱<?= number_format($item['price'], 2) ?></td>
                                <td data-label="Qty"><?= $item['quantity'] ?></td>
                                <td data-label="Subtotal">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 class="order-total">Total: ₱<?= number_format($total, 2) ?></h3>
        </div>
    </div>
</body>
</html>