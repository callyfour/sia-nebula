<?php
session_start();
require "../php/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$productsStmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ? OFFSET ?");
$productsStmt->execute([$perPage, $offset]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products</title>
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

        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
            font-style: italic;
        }

        .btn {
            margin-bottom: 20px;
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

        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            font-size: 14px;
            background: #555; /* dark gray or your choice */
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            margin-left: 6px;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            background: #ff4d4d; /* red on hover */
            transform: translateY(-1px);
        }


    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo"><i class='bx bxs-cog'></i> Admin Panel</div>
        <nav>
            <a href="admin.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="orders.php"><i class='bx bxs-receipt'></i> Orders</a>
            <a href="products.php" class="active"><i class='bx bxs-box'></i> Products</a>
            <a href="../php/logout.php"><i class='bx bxs-log-out'></i> Logout</a>
        </nav>
    </aside>

    <main class="content">
        <header class="content-header"><h1>Products</h1></header>

        <a href="product_add.php" class="btn"><i class='bx bx-plus'></i> Add Product</a>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Brand</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="5" class="empty-state">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>₱ <?= number_format($p['price'],2) ?></td>
                            <td><?= htmlspecialchars($p['brand']) ?></td>
                            <td><?= $p['stock'] ?? 0 ?></td>
                            <td>
                                <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn-edit">
                                    <i class='bx bx-edit'></i> Edit
                                </a>
                            <a href="product_delete.php?id=<?= $p['id'] ?>" 
                            class="btn-delete"
                            onclick="return confirm('Are you sure you want to delete this product?');">
                            <i class='bx bx-trash'></i> Delete
                            </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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
