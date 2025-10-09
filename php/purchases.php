<?php
session_start();
require "db.php";

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header("Location: login.php");
    exit;
}

// Fetch only completed/paid orders
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE user_id = ? AND status = 'paid' 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Purchases</title>
<link rel="stylesheet" href="../style/navbar.css" />
<link rel="stylesheet" href="../style/order.css" />
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
    <div class="navbar">
        <div class="nav-logo" onclick="window.location.href='index.php'">
            <img src="../assets/logo.png" alt="Logo" />
        </div>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="shop.php">Shop Parts</a></li>
            <li><a href="orders.php">My Orders</a></li>
            <li><a href="purchases.php" class="active">My Purchases</a></li>
        </ul>
    </div>

    <div class="order-wrapper">
        <div class="order-container">
            <h2 class="order-title">My Purchases</h2>

            <?php if (empty($purchases)): ?>
                <p>You haven’t completed any purchases yet. <a href="shop.php">Start shopping</a></p>
            <?php else: ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $purchase): 
                            // Fetch items for this order
                            $stmtItems = $pdo->prepare("
                                SELECT oi.quantity, oi.price, p.name
                                FROM order_items oi
                                JOIN products p ON oi.product_id = p.id
                                WHERE oi.order_id = ?
                            ");
                            $stmtItems->execute([$purchase['id']]);
                            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($purchase['id']) ?></td>
                                <td>
                                    <?php foreach ($items as $item): ?>
                                        <?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>)<br>
                                    <?php endforeach; ?>
                                </td>
                                <td>₱<?= number_format($purchase['total_amount'], 2) ?></td>
                                <td><?= htmlspecialchars($purchase['created_at']) ?></td>
                                <td><a href="order_view.php?id=<?= $purchase['id'] ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
