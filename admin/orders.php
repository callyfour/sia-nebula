<?php
session_start();
require "../php/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$ordersStmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?");
$ordersStmt->execute([$perPage, $offset]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <style>
        /* ---------- Pagination (Modern) ---------- */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 32px 0;
            flex-wrap: wrap;
        }
        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition-modern);
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            background: var(--bg-card);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        .pagination a.active {
            background: var(--accent-red);
            color: var(--text-primary);
            box-shadow: 0 6px 12px rgba(255,23,68,0.3);
            transform: scale(1.05);
        }
        .pagination a:hover:not(.disabled) {
            background: rgba(255,23,68,0.2);
            color: var(--accent-red);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 16px rgba(255,23,68,0.3);
        }
        .pagination a.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        /* Optional: Empty state style for table */
        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
            font-style: italic;
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            font-size: 14px;
            background: var(--accent-red);
            color: var(--text-primary);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: #ff4d6d;
            transform: translateY(-1px);
        }

    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo">
            <i class='bx bxs-cog'></i> Admin Panel
        </div>
        <nav>
            <a href="admin.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="orders.php" class="active"><i class='bx bxs-receipt'></i> Orders</a>
            <a href="products.php"><i class='bx bxs-box'></i> Products</a>
            <a href="../php/logout.php"><i class='bx bxs-log-out'></i> Logout</a>
        </nav>
    </aside>

    <main class="content">
        <header class="content-header"><h1>Orders</h1></header>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($orders)): ?>
                        <tr><td colspan="6" class="empty-state">No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach($orders as $o): ?>
                        <tr>
                            <td><?= $o['id'] ?></td>
                            <td><?= $o['user_id'] ?></td>
                            <td>₱ <?= number_format($o['total_amount'] ?? 0,2) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($o['status'] ?? 'pending') ?>">
                                    <?= ucfirst($o['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit_order.php?id=<?= $o['id'] ?>" class="btn-edit">
                                    <i class='bx bxs-edit'></i> Edit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>" class="prev">&laquo;</a>
            <?php endif; ?>
            <?php for($i=1;$i<=$totalPages;$i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>" class="next">&raquo;</a>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
