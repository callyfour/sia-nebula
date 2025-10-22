<?php
require "../php/db.php";
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid order ID.");
}

$orderId = (int)$_GET['id'];

// Fetch order details
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Fetch user
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$order['user_id']]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch order items
$itemsStmt = $pdo->prepare("
    SELECT p.name, p.price, oi.quantity 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= htmlspecialchars($orderId) ?></title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <style>
        :root {
    --accent-red: #ff1744;
    --bg-primary: #0f0f0f;
    --bg-card: #1a1a1a;
    --text-primary: #ffffff;
    --text-secondary: #cccccc;
    --border-color: #2a2a2a;
    --shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
    --shadow-hover: 0 10px 25px rgba(255, 23, 68, 0.3);
        }

        body {
            font-family: "Poppins", sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 40px;
            max-width: 900px;
            margin: auto;
        }

        .invoice-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .invoice-container:hover {
            box-shadow: var(--shadow-hover);
            border-color: var(--accent-red);
        }

        h1 {
            text-align: center;
            color: var(--accent-red);
            font-size: 1.8rem;
            margin-bottom: 30px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .invoice-header div {
            flex: 1;
            min-width: 280px;
        }

        .invoice-header h2 {
            margin-bottom: 8px;
            color: var(--accent-red);
            font-size: 1.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        table th, table td {
            padding: 12px 14px;
            text-align: left;
        }

        table th {
            background: #222;
            color: var(--accent-red);
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px solid var(--border-color);
        }

        table tr:nth-child(even) td {
            background: #1f1f1f;
        }

        table tr:nth-child(odd) td {
            background: #181818;
        }

        .total {
            text-align: right;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent-red);
            margin-top: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            opacity: 0.8;
        }

        /* Print optimization */
        @media print {
            body {
                background: #fff;
                color: #000;
                padding: 0;
                margin: 0;
            }
            .invoice-container {
                background: #fff;
                color: #000;
                border: none;
                box-shadow: none;
            }
            .print-btn {
                display: none;
            }
        }

        /* Print Button (matches admin theme) */
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 25px;
            padding: 10px 20px;
            background: var(--accent-red);
            color: var(--text-primary);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(255, 23, 68, 0.3);
        }

        .print-btn:hover {
            background: #ff3b60;
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: transparent;
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(255, 23, 68, 0.2);
        }

        .back-btn:hover {
            background: var(--accent-red);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }


    </style>
</head>
<body>
    <div class="action-buttons">
        <a href="orders.php" class="back-btn">
            <i class='bx bxs-left-arrow-alt'></i> Back
        </a>
        <a href="#" class="print-btn" onclick="window.print()">
            <i class='bx bxs-printer'></i> Print
        </a>
    </div>


    <div class="invoice-container">
        <h1>Invoice #<?= htmlspecialchars($order['id']) ?></h1>

        <div class="invoice-header">
            <div>
                <h2>Billing Info</h2>
                <p><strong>Name:</strong> <?= htmlspecialchars($user['name'] ?? 'N/A') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <h2>Order Details</h2>
                <p><strong>Date:</strong> <?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></p>
                <p><strong>Status:</strong> <?= ucfirst($order['status']) ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price (₱)</th>
                    <th>Qty</th>
                    <th>Subtotal (₱)</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; ?>
                <?php foreach ($items as $item): 
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= number_format($item['price'], 2) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($subtotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="total">Total: ₱<?= number_format($total, 2) ?></p>

        <div class="footer">
            <p>Thank you for your purchase!</p>
            <p><small>This is a system-generated invoice — no signature required.</small></p>
        </div>
    </div>
</body>
</html>
