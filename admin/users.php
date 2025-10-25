<?php
session_start();
require "../php/db.php";

// Check if user is logged in & admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// Handle user actions (delete, update role, toggle status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        // Prevent admin from deleting themselves
        if ($userId !== $_SESSION['user']['id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['success_message'] = "User deleted successfully!";
        } else {
            $_SESSION['error_message'] = "You cannot delete your own account!";
        }
        header("Location: users.php");
        exit;
    }
    
    if (isset($_POST['update_role'])) {
        $userId = (int)$_POST['user_id'];
        $newRole = $_POST['role'];
        
        // Prevent admin from changing their own role
        if ($userId !== $_SESSION['user']['id']) {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $userId]);
            $_SESSION['success_message'] = "User role updated successfully!";
        } else {
            $_SESSION['error_message'] = "You cannot change your own role!";
        }
        header("Location: users.php");
        exit;
    }
    
    if (isset($_POST['toggle_status'])) {
        $userId = (int)$_POST['user_id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = ($currentStatus === 'active') ? 'banned' : 'active';
        
        // Prevent admin from banning themselves
        if ($userId !== $_SESSION['user']['id']) {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            $_SESSION['success_message'] = "User status updated successfully!";
        } else {
            $_SESSION['error_message'] = "You cannot change your own status!";
        }
        header("Location: users.php");
        exit;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Search and filter
$searchQuery = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($searchQuery !== '') {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($roleFilter !== '') {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Count total users
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalUsers / $perPage));

// Fetch users
$sql = "SELECT id, name, email, role, status, created_at FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$bindIndex = 1;
foreach ($params as $p) {
    $stmt->bindValue($bindIndex++, $p);
}
$stmt->bindValue($bindIndex++, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count users by role
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$customerCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <style>
        .filters {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filters input, .filters select {
            background: #0f0f0f;
            border: 1px solid #333;
            color: #fff;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: #ff4d4d;
        }
        
        .filter-btn {
            background: #ff4d4d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .filter-btn:hover {
            background: #e60000;
        }
        
        .reset-btn {
            background: #333;
            color: #ccc;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .reset-btn:hover {
            background: #444;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: #ff4d4d;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #fff;
        }
        
        .stat-card .label {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: #4CAF50;
            color: white;
        }
        
        .btn-edit:hover {
            background: #45a049;
        }
        
        .btn-ban {
            background: #ff9800;
            color: white;
        }
        
        .btn-ban:hover {
            background: #e68900;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
        
        .status-active {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .status-banned {
            background: #f44336;
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .role-admin {
            background: #ff4d4d;
            color: white;
        }
        
        .role-customer {
            background: #2196F3;
            color: white;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #4CAF50;
            color: white;
        }
        
        .alert-error {
            background: #f44336;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-top: 0;
            color: #fff;
        }
        
        .modal-content label {
            display: block;
            margin: 15px 0 5px;
            color: #ccc;
        }
        
        .modal-content select {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid #333;
            color: #fff;
            padding: 10px;
            border-radius: 5px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        
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
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #ff4d4d;
            color: white;
        }
        
        .pagination a.active {
            background: #ff4d4d;
            color: white;
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
                <a href="admin.php" ><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="analytics.php"><i class='bx bxs-bar-chart-alt-2'></i> Analytics</a>
                <a href="orders.php"><i class='bx bxs-receipt'></i> Orders</a>
                <a href="products.php"><i class='bx bxs-box'></i> Products</a>
                <a href="users.php" class="active" aria-current="page"><i class='bx bxs-user'></i> Users</a>
                <a href="audit_view.php" <?= basename($_SERVER['PHP_SELF']) === 'audit_view.php' ? 'active' : '' ?>">
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
                <h1><i class='bx bxs-user-account'></i> User Management</h1>
            </header>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <i class='bx bxs-group'></i>
                    <div class="number"><?= $totalUsers ?></div>
                    <div class="label">Total Users</div>
                </div>
                <div class="stat-card">
                    <i class='bx bxs-user-badge'></i>
                    <div class="number"><?= $adminCount ?></div>
                    <div class="label">Administrators</div>
                </div>
                <div class="stat-card">
                    <i class='bx bxs-user'></i>
                    <div class="number"><?= $customerCount ?></div>
                    <div class="label">Customers</div>
                </div>
            </div>

            <!-- Filters -->
            <form class="filters" method="GET">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($searchQuery) ?>">
                
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
                </select>
                
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
                </select>
                
                <button type="submit" class="filter-btn">
                    <i class='bx bx-search'></i> Filter
                </button>
                
                <a href="users.php" class="reset-btn">
                    <i class='bx bx-reset'></i> Reset
                </a>
            </form>

            <!-- Users Table -->
            <section class="section">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id']) ?></td>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="role-badge role-<?= $user['role'] ?>">
                                                <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-<?= $user['status'] ?? 'active' ?>">
                                                <?= ucfirst(htmlspecialchars($user['status'] ?? 'active')) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-edit" onclick="openEditModal(<?= $user['id'] ?>, '<?= $user['role'] ?>')">
                                                    <i class='bx bx-edit'></i> Role
                                                </button>
                                                
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $user['status'] ?? 'active' ?>">
                                                    <button type="submit" name="toggle_status" class="action-btn btn-ban"
                                                        <?= $user['id'] === $_SESSION['user']['id'] ? 'disabled' : '' ?>>
                                                        <i class='bx <?= ($user['status'] ?? 'active') === 'active' ? 'bx-block' : 'bx-check' ?>'></i>
                                                        <?= ($user['status'] ?? 'active') === 'active' ? 'Ban' : 'Unban' ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="delete_user" class="action-btn btn-delete"
                                                        <?= $user['id'] === $_SESSION['user']['id'] ? 'disabled' : '' ?>>
                                                        <i class='bx bx-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px; color: #aaa;">
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($statusFilter) ?>">
                                &laquo; Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($searchQuery) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($statusFilter) ?>" 
                               class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($statusFilter) ?>">
                                Next &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Edit Role Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3><i class='bx bx-edit'></i> Change User Role</h3>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <label for="role">Select New Role:</label>
                <select name="role" id="role" required>
                    <option value="customer">Customer</option>
                    <option value="admin">Administrator</option>
                </select>
                
                <div class="modal-buttons">
                    <button type="submit" name="update_role" class="filter-btn">
                        <i class='bx bx-check'></i> Update
                    </button>
                    <button type="button" class="reset-btn" onclick="closeEditModal()">
                        <i class='bx bx-x'></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, currentRole) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('role').value = currentRole;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>