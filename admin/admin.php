<?php
session_start();
require "../php/db.php";
require "../vendor/autoload.php";

// ✅ Stripe API key (if needed for admin actions; currently not used here)
\Stripe\Stripe::setApiKey("sk_test_51SBbPeFY7PfuKJeXlIKyQFBXSNhTgG9GQAJmgS4IMbzfK6dDtEwH8CjQhXHDKI9EPBhmD0fUBSvO5Jtpc7QXy9GS00Rm99vv0j");

// ✅ Clean up old/orphan orders BEFORE processing (older than 1 hour, no Stripe session)
$pdo->exec("DELETE FROM orders WHERE stripe_session_id IS NULL AND created_at < NOW() - INTERVAL 1 HOUR");

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../php/login.php");
    exit;
}

// Check if the logged in user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// Pagination settings
$perPage = 10; // Limit: Show 10 items per page

// Handle delete order
if (isset($_GET['delete_order'])) {
    $orderId = (int)$_GET['delete_order'];
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
    $queryParams = $_GET;
    unset($queryParams['delete_order']);
    $redirectUrl = 'admin.php?' . http_build_query($queryParams) . '&msg=Order+deleted';
    header("Location: " . $redirectUrl);
    exit;
}

// Handle delete product
if (isset($_GET['delete_product'])) {
    $productId = (int)$_GET['delete_product'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
    $queryParams = $_GET;
    unset($queryParams['delete_product']);
    $redirectUrl = 'admin.php?' . http_build_query($queryParams) . '&msg=Product+deleted';
    header("Location: " . $redirectUrl);
    exit;
}

// Orders Pagination (only completed/paid orders with Stripe session)
$ordersPage = isset($_GET['orders_page']) ? max(1, (int)$_GET['orders_page']) : 1;
$ordersOffset = ($ordersPage - 1) * $perPage;
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE stripe_session_id IS NOT NULL ORDER BY created_at DESC LIMIT ? OFFSET ?");
$ordersStmt->execute([$perPage, $ordersOffset]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE stripe_session_id IS NOT NULL")->fetchColumn();
$totalOrdersPages = ceil($totalOrders / $perPage);

// Products Pagination
$productsPage = isset($_GET['products_page']) ? max(1, (int)$_GET['products_page']) : 1;
$productsOffset = ($productsPage - 1) * $perPage;
$productsStmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ? OFFSET ?");
$productsStmt->execute([$perPage, $productsOffset]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalProductsPages = ceil($totalProducts / $perPage);

// Function to generate pagination links (preserves other query params reliably)
function generatePagination($currentPage, $totalPages, $baseUrl, $paramName) {
    if ($totalPages <= 1) return '';
    
    // Get current query params without the pagination param
    $queryParams = $_GET;
    unset($queryParams[$paramName]);
    $baseQuery = http_build_query($queryParams);
    $separator = $baseQuery ? '&' : '?';
    
    $html = '<div class="pagination">';
    
    // Prev button
    $prevHref = ($currentPage > 1) ? $baseUrl . $separator . $paramName . '=' . ($currentPage - 1) . ($baseQuery ? '&' . $baseQuery : '') : '#';
    $html .= '<a href="' . $prevHref . '" class="pag-link' . ($currentPage <= 1 ? ' disabled' : '') . '"><i class="bx bx-chevron-left"></i> Prev</a>';
    
    // Page numbers (show up to 5 around current, with ellipsis)
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $firstHref = $baseUrl . $separator . $paramName . '=1' . ($baseQuery ? '&' . $baseQuery : '');
        $html .= '<a href="' . $firstHref . '" class="pag-link">1</a>';
        if ($start > 2) $html .= '<span class="pag-dots">...</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $pageHref = $baseUrl . $separator . $paramName . '=' . $i . ($baseQuery ? '&' . $baseQuery : '');
        $html .= '<a href="' . $pageHref . '" class="pag-link' . ($i === $currentPage ? ' active' : '') . '">' . $i . '</a>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span class="pag-dots">...</span>';
        $lastHref = $baseUrl . $separator . $paramName . '=' . $totalPages . ($baseQuery ? '&' . $baseQuery : '');
        $html .= '<a href="' . $lastHref . '" class="pag-link">' . $totalPages . '</a>';
    }
    
    // Next button
    $nextHref = ($currentPage < $totalPages) ? $baseUrl . $separator . $paramName . '=' . ($currentPage + 1) . ($baseQuery ? '&' . $baseQuery : '') : '#';
    $html .= '<a href="' . $nextHref . '" class="pag-link' . ($currentPage >= $totalPages ? ' disabled' : '') . '">Next <i class="bx bx-chevron-right"></i></a>';
    $html .= '</div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ✅ Modal Styles (updated to match theme) */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: var(--bg-card);
            padding: 32px;
            border-radius: 12px;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px var(--shadow);
        }
        .modal-content h3 {
            margin: 0 0 16px 0;
            color: var(--text-primary);
            font-size: 1.5rem;
        }
        .modal-content p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }
        .modal-buttons {
            display: flex;
            justify-content: space-around;
            gap: 12px;
        }
        .modal-buttons .btn {
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }
        .modal-buttons .confirm {
            background: var(--accent-red);
            color: var(--text-primary);
            border: 1px solid var(--accent-red);
        }
        .modal-buttons .confirm:hover {
            background: var(--dark-red);
            transform: translateY(-1px);
        }
        .modal-buttons .cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .modal-buttons .cancel:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Pagination Styles (Integrated with theme) */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 32px 0;
            padding: 16px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px var(--shadow);
        }
        .pag-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            background: transparent;
            color: var(--accent-red);
            text-decoration: none;
            border: 1px solid var(--accent-red);
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 44px;
            justify-content: center;
            font-size: 14px;
        }
        .pag-link:hover:not(.disabled) {
            background: var(--accent-red);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-hover);
        }
        .pag-link.active {
            background: var(--accent-red);
            color: var(--text-primary);
            border-color: var(--accent-red);
            box-shadow: 0 0 0 2px rgba(255, 23, 68, 0.3);
        }
        .pag-link.disabled {
            color: var(--text-secondary);
            border-color: var(--border);
            cursor: not-allowed;
            opacity: 0.5;
            transform: none !important;
        }
        .pag-dots {
            padding: 10px 6px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 64px 32px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--accent-red);
            opacity: 0.7;
        }
        .empty-state h3 {
            font-size: 1.5rem;
            margin: 0 0 12px 0;
            color: var(--text-primary);
        }
        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        /* Search/Filter */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .search-container {
            display: flex;
            gap: 12px;
            align-items: center;
            flex: 1;
            min-width: 200px;
        }
        .search-input {
            flex: 1;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-input::placeholder {
            color: var(--text-secondary);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--accent-red);
            box-shadow: 0 0 0 3px rgba(255, 23, 68, 0.1);
        }

        /* Status Badge for Orders */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-paid { background: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid #4caf50; }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        .status-cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class='bx bxs-cog'></i>
                <span>Admin Panel</span>
            </div>
            <nav>
                <a href="admin.php" class="active"><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="#orders"><i class='bx bxs-receipt'></i> Orders</a>
                <a href="#products"><i class='bx bxs-box'></i> Products</a>
                <a href="../php/logout.php"><i class='bx bxs-log-out'></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <header class="content-header">
                <h1>Admin Dashboard</h1>
                <?php if (isset($_GET['msg'])): ?>
                    <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>
            </header>

            <!-- Orders Section (Limited to 10 per page) -->
            <section id="orders" class="section">
                <h2>Orders (<?= $totalOrders ?> total) - Page <?= $ordersPage ?> of <?= $totalOrdersPages ?></h2>
                <div class="section-header">
                    <div></div> <!-- Spacer for alignment -->
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search orders by user ID, total, or date..." id="ordersSearch">
                        <button class="btn" onclick="filterOrders()"><i class='bx bx-search'></i> Search</button>
                    </div>
                </div>
                <div class="table-container">
                    <table id="ordersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class='bx bx-receipt'></i>
                                        <h3>No orders found</h3>
                                        <p>Completed orders (with Stripe payment) will appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                    <?php $statusClass = 'status-' . strtolower($o['status'] ?? 'pending'); ?>
                                    <tr>
                                        <td><?= $o['id'] ?></td>
                                        <td><?= $o['user_id'] ?></td>
                                        <td>₱ <?= number_format($o['total_amount'] ?? $o['total'], 2) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($o['status'] ?? 'Pending') ?></span></td>
                                        <td>
                                            <a href="#" class="delete-btn" data-type="order" data-id="<?= $o['id'] ?>">
                                                <i class='bx bxs-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= generatePagination($ordersPage, $totalOrdersPages, 'admin.php', 'orders_page') ?>
            </section>

            <!-- Products Section (Limited to 10 per page) -->
            <section id="products" class="section">
                <h2>Products (<?= $totalProducts ?> total) - Page <?= $productsPage ?> of <?= $totalProductsPages ?></h2>
                <div class="section-header">
                    <a href="product_add.php" class="btn"><i class='bx bx-plus'></i> Add Product</a>
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search products by name or brand..." id="productsSearch">
                        <button class="btn" onclick="filterProducts()"><i class='bx bx-search'></i> Search</button>
                    </div>
                </div>
                <div class="table-container">
                    <table id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Brand</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class='bx bx-box'></i>
                                        <h3>No products found</h3>
                                        <p>Add your first product to get started.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?= $p['id'] ?></td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td>₱ <?= number_format($p['price'], 2) ?></td>
                                        <td><?= htmlspecialchars($p['brand']) ?></td>
                                        <td>
                                            <a href="product_edit.php?id=<?= $p['id'] ?>" class="edit-btn"><i class='bx bxs-edit'></i> Edit</a> 
                                            <a href="#" class="delete-btn" data-type="product" data-id="<?= $p['id'] ?>">
                                                <i class='bx bxs-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= generatePagination($productsPage, $totalProductsPages, 'admin.php', 'products_page') ?>
            </section>
        </main>
    </div>

    <!-- ✅ Modal HTML -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>Are you sure?</h3>
            <p id="modalText">Do you really want to delete this item?</p>
            <div class="modal-buttons">
                <a href="#" id="confirmLink" class="btn confirm">Yes, Delete</a>
                <a href="#" id="cancelBtn" class="btn cancel">Cancel</a>
            </div>
        </div>
    </div>

    <script>
        // ✅ Modal JS (with page preservation)
        const modal = document.getElementById('confirmModal');
        const modalText = document.getElementById('modalText');
        const confirmLink = document.getElementById('confirmLink');
        const cancelBtn = document.getElementById('cancelBtn');

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.id;
                const type = this.dataset.type;

                // Preserve current page and other params in confirm link
                const currentPage = type === 'order' ? '<?= $ordersPage ?>' : '<?= $productsPage ?>';
                const pageParam = type === 'order' ? 'orders_page' : 'products_page';
                modalText.textContent = `Do you really want to delete this ${type}?`;
                confirmLink.href = `?delete_${type}=${id}&${pageParam}=${currentPage}` + (<?= json_encode($_GET) ?> ? '&' + new URLSearchParams(<?= json_encode($_GET) ?>).toString().replace(/delete_${type}=\d+/, '') : '');
                
                modal.style.display = 'flex';
            });
        });

        cancelBtn.addEventListener('click', e => {
            e.preventDefault();
            modal.style.display = 'none';
        });

        window.addEventListener('click', e => {
            if (e.target === modal) modal.style.display = 'none';
        });

        // ✅ Basic Client-Side Search/Filter (enhances organization)
        // Note: This is client-side for demo; for production, implement server-side search with GET params
        function filterOrders() {
            const input = document.getElementById('ordersSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('ordersTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) { // Skip header
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length - 1; j++) { // Skip actions column
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        function filterProducts() {
            const input = document.getElementById('productsSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('productsTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) { // Skip header
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length - 1; j++) { // Skip actions column
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        // Auto-filter on input (debounced for performance)
        let timeoutOrders, timeoutProducts;
        document.getElementById('ordersSearch').addEventListener('input', () => {
            clearTimeout(timeoutOrders);
            timeoutOrders = setTimeout(filterOrders, 300);
        });
        document.getElementById('productsSearch').addEventListener('input', () => {
            clearTimeout(timeoutProducts);
            timeoutProducts = setTimeout(filterProducts, 300);
        });
    </script>
</body>
</html>