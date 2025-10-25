<?php
session_start();
require "../php/db.php";
require_once __DIR__ . "/audit_trail.php";

// --- SECURITY CHECK ---
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// --- FETCH FILTERS ---
$actionFilter = $_GET['action'] ?? null;
$userFilter   = $_GET['user_id'] ?? null;
$page         = max(1, intval($_GET['page'] ?? 1));
$limit        = 20;
$offset       = ($page - 1) * $limit;

// --- FETCH DATA ---
$auditLogs = fetchAuditTrail($pdo, $limit, $offset, $actionFilter, $userFilter);
$stats     = getAuditStats($pdo);

// --- PAGINATION ---
$totalLogs  = $stats['total_logs'] ?? 0;
$totalPages = ceil($totalLogs / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail | Admin Panel</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        
            /* ===========================
            🔴 Audit Trail Page — Dark Gray + Red
            =========================== */

            body {
                background: #0f0f0f;
                color: #fff;
                font-family: "Poppins", sans-serif;
            }

            /* --- Filter Bar --- */
            .filter {
                background: #1a1a1a;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }

            .filter select,
            .filter input {
                background: #0f0f0f;
                border: 1px solid #333;
                color: #fff;
                padding: 8px 12px;
                border-radius: 5px;
                font-size: 0.9rem;
            }

            .filter select:focus,
            .filter input:focus {
                outline: none;
                border-color: #ff4d4d;
            }

            .filter button {
                background: #ff4d4d;
                color: #fff;
                border: none;
                padding: 8px 14px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
                transition: background 0.3s ease;
            }

            .filter button:hover {
                background: #e60000;
            }

            /* --- Stats Cards --- */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 15px;
                margin-bottom: 25px;
            }

            .stat-card {
                background: #1a1a1a;
                padding: 20px;
                border-radius: 10px;
                border: 1px solid #333;
                text-align: center;
            }

            .stat-card h3 {
                font-size: 0.9rem;
                color: #ff4d4d;
                margin-bottom: 8px;
                text-transform: uppercase;
            }

            .stat-card p {
                font-size: 1.6rem;
                font-weight: bold;
                color: #fff;
            }

            .stat-card ul {
                margin-top: 8px;
                padding-left: 0;
                list-style: none;
                font-size: 0.9rem;
                color: #ccc;
            }

            /* --- Table --- */
            .table-container {
                background: #1a1a1a;
                border-radius: 10px;
                padding: 15px;
                border: 1px solid #333;
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                color: #fff;
            }

            th, td {
                padding: 10px 12px;
                text-align: left;
                border-bottom: 1px solid #333;
                font-size: 0.9rem;
            }

            th {
                background: #111;
                color: #ff4d4d;
                font-weight: 600;
            }

            tr:hover {
                background: #262626;
            }

            .action-tag {
                background: rgba(255, 77, 77, 0.15);
                color: #ff4d4d;
                font-weight: 500;
                padding: 3px 6px;
                border-radius: 4px;
                font-size: 0.8rem;
                text-transform: uppercase;
            }

            /* --- Pagination --- */
            .pagination {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin-top: 20px;
            }

            .pagination a {
                padding: 8px 12px;
                background: #1a1a1a;
                color: #ccc;
                text-decoration: none;
                border-radius: 5px;
                border: 1px solid #333;
                transition: all 0.3s ease;
            }

            .pagination a:hover {
                background: #ff4d4d;
                color: #fff;
                border-color: #ff4d4d;
            }

            .pagination a.active {
                background: #ff4d4d;
                color: #fff;
                border-color: #ff4d4d;
            }




    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar" role="complementary">
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
                <a href="audit_view.php" class= "active" aria-current="page" <?= basename($_SERVER['PHP_SELF']) === 'audit_view.php' ? 'active' : '' ?>">
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
                <h1><i class='bx bx-history'></i> Audit Trail</h1>
            </header>

            <!-- Stats Section -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Logs</h3>
                    <p><?= number_format($stats['total_logs'] ?? 0) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Logs Today</h3>
                    <p><?= number_format($stats['logs_today'] ?? 0) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Users Today</h3>
                    <p><?= number_format($stats['active_users_today'] ?? 0) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Top Actions</h3>
                    <?php if (!empty($stats['common_actions'])): ?>
                        <ul style="padding-left:15px; margin:5px 0;">
                            <?php foreach ($stats['common_actions'] as $a): ?>
                                <li><?= htmlspecialchars($a['action']) ?> (<?= $a['count'] ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size:14px;">No data yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <form class="filter" method="GET">
                <select name="action">
                    <option value="">All Actions</option>
                    <?php
                    $actions = [
                        AUDIT_LOGIN, AUDIT_LOGOUT, AUDIT_DASHBOARD_ACCESS,
                        AUDIT_PRODUCT_CREATE, AUDIT_PRODUCT_UPDATE, AUDIT_PRODUCT_DELETE,
                        AUDIT_ORDER_UPDATE, AUDIT_ORDER_DELETE,
                        AUDIT_USER_CREATE, AUDIT_USER_UPDATE, AUDIT_USER_DELETE,
                        AUDIT_FAILED_LOGIN, AUDIT_PASSWORD_CHANGE, AUDIT_SETTINGS_UPDATE
                    ];
                    foreach ($actions as $act): ?>
                        <option value="<?= $act ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= $act ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="user_id" placeholder="Filter by User ID" value="<?= htmlspecialchars($userFilter) ?>">
                <button type="submit"><i class='bx bx-filter'></i> Filter</button>
            </form>

            <!-- Audit Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Table</th>
                            <th>Affected ID</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditLogs)): ?>
                            <tr><td colspan="9" style="text-align:center;">No audit logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                                    <td><span class="action-tag"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                    <td><?= htmlspecialchars($log['affected_table'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($log['affected_id'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                    <td title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'], 0, 25)) ?>...
                                    </td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&action=<?= urlencode($actionFilter ?? '') ?>&user_id=<?= urlencode($userFilter ?? '') ?>"
                           class="<?= $i == $page ? 'active' : '' ?>">
                           <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
