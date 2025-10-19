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

// New: Define a function to fetch counts for reusability
function fetchCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return 0;  // Fallback value
    }
}

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
                <a href="admin.php" class="active" aria-current="page"><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="orders.php"><i class='bx bxs-receipt'></i> Orders</a>
                <a href="products.php"><i class='bx bxs-box'></i> Products</a>
                <a href="../php/logout.php" onclick="return confirm('Are you sure you want to logout?');"> <!-- Confirmation added -->
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
    </script>
</body>
</html>