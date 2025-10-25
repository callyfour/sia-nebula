<?php
session_start();
require "../php/db.php";

// Regenerate session for security
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Check if user is logged in & admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// Date range filters (default to last 30 days)
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Quick filter presets
$preset = $_GET['preset'] ?? 'last30';
if ($preset === 'today') {
    $dateFrom = $dateTo = date('Y-m-d');
} elseif ($preset === 'last7') {
    $dateFrom = date('Y-m-d', strtotime('-7 days'));
    $dateTo = date('Y-m-d');
} elseif ($preset === 'last30') {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
    $dateTo = date('Y-m-d');
} elseif ($preset === 'thismonth') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
}

// ==================== REVENUE ANALYTICS ====================
$revenueQuery = "
    SELECT 
        DATE(created_at) as date,
        SUM(total_amount) as daily_revenue,
        COUNT(*) as order_count
    FROM orders 
    WHERE stripe_session_id IS NOT NULL 
    AND DATE(created_at) BETWEEN :date_from AND :date_to
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$revenueStmt = $pdo->prepare($revenueQuery);
$revenueStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$revenueData = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);

// Total revenue summary for current period
$totalRevenueQuery = "
    SELECT 
        COALESCE(SUM(total_amount), 0) as total,
        COALESCE(AVG(total_amount), 0) as average,
        COUNT(*) as orders
    FROM orders 
    WHERE stripe_session_id IS NOT NULL 
    AND DATE(created_at) BETWEEN :date_from AND :date_to
";
$totalRevenueStmt = $pdo->prepare($totalRevenueQuery);
$totalRevenueStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$revenueSummary = $totalRevenueStmt->fetch(PDO::FETCH_ASSOC);

// Previous period comparison
$daysDiff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
$prevDateFrom = date('Y-m-d', strtotime($dateFrom . " -{$daysDiff} days"));
$prevDateTo = date('Y-m-d', strtotime($dateTo . " -{$daysDiff} days"));

$prevRevenueStmt = $pdo->prepare($totalRevenueQuery);
$prevRevenueStmt->execute(['date_from' => $prevDateFrom, 'date_to' => $prevDateTo]);
$prevRevenueSummary = $prevRevenueStmt->fetch(PDO::FETCH_ASSOC);

// Calculate growth percentages
$revenueGrowth = $prevRevenueSummary['total'] > 0 
    ? (($revenueSummary['total'] - $prevRevenueSummary['total']) / $prevRevenueSummary['total']) * 100 
    : 0;
$ordersGrowth = $prevRevenueSummary['orders'] > 0 
    ? (($revenueSummary['orders'] - $prevRevenueSummary['orders']) / $prevRevenueSummary['orders']) * 100 
    : 0;

// ==================== PRODUCT PERFORMANCE ====================
$productPerformanceQuery = "
    SELECT 
        p.id,
        p.name,
        p.category,
        p.price,
        p.stock,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE o.stripe_session_id IS NOT NULL 
    AND DATE(o.created_at) BETWEEN :date_from AND :date_to
    GROUP BY p.id, p.name, p.category, p.price, p.stock
    ORDER BY revenue DESC
    LIMIT 10
";
$productPerfStmt = $pdo->prepare($productPerformanceQuery);
$productPerfStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$topProducts = $productPerfStmt->fetchAll(PDO::FETCH_ASSOC);

// Low performing products
$lowProductsQuery = "
    SELECT 
        p.id,
        p.name,
        p.category,
        p.price,
        p.stock,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE (o.stripe_session_id IS NOT NULL OR o.id IS NULL)
    AND (o.created_at IS NULL OR DATE(o.created_at) BETWEEN :date_from AND :date_to)
    GROUP BY p.id, p.name, p.category, p.price, p.stock
    HAVING units_sold < 5
    ORDER BY units_sold ASC, revenue ASC
    LIMIT 10
";
$lowProductsStmt = $pdo->prepare($lowProductsQuery);
$lowProductsStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$lowProducts = $lowProductsStmt->fetchAll(PDO::FETCH_ASSOC);

