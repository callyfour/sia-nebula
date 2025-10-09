<?php
session_start();
require "db.php";

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header("Location: login.php");
    exit;
}

// Fetch all orders for this user
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure $orders is an array
if (!is_array($orders)) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Orders</title>
<link rel="stylesheet" href="../style/navbar.css" />
<link rel="stylesheet" href="../style/order.css" />
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-logo" onclick="window.location.href='index.php'">
            <img src="../assets/logo.png" alt="Logo" />
        </div>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="shop.php">Shop Parts</a></li>
            <li><a href="orders.php" class="active">My Orders</a></li>
            <li><a href="cart.php">My Cart</a></li>
        </ul>
    </div>

    <div class="order-wrapper">
        <div class="order-container">
            <h2 class="order-title">My Orders</h2>

            <?php if (empty($orders)): ?>
                <p>You haven’t placed any orders yet. <a href="shop.php">Shop now</a></p>
            <?php else: ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Placed On</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['id']) ?></td>
                                <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst($order['status'] ?? 'paid')) ?></td>
                                <td><?= htmlspecialchars($order['created_at']) ?></td>
                                <td><a href="order_view.php?id=<?= $order['id'] ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
