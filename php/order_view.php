<?php
session_start();
require "db.php";

$orderId = $_GET['id'] ?? null;
if (!$orderId) die("Order ID is missing.");

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("Order not found.");

// Fetch order items with product images
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, p.name, p.image 
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
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
<div class="order-wrapper">
  <div class="order-container">
    <a href="javascript:history.back()" class="back-button">
      <i class='bx bx-arrow-back'></i> Back to Orders
    </a>

    <h2 class="order-title">Order #<?= htmlspecialchars($order['id']) ?></h2>

    <div class="order-info">
      <p><strong>Status:</strong> 
        <span class="status <?= htmlspecialchars($order['status'] ?? 'paid') ?>">
          <?= htmlspecialchars(ucfirst($order['status'] ?? 'paid')) ?>
        </span>
      </p>
      <p><strong>Shipping To:</strong> 
        <?= htmlspecialchars($order['shipping_name'] ?? '-') ?>, 
        <?= htmlspecialchars($order['shipping_address'] ?? '-') ?>
      </p>
      <p><strong>Placed On:</strong> <?= htmlspecialchars($order['created_at'] ?? '-') ?></p>
    </div>

    <h3 class="order-subtitle" style="margin-top: 30px;">Ordered Items</h3>

    <?php if (empty($items)): ?>
      <p style="color: var(--text-muted); text-align: center;">No items found in this order.</p>
    <?php else: ?>
      <table class="items-table" style="width:100%; border-collapse:collapse; text-align:left;">
        <thead>
          <tr>
            <th style="width: 60px;">Image</th>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td data-label="Image">
                <?php if (!empty($item['image'])): ?>
                  <img src="../uploads/<?= htmlspecialchars($item['image']) ?>" 
                       alt="<?= htmlspecialchars($item['name']) ?>" 
                       style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border:1px solid var(--border-color);">
                <?php else: ?>
                  <div style="width:60px;height:60px;border-radius:8px;background:#222;display:flex;align-items:center;justify-content:center;color:#555;font-size:0.8rem;">No Img</div>
                <?php endif; ?>
              </td>
              <td data-label="Product" style="color:var(--text-primary);font-weight:500;">
                <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
              </td>
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