// Category performance
$categoryQuery = "
    SELECT 
        p.category,
        COUNT(DISTINCT p.id) as product_count,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE o.stripe_session_id IS NOT NULL 
    AND DATE(o.created_at) BETWEEN :date_from AND :date_to
    GROUP BY p.category
    ORDER BY revenue DESC
";
$categoryStmt = $pdo->prepare($categoryQuery);
$categoryStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== CUSTOMER INSIGHTS ====================
// Total customers and new customers
$totalCustomersQuery = "SELECT COUNT(DISTINCT user_id) as total FROM orders WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
$totalCustomersStmt = $pdo->prepare($totalCustomersQuery);
$totalCustomersStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$totalCustomers = $totalCustomersStmt->fetchColumn();

$newCustomersQuery = "
    SELECT COUNT(DISTINCT user_id) as new_customers
    FROM orders 
    WHERE user_id IN (
        SELECT user_id FROM orders 
        GROUP BY user_id 
        HAVING MIN(DATE(created_at)) BETWEEN :date_from AND :date_to
    )
";
$newCustomersStmt = $pdo->prepare($newCustomersQuery);
$newCustomersStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$newCustomers = $newCustomersStmt->fetchColumn();

// Customer purchase frequency
$customerFrequencyQuery = "
    SELECT 
        user_id,
        COUNT(*) as order_count,
        SUM(total_amount) as lifetime_value
    FROM orders 
    WHERE stripe_session_id IS NOT NULL 
    GROUP BY user_id
    ORDER BY lifetime_value DESC
    LIMIT 10
";
$customerFreqStmt = $pdo->query($customerFrequencyQuery);
$topCustomers = $customerFreqStmt->fetchAll(PDO::FETCH_ASSOC);

// Average customer lifetime value
$avgLifetimeQuery = "
    SELECT AVG(total_value) as avg_lifetime_value
    FROM (
        SELECT user_id, SUM(total_amount) as total_value
        FROM orders 
        WHERE stripe_session_id IS NOT NULL 
        GROUP BY user_id
    ) as customer_values
";
$avgLifetime = $pdo->query($avgLifetimeQuery)->fetchColumn();

// ==================== SALES DATA ====================
// Orders by status
$orderStatusQuery = "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(total_amount) as total_amount
    FROM orders 
    WHERE DATE(created_at) BETWEEN :date_from AND :date_to
    GROUP BY status
";
$orderStatusStmt = $pdo->prepare($orderStatusQuery);
$orderStatusStmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$ordersByStatus = $orderStatusStmt->fetchAll(PDO::FETCH_ASSOC);

// Conversion rate (completed orders vs total orders)
$completedOrders = 0;
$totalOrdersCount = array_sum(array_column($ordersByStatus, 'count'));
foreach ($ordersByStatus as $status) {
    if (in_array($status['status'], ['Completed', 'Shipped', 'Delivered'])) {
        $completedOrders += $status['count'];
    }
}
$conversionRate = $totalOrdersCount > 0 ? ($completedOrders / $totalOrdersCount) * 100 : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & Analytics Reports</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ==================== ANALYTICS PAGE STYLES ==================== */

/* Filter Section */
.analytics-filters {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    font-size: 14px;
    color: #e0e0e0;
}

.filter-group input, 
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #444;
    border-radius: 4px;
    font-size: 14px;
    background: #2a2a2a;
    color: #e0e0e0;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #dc2626;
}

