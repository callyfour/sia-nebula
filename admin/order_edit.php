<?php
session_start();
require "../php/db.php";

// ✅ Only admin can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// ✅ Check if order ID is given
if (!isset($_GET['id'])) {
    header("Location: admin.php?msg=No+order+ID");
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: admin.php?msg=Order+not+found");
    exit;
}

$msg = null;

// ✅ Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id']);
    $total = trim($_POST['total']);
    $status = trim($_POST['status']);

    if ($user_id === '' || $total === '' || $status === '') {
        $msg = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET user_id=?, total=?, status=? WHERE id=?");
        $stmt->execute([$user_id, $total, $status, $id]);

        header("Location: admin.php?msg=Order+updated");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order</title>
<link rel="stylesheet" href="../style/admin.css">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo"><i class='bx bxs-edit'></i> <span>Edit Order</span></div>
        <nav>
            <a href="admin.php"><i class='bx bxs-dashboard'></i> Back to Dashboard</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Edit Order #<?= htmlspecialchars($order['id']) ?></h1>
        <?php if ($msg): ?>
            <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>

        <form method="POST" class="order-form">
            <label>User ID</label>
            <input type="number" name="user_id" value="<?= htmlspecialchars($order['user_id']) ?>" required>

            <label>Total (₱)</label>
            <input type="number" step="0.01" name="total" value="<?= $order['total'] ?>" required>

            <label>Status</label>
            <select name="status" required>
                <?php
                $statuses = ["Pending", "Processing", "Shipped", "Completed", "Cancelled"];
                foreach ($statuses as $s) {
                    $selected = ($order['status'] === $s) ? 'selected' : '';
                    echo "<option value='$s' $selected>$s</option>";
                }
                ?>
            </select>

            <button type="submit" class="btn">Update Order</button>
        </form>
    </main>
</div>

<style>
.order-form {
  max-width: 500px;
  display: flex;
  flex-direction: column;
}
.order-form label {
  margin-top: 10px;
  font-weight: 600;
}
.order-form input, .order-form select {
  padding: 8px;
  margin-top: 4px;
  border: 1px solid #ddd;
  border-radius: 6px;
}
.order-form button {
  margin-top: 15px;
  padding: 10px;
  background: #007bff;
  color: white;
  font-weight: bold;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}
</style>
</body>
</html>
