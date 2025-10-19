<?php
session_start();
require "../php/db.php";

// ✅ Only admin access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// ✅ Check order ID
if (!isset($_GET['id'])) {
    header("Location: admin.php?msg=No+order+ID");
    exit;
}

$id = (int)$_GET['id'];

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: admin.php?msg=Order+not+found");
    exit;
}

// Fetch ordered products
$stmt = $pdo->prepare("SELECT op.id, p.name, p.price, op.quantity FROM order_items op JOIN products p ON op.product_id = p.id WHERE op.order_id = ?");
$stmt->execute([$id]);
$orderProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = null;

// ✅ Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id']);
    $status = trim($_POST['status']);
    $shipping_address = trim($_POST['shipping_address']);
    $product_quantities = $_POST['quantity'] ?? [];

    if ($user_id === '' || $status === '' || $shipping_address === '') {
        $msg = "All fields are required.";
    } else {
        // Update order items quantities & recalc total
        $total = 0;
        foreach ($orderProducts as $p) {
            $pid = $p['id'];
            $qty = max(0, intval($product_quantities[$pid] ?? $p['quantity']));
            $stmt = $pdo->prepare("UPDATE order_items SET quantity=? WHERE id=?");
            $stmt->execute([$qty, $pid]);
            $p['quantity'] = $qty; // update quantity in local array
            $total += $p['price'] * $qty;
        }

        // Update order total in DB
        $stmt = $pdo->prepare("UPDATE orders SET user_id=?, total_amount=?, status=?, shipping_address=? WHERE id=?");
        $stmt->execute([$user_id, $total, $status, $shipping_address, $id]);

        // Redirect to orders.php with a success message
        header("Location: orders.php?msg=Order+updated+successfully");
        exit;


        // Update local variables so form reflects new values
        $order['total'] = $total;
        foreach ($orderProducts as &$p) {
            $p['quantity'] = $product_quantities[$p['id']] ?? $p['quantity'];
        }

        $msg = "Order updated successfully!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order #<?= htmlspecialchars($order['id']) ?></title>
<link rel="stylesheet" href="../style/admin.css">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo"><i class='bx bxs-edit'></i> <span>Edit Order</span></div>
        <nav>
            <a href="admin.php"><i class='bx bxs-dashboard'></i> Back to Dashboard</a>
        </nav>
    </aside>

    <main class="content">
        <h1>Edit Order #<?= htmlspecialchars($order['id']) ?></h1>
        <?php if ($msg): ?>
            <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>

        <form method="POST" class="order-form">
            <label>User ID</label>
            <input type="number" name="user_id" value="<?= htmlspecialchars($order['user_id']) ?>" required>

            <label>Shipping Address</label>
            <textarea name="shipping_address" required><?= htmlspecialchars($order['shipping_address']) ?></textarea>

            <label>Status</label>
            <select name="status" required>
                <?php
                $statuses = ["Pending", "Processing", "Shipped", "Paid", "Cancelled"];
                foreach ($statuses as $s) {
                    $selected = ($order['status'] === $s) ? 'selected' : '';
                    echo "<option value='$s' $selected>$s</option>";
                }
                ?>
            </select>

            <h2>Products in Order</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price (₱)</th>
                        <th>Quantity</th>
                        <th>Subtotal (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderProducts as $p): 
                        $subtotal = $p['price'] * $p['quantity'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= number_format($p['price'],2) ?></td>
                        <td>
                            <input type="number" name="quantity[<?= $p['id'] ?>]" value="<?= $p['quantity'] ?>" min="0" style="width:60px;">
                        </td>
                        <td><?= number_format($subtotal,2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p><strong>Total:</strong> ₱ <?= number_format($order['total'],2) ?></p>

            <button type="submit" class="btn">Update Order</button>
        </form>
    </main>
</div>

<style>
:root {
    --form-max-width: 600px;
    --input-padding: 14px 18px;
    --input-border: 2px solid #333;
    --input-focus: #ff4d4d;
    --label-color: #fff;
    --error-color: #ff5252;
    --success-color: #4caf50;
    --bg-primary: #0f0f0f;
    --bg-secondary: #1a1a1a;
    --bg-card: #222;
    --text-primary: #fff;
    --text-secondary: #ccc;
    --accent-red: #ff4d4d;
    --dark-red: #e60000;
    --border: #333;
    --shadow: rgba(0,0,0,0.5);
    --shadow-hover: rgba(255,23,68,0.3);
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border);
    box-shadow: 4px 0 20px var(--shadow);
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar .logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 700;
    text-align: center;
    color: var(--accent-red);
    margin: 30px 0;
    padding: 20px;
    background: rgba(255,23,68,0.05);
    border-bottom: 1px solid var(--border);
}

.sidebar nav {
    flex: 1;
    padding: 10px 0;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 24px;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar nav a:hover {
    background: rgba(255,23,68,0.1);
    color: var(--accent-red);
    border-left-color: var(--accent-red);
    transform: translateX(4px);
}

/* Main Content */
.content {
    flex: 1;
    padding: 40px;
    background: var(--bg-primary);
    overflow-y: auto;
}

.content h1 {
    color: var(--text-primary);
    margin: 0 0 24px 0;
    font-size: 2.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Alerts */
.alert {
    padding: 16px 20px;
    margin-bottom: 24px;
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px var(--shadow);
}

.alert-error {
    background: rgba(255, 82, 82, 0.1);
    color: var(--error-color);
    border: 1px solid var(--error-color);
}

.alert-success {
    background: rgba(76, 175, 80, 0.1);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

/* --- Form Container --- */
.order-form {
    max-width: 700px;
    background: var(--bg-card);
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 8px 32px var(--shadow);
    border: 1px solid var(--border);
    margin-top: 24px;
}

.order-form h2 {
    color: var(--text-primary);
    margin: 24px 0 12px 0;
    font-size: 1.5rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 6px;
}

/* --- Form Groups --- */
.order-form label {
    display: block;
    margin-top: 16px;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--label-color);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-form input[type="text"],
.order-form input[type="number"],
.order-form textarea,
.order-form select {
    width: 100%;
    padding: var(--input-padding);
    border: var(--input-border);
    border-radius: 8px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 16px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.order-form input:focus,
.order-form textarea:focus,
.order-form select:focus {
    outline: none;
    border-color: var(--input-focus);
    box-shadow: 0 0 0 3px rgba(255,23,68,0.1);
    transform: translateY(-1px);
}

.order-form textarea {
    resize: vertical;
    min-height: 80px;
}

/* --- Products Table --- */
.order-form table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

.order-form table th,
.order-form table td {
    padding: 12px 8px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 14px;
}

.order-form table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-form table tbody tr:hover {
    background: rgba(255,23,68,0.05);
}

.order-form table input[type="number"] {
    width: 60px;
    padding: 6px 8px;
    border: var(--input-border);
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    text-align: center;
}

/* --- Total Display --- */
.order-form p strong {
    color: var(--text-primary);
    font-size: 16px;
}

/* --- Submit Button --- */
.order-form .btn {
    background: linear-gradient(135deg, var(--accent-red) 0%, var(--dark-red) 100%);
    color: var(--text-primary);
    border: none;
    padding: 16px 32px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    width: 100%;
}

.order-form .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px var(--shadow-hover);
}

.order-form .btn:active {
    transform: translateY(0);
}

/* --- Responsive --- */
@media (max-width: 768px) {
    .order-form {
        padding: 24px;
    }
    .order-form table th,
    .order-form table td {
        font-size: 12px;
        padding: 8px 6px;
    }
    .order-form input[type="number"] {
        width: 50px;
        font-size: 14px;
    }
}


/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    .admin-layout {
        flex-direction: column;
    }
    .content {
        padding: 20px;
    }
}

</style>
<script>
document.querySelectorAll('.order-form input[type="number"]').forEach(input => {
    input.addEventListener('input', () => {
        let total = 0;
        document.querySelectorAll('.order-form tbody tr').forEach(row => {
            const price = parseFloat(row.children[1].textContent.replace(/,/g,'')) || 0;
            const qty = parseInt(row.children[2].querySelector('input').value) || 0;
            const subtotal = price * qty;
            row.children[3].textContent = subtotal.toFixed(2);
            total += subtotal;
        });
        document.querySelector('.order-form p strong').textContent = `₱ ${total.toFixed(2)}`;
    });
});

</script>

</body>
</html>