.preset-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.preset-btn {
    padding: 8px 16px;
    border: 1px solid #444;
    background: #2a2a2a;
    color: #e0e0e0;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.preset-btn:hover, 
.preset-btn.active {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

/* Metrics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    border: 1px solid #333;
    transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
    border-color: #dc2626;
}

.metric-card h3 {
    font-size: 14px;
    color: #999;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.metric-card h3 i {
    font-size: 18px;
    color: #dc2626;
}

.metric-value {
    font-size: 32px;
    font-weight: bold;
    color: #e0e0e0;
    margin-bottom: 8px;
}

.metric-change {
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.metric-change.positive {
    color: #10b981;
}

.metric-change.negative {
    color: #dc2626;
}

.metric-change i {
    font-size: 16px;
}

/* Chart Containers */
.chart-container {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.chart-container h2 {
    margin-bottom: 20px;
    font-size: 18px;
    color: #e0e0e0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-container h2 i {
    color: #dc2626;
}

.chart-wrapper {
    position: relative;
    height: 300px;
}

/* Data Tables */
.data-table {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    border: 1px solid #333;
    overflow-x: auto;
}

.data-table h2 {
    margin-bottom: 15px;
    font-size: 18px;
    color: #e0e0e0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.data-table h2 i {
    color: #dc2626;
}

.data-table table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #2a2a2a;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dc2626;
    color: #e0e0e0;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #333;
    color: #b0b0b0;
}

.data-table tr:hover {
    background: #2a2a2a;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* Export Buttons */
.export-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.btn-export {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-csv {
    background: #dc2626;
    color: white;
    border: 1px solid #dc2626;
}

.btn-csv:hover {
    background: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.4);
}

.btn-print {
    background: #2a2a2a;
    color: #e0e0e0;
    border: 1px solid #444;
}

.btn-print:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.4);
}

/* Responsive Design */
@media (max-width: 768px) {
    .analytics-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .metric-value {
        font-size: 24px;
    }
    
    .chart-wrapper {
        height: 250px;
    }
    
    .data-table {
        overflow-x: scroll;
    }
}

/* Print Styles */
@media print {
    .sidebar, 
    .analytics-filters, 
    .export-buttons, 
    .btn,
    .preset-buttons {
        display: none !important;
    }
    
    .content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    .chart-container,
    .data-table,
    .metric-card {
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
        background: white;
        color: black;
    }
    
    .analytics-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .metric-card h3,
    .metric-value,
    .data-table th,
    .data-table td,
    .chart-container h2,
    .data-table h2 {
        color: black !important;
    }
}
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class='bx bxs-cog'></i> Admin Panel
            </div>
            <nav>
                <a href="admin.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="analytics.php" class="active"><i class='bx bxs-bar-chart-alt-2'></i> Analytics</a>
                <a href="orders.php"><i class='bx bxs-receipt'></i> Orders</a>
                <a href="products.php"><i class='bx bxs-box'></i> Products</a>
                <a href="users.php"><i class='bx bxs-user'></i> Users</a>
                <a href="audit_view.php"  <?= basename($_SERVER['PHP_SELF']) === 'audit_view.php' ? 'active' : '' ?>">
                    <i class='bx bx-history'></i> Audit Trail
                </a>
                <a href="../php/logout.php" onclick="return confirm('Are you sure you want to logout?');">
                    <i class='bx bxs-log-out'></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <header class="content-header">
                <h1><i class='bx bxs-bar-chart-alt-2'></i> Sales & Analytics Reports</h1>
            </header>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button class="btn-export btn-csv" onclick="exportToCSV()">
                    <i class='bx bxs-file'></i> Export to CSV
                </button>
                <button class="btn-export btn-print" onclick="window.print()">
                    <i class='bx bxs-printer'></i> Print Report
                </button>
            </div>

            <!-- Filters -->
            <div class="analytics-filters">
                <form method="GET" action="analytics.php">
                    <div class="preset-buttons">
                        <button type="submit" name="preset" value="today" class="preset-btn <?= $preset === 'today' ? 'active' : '' ?>">Today</button>
                        <button type="submit" name="preset" value="last7" class="preset-btn <?= $preset === 'last7' ? 'active' : '' ?>">Last 7 Days</button>
                        <button type="submit" name="preset" value="last30" class="preset-btn <?= $preset === 'last30' ? 'active' : '' ?>">Last 30 Days</button>
                        <button type="submit" name="preset" value="thismonth" class="preset-btn <?= $preset === 'thismonth' ? 'active' : '' ?>">This Month</button>
                    </div>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date_from">From Date:</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="filter-group">
                            <label for="date_to">To Date:</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <button type="submit" class="btn" style="align-self: flex-end;">Apply Filter</button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="analytics-grid">
                <div class="metric-card">
                    <h3><i class='bx bxs-wallet-alt'></i> Total Revenue</h3>
                    <div class="metric-value">₱<?= number_format($revenueSummary['total'], 2) ?></div>
                    <div class="metric-change <?= $revenueGrowth >= 0 ? 'positive' : 'negative' ?>">
                        <i class='bx bxs-<?= $revenueGrowth >= 0 ? 'up' : 'down' ?>-arrow'></i>
                        <?= number_format(abs($revenueGrowth), 1) ?>% vs previous period
                    </div>
                </div>

                <div class="metric-card">
                    <h3><i class='bx bxs-receipt'></i> Total Orders</h3>
                    <div class="metric-value"><?= number_format($revenueSummary['orders']) ?></div>
                    <div class="metric-change <?= $ordersGrowth >= 0 ? 'positive' : 'negative' ?>">
                        <i class='bx bxs-<?= $ordersGrowth >= 0 ? 'up' : 'down' ?>-arrow'></i>
                        <?= abs(number_format($ordersGrowth, 1)) ?>% vs previous period
                    </div>
                </div>

                <div class="metric-card">
                    <h3><i class='bx bxs-shopping-bag'></i> Average Order Value</h3>
                    <div class="metric-value">₱<?= number_format($revenueSummary['average'], 2) ?></div>
                    <div class="metric-change">
                        Per transaction
                    </div>
                </div>

                <div class="metric-card">
                    <h3><i class='bx bxs-check-circle'></i> Conversion Rate</h3>
                    <div class="metric-value"><?= number_format($conversionRate, 1) ?>%</div>
                    <div class="metric-change">
                        Completed orders
                    </div>
                </div>

                <div class="metric-card">
                    <h3><i class='bx bxs-user'></i> Total Customers</h3>
                    <div class="metric-value"><?= number_format($totalCustomers) ?></div>
                    <div class="metric-change positive">
                        <i class='bx bxs-user-plus'></i>
                        <?= number_format($newCustomers) ?> new
                    </div>
                </div>

                <div class="metric-card">
                    <h3><i class='bx bxs-heart'></i> Avg Lifetime Value</h3>
                    <div class="metric-value">₱<?= number_format($avgLifetime ?? 0, 2) ?></div>
                    <div class="metric-change">
                        Per customer
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="chart-container">
                <h2><i class='bx bxs-line-chart'></i> Revenue Trend</h2>
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Orders by Status Chart -->
            <div class="chart-container">
                <h2><i class='bx bxs-pie-chart-alt-2'></i> Orders by Status</h2>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Category Performance Chart -->
            <?php if (!empty($categoryData)): ?>
            <div class="chart-container">
                <h2><i class='bx bxs-category'></i> Category Performance</h2>
                <div class="chart-wrapper">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Products Table -->
            <div class="data-table">
                <h2><i class='bx bxs-trophy'></i> Top Performing Products</h2>
                <table id="topProductsTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topProducts)): ?>
                            <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td><?= number_format($product['units_sold']) ?></td>
                                    <td>₱<?= number_format($product['revenue'], 2) ?></td>
                                    <td><?= number_format($product['stock']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center;">No data available for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Low Performing Products Table -->
            <div class="data-table">
                <h2><i class='bx bxs-error'></i> Low Performing Products</h2>
                <table id="lowProductsTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lowProducts)): ?>
                            <?php foreach ($lowProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td><?= number_format($product['units_sold']) ?></td>
                                    <td>₱<?= number_format($product['revenue'], 2) ?></td>
                                    <td><?= number_format($product['stock']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center;">No low-performing products.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Customers Table -->
            <div class="data-table">
                <h2><i class='bx bxs-user-circle'></i> Top Customers by Lifetime Value</h2>
                <table id="topCustomersTable">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Total Orders</th>
                            <th>Lifetime Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topCustomers)): ?>
                            <?php foreach ($topCustomers as $customer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($customer['user_id']) ?></td>
                                    <td><?= number_format($customer['order_count']) ?></td>
                                    <td>₱<?= number_format($customer['lifetime_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">No customer data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <script>
         // ===== Revenue Chart =====
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?= json_encode(array_reverse($revenueData)) ?>;

    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueData.map(item => item.date),
            datasets: [{
                label: 'Daily Revenue (₱)',
                data: revenueData.map(item => parseFloat(item.daily_revenue)),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // ===== Status Chart =====
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?= json_encode($ordersByStatus) ?>;

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: [
                    '#10b981', // green
                    '#f59e0b', // amber
                    '#ef4444', // red
                    '#3b82f6', // blue
                    '#8b5cf6'  // purple
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // ===== Category Chart =====
    <?php if (!empty($categoryData)): ?>
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryData = <?= json_encode($categoryData) ?>;

    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: categoryData.map(item => item.category),
            datasets: [{
                label: 'Revenue (₱)',
                data: categoryData.map(item => parseFloat(item.revenue)),
                backgroundColor: '#667eea'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

        // Export to CSV function
        function exportToCSV() {
            let csv = 'Sales & Analytics Report\n';
            csv += 'Period: <?= $dateFrom ?> to <?= $dateTo ?>\n\n';
            
            // Summary metrics
            csv += 'SUMMARY METRICS\n';
            csv += 'Total Revenue,₱<?= number_format($revenueSummary['total'], 2) ?>\n';
            csv += 'Total Orders,<?= $revenueSummary['orders'] ?>\n';
            csv += 'Average Order Value,₱<?= number_format($revenueSummary['average'], 2) ?>\n';
            csv += 'Conversion Rate,<?= number_format($conversionRate, 1) ?>%\n';
            csv += 'Total Customers,<?= $totalCustomers ?>\n';
            csv += 'New Customers,<?= $newCustomers ?>\n\n';
            
            // Top Products
            csv += 'TOP PERFORMING PRODUCTS\n';
            csv += 'Product Name,Category,Units Sold,Revenue,Stock\n';
            <?php foreach ($topProducts as $product): ?>
            csv += '<?= addslashes($product['name']) ?>,<?= addslashes($product['category']) ?>,<?= $product['units_sold'] ?>,₱<?= number_format($product['revenue'], 2) ?>,<?= $product['stock'] ?>\n';
            <?php endforeach; ?>
            
            csv += '\nLOW PERFORMING PRODUCTS\n';
            csv += 'Product Name,Category,Units Sold,Revenue,Stock\n';
            <?php foreach ($lowProducts as $product): ?>
            csv += '<?= addslashes($product['name']) ?>,<?= addslashes($product['category']) ?>,<?= $product['units_sold'] ?>,₱<?= number_format($product['revenue'], 2) ?>,<?= $product['stock'] ?>\n';
            <?php endforeach; ?>
            
            csv += '\nTOP CUSTOMERS\n';
            csv += 'User ID,Total Orders,Lifetime Value\n';
            <?php foreach ($topCustomers as $customer): ?>
            csv += '<?= $customer['user_id'] ?>,<?= $customer['order_count'] ?>,₱<?= number_format($customer['lifetime_value'], 2) ?>\n';
            <?php endforeach; ?>
            
            csv += '\nORDERS BY STATUS\n';
            csv += 'Status,Count,Total Amount\n';
            <?php foreach ($ordersByStatus as $status): ?>
            csv += '<?= $status['status'] ?>,<?= $status['count'] ?>,₱<?= number_format($status['total_amount'], 2) ?>\n';
            <?php endforeach; ?>
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'sales_analytics_report_<?= date('Y-m-d') ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
                   