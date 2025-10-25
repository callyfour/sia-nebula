<?php
session_start();
require "../php/db.php";
require_once "../admin/audit_trail.php";


// Regenerate session for security
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Check if user is logged in & admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// ✅ Record admin dashboard access only once per session
if (isset($_SESSION['user']) && empty($_SESSION['dashboard_access_logged'])) {
    recordAudit($pdo, $_SESSION['user']['id'], AUDIT_LOGIN, 'Admin accessed dashboard');
    $_SESSION['dashboard_access_logged'] = true; // Mark as logged
}


// New: Define a function to fetch counts for reusability
function fetchCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return 0;  // Fallback value
    }
}
// Fetch out-of-stock products
$outOfStockStmt = $pdo->query("SELECT name FROM products WHERE stock <= 0");
$outOfStockProducts = $outOfStockStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing counts using the new function
$totalOrders = fetchCount($pdo, "SELECT COUNT(*) FROM orders");
$totalProducts = fetchCount($pdo, "SELECT COUNT(*) FROM products");
$totalRevenue = fetchCount($pdo, "SELECT SUM(total_amount) FROM orders WHERE stripe_session_id IS NOT NULL");
$pendingOrders = fetchCount($pdo, "SELECT COUNT(*) FROM orders WHERE status='Pending'");

// New: Fetch additional data
$totalUsers = fetchCount($pdo, "SELECT COUNT(*) FROM users");  // Assuming a 'users' table exists
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();  // Fetch recent orders
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
    <div class="admin-layout" role="main" aria-label="Admin Dashboard Layout">
        <!-- Sidebar -->
        <aside class="sidebar" role="complementary" aria-label="Navigation Menu">
            <div class="logo">
                <i class='bx bxs-cog'></i> Admin Panel
            </div>
            <nav aria-label="Sidebar Navigation">
                <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'active' : '' ?>">
                    <i class='bx bxs-dashboard'></i> Dashboard
                </a>
                <a href="analytics.php" class="<?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>">
                    <i class='bx bxs-bar-chart-alt-2'></i> Analytics
                </a>
                <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
                    <i class='bx bxs-receipt'></i> Orders
                </a>
                <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
                    <i class='bx bxs-box'></i> Products
                </a>
                <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                    <i class='bx bxs-user'></i> Users
                </a>
                <a href="audit_view.php" <?= basename($_SERVER['PHP_SELF']) === 'audit_view.php' ? 'active' : '' ?>">
                    <i class='bx bx-history'></i> Audit Trail
                </a>


                <a href="../php/logout.php" onclick="return confirm('Are you sure you want to logout?');">
                    <i class='bx bxs-log-out'></i> Logout
                </a>
            </nav>

        </aside>

        <!-- Main Content -->
        <main class="content" id="main-content" tabindex="-1">
            <header class="content-header" aria-labelledby="dashboard-title">
                <h1 id="dashboard-title">Dashboard Overview</h1>
            </header>

            <section class="dashboard-cards" aria-label="Dashboard Summary Cards">
                <div class="card">
                    <i class='bx bxs-receipt'></i>
                    <div class="card-info">
                        <span class="label">Total Orders</span>
                        <span class="number"><?= htmlspecialchars($totalOrders) ?></span>
                    </div>
                </div>
                <div class="card">
                    <i class='bx bxs-box'></i>
                    <div class="card-info">
                        <span class="label">Total Products</span>
                        <span class="number"><?= htmlspecialchars($totalProducts) ?></span>
                    </div>
                </div>
                <div class="card">
                    <i class='bx bxs-wallet-alt'></i>
                    <div class="card-info">
                        <span class="label">Total Revenue</span>
                        <span class="number">₱ <?= htmlspecialchars(number_format($totalRevenue ?? 0, 2)) ?></span>
                    </div>
                </div>
                <div class="card">
                    <i class='bx bxs-hourglass'></i>
                    <div class="card-info">
                        <span class="label">Pending Orders</span>
                        <span class="number"><?= htmlspecialchars($pendingOrders) ?></span>
                    </div>
                </div>
                <!-- New: Added card for total users -->
                <div class="card">
                    <i class='bx bxs-user'></i>
                    <div class="card-info">
                        <span class="label">Total Users</span>
                        <span class="number"><?= htmlspecialchars($totalUsers) ?></span>
                    </div>
                </div>
            </section>

            <?php if (!empty($outOfStockProducts)): ?>
                <div id="stockPopup" class="popup-overlay">
                <div class="popup-content">
                    <h2><i class='bx bxs-error-circle'></i> Out of Stock Alert</h2>
                    <p>The following products are currently out of stock:</p>
                    <ul>
                    <?php foreach ($outOfStockProducts as $item): ?>
                        <li><?= htmlspecialchars($item['name']) ?></li>
                    <?php endforeach; ?>
                    </ul>
                    <button onclick="closePopup()">OK</button>
                </div>
                </div>
            <?php endif; ?>


            <!-- New: Section for Recent Orders -->
            <section class="section" aria-label="Recent Orders Section">
                <h2><i class='bx bxs-history'></i> Recent Orders</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>User ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['id']) ?></td>
                                        <td><?= htmlspecialchars($order['user_id']) ?></td>
                                        <td>₱ <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                        <td><span class="status-badge status-<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center;">No recent orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- New: Add a refresh button -->
                <button class="btn" onclick="location.reload();">Refresh Dashboard</button>
            </section>
        </main>
    </div>
    <script>
        // New: JavaScript function for enhanced interactivity
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('main-content').focus();  // Focus on main content
        });
        
        function closePopup() {
        document.getElementById("stockPopup").style.display = "none";
        }
    </script>
</body>
</html>