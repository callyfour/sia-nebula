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

// ✅ Get filter value
$filter = $_GET['filter'] ?? 'all';

// ✅ Build WHERE clause based on filter
$where = "";
if ($filter === 'lowstock') {
    $where = "WHERE stock < 5";
} elseif ($filter === 'outofstock') {
    $where = "WHERE stock = 0";
}

// ✅ Build and run product query (LIMIT and OFFSET must be injected directly)
$query = "SELECT * FROM products $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$productsStmt = $pdo->query($query);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Build and run total count query (for pagination)
$totalQuery = "SELECT COUNT(*) FROM products $where";
$totalProducts = $pdo->query($totalQuery)->fetchColumn();
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
        .filter-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .filter-form label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .filter-form select {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }

        .filter-form select:hover {
            border-color: #ff4d6d;
            box-shadow: 0 0 6px rgba(255, 23, 68, 0.3);
        }

        .filter-form select:focus {
            outline: none;
            border-color: #ff4d6d;
            box-shadow: 0 0 6px rgba(255, 23, 68, 0.3);
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
        <form method="GET" class="filter-form">
            <label for="filter">Filter:</label>
            <select name="filter" id="filter" onchange="this.form.submit()">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Products</option>
                <option value="lowstock" <?= $filter === 'lowstock' ? 'selected' : '' ?>>Low Stock (&lt; 5)</option>
                <option value="outofstock" <?= $filter === 'outofstock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </form>



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
                            <td>
                                <?php if (($p['stock'] ?? 0) <= 0): ?>
                                    <span style="color: #ff4d4d; font-weight: 600;">Out of Stock</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($p['stock']) ?>
                                <?php endif; ?>
                            </td>
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
                <a href="?filter=<?= $filter ?>&page=<?= $page-1 ?>" class="prev">&laquo;</a>
            <?php endif; ?>
            <?php for($i=1;$i<=$totalPages;$i++): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if($page < $totalPages): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $page+1 ?>" class="next">&raquo;</a>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>
