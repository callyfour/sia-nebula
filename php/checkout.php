<?php
session_start();
require "db.php";
require "../vendor/autoload.php"; // Stripe PHP

\Stripe\Stripe::setApiKey("sk_test_51SBbPeFY7PfuKJeXlIKyQFBXSNhTgG9GQAJmgS4IMbzfK6dDtEwH8CjQhXHDKI9EPBhmD0fUBSvO5Jtpc7QXy9GS00Rm99vv0j"); // Stripe secret key

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// ✅ Fetch user profile to autofill checkout form
$stmt = $pdo->prepare("SELECT name, billingAddress, shippingAddress FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch(PDO::FETCH_ASSOC);

$name = $userProfile['name'] ?? '';
$billingAddress = $userProfile['billingAddress'] ?? '';
$shippingAddress = $userProfile['shippingAddress'] ?? '';

// ✅ Fetch cart items
$stmt = $pdo->prepare("
    SELECT c.product_id, c.quantity, p.name, p.price
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Calculate total
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}

// ✅ Handle form submission
if (isset($_POST['paynow'])) {
    $name = trim($_POST['name'] ?? '');
    $shippingAddress = trim($_POST['shippingAddress'] ?? '');
    $billingAddress = trim($_POST['billingAddress'] ?? '');

    if (!$name || !$shippingAddress || !$billingAddress) {
        $error = "Please fill in all fields.";
    } elseif (empty($cartItems)) {
        $error = "Your cart is empty.";
    } else {
        // ✅ Save updated addresses to the user's profile
        $stmt = $pdo->prepare("UPDATE users SET name = ?, shippingAddress = ?, billingAddress = ? WHERE id = ?");
        $stmt->execute([$name, $shippingAddress, $billingAddress, $userId]);

        // ✅ Create Stripe checkout session
        $line_items = [];
        foreach ($cartItems as $item) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'php',
                    'product_data' => ['name' => $item['name']],
                    'unit_amount' => intval($item['price'] * 100),
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => "http://localhost/nebula/php/checkout_success.php?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "http://localhost/nebula/php/checkout.php",
        ]);

        // ✅ Insert order into database
        $stmt = $pdo->prepare("INSERT INTO orders 
            (user_id, stripe_session_id, total_amount, shipping_name, shipping_address, billing_address, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $userId,
            $checkout_session->id,
            $totalPrice,
            $name,
            $shippingAddress,
            $billingAddress,
            date('Y-m-d H:i:s')
        ]);
        $orderId = $pdo->lastInsertId();

        // ✅ Insert order items
        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items 
                (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        }

        // ✅ Redirect to Stripe checkout
        header("Location: " . $checkout_session->url);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout</title>
<link rel="stylesheet" href="../style/navbar.css">
<link rel="stylesheet" href="../style/cart.css">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
<style>
    #card-element { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
    .payment-form { max-width: 400px; margin: 20px 0; }
    .payment-form input[type="submit"] { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
    textarea { resize: vertical; }
</style>
</head>
<body>

<!-- ✅ Navbar -->
<div class="navbar">
    <div class="nav-logo" onclick="window.location.href='index.php'">
        <img src="../assets/logo.png" alt="Logo">
    </div>
    <ul class="nav-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="shop.php">Shop Parts</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="gallery.php">Gallery</a></li>
        <li><a href="cart.php">Cart</a></li>
    </ul>
    <div class="user-menu" id="userMenu">
        <?php if (isset($_SESSION["user"])): ?>
            <button class="svg-btn login-btn" onclick="document.getElementById('dropdownMenu').style.display='block'">
                <svg width="50" height="50"><polygon points="10,0 50,0 50,40 40,50 0,50 0,10" fill="#1f1f1f"/></svg>
                <span class="icon-wrapper"><i class="bx bxs-user"></i></span>
                <span class="user-name"><?= htmlspecialchars($_SESSION["user"]["name"]); ?></span>
            </button>
            <div class="dropdown" id="dropdownMenu" style="display:none;">
                <button class="dropdown-item" onclick="window.location.href='profile.php'">Profile</button>
                <form action="logout.php" method="POST" style="margin:0;">
                    <button type="submit" class="dropdown-item">Logout</button>
                </form>
            </div>
        <?php else: ?>
            <a href="login.php" class="svg-btn login-btn">
                <svg width="50" height="50"><polygon points="10,0 50,0 50,40 40,50 0,50 0,10" fill="#1f1f1f"/></svg>
                <span class="icon-wrapper"><i class="bx bxs-user"></i></span>
                <span class="user-name">Login</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="cart-wrapper">
    <div class="shopping-cart">
        <h2>Checkout</h2>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <p>Your cart is empty. <a href="shop.php">Shop now</a>?</p>
        <?php else: ?>
            <table style="width:100%; margin-bottom:20px; border-collapse:collapse; text-align:center;">
                <thead>
                    <tr style="border-bottom:1px solid #444;">
                        <th style="padding:12px; color:#fff; background:#111;">Product</th>
                        <th style="padding:12px; color:#fff; background:#111;">Unit Price</th>
                        <th style="padding:12px; color:#fff; background:#111;">Quantity</th>
                        <th style="padding:12px; color:#fff; background:#111;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr style="border-bottom:1px solid #333; background:#1a1a1a; color:#fff;">
                            <td style="padding:10px;"><?= htmlspecialchars($item['name']) ?></td>
                            <td style="padding:10px;">₱ <?= number_format($item['price'], 2) ?></td>
                            <td style="padding:10px;"><?= $item['quantity'] ?></td>
                            <td style="padding:10px;">₱ <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>


            <div style="
                display: flex; 
                justify-content: flex-end; 
                align-items: center; 
                background: linear-gradient(135deg, #1a1a1a, #111); 
                color: #fff; 
                padding: 18px 24px; 
                border-radius: 12px; 
                border: 1px solid #333; 
                box-shadow: 0 0 10px rgba(255, 77, 77, 0.2); 
                margin-top: 20px; 
                font-family: 'Poppins', sans-serif;
            ">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #ccc;">Total:&nbsp;</h3>
                <span style="font-size: 1.5rem; font-weight: 700; color: #ff4d4d;">
                    ₱ <?= number_format($totalPrice, 2) ?>
                </span>
            </div>

            <!-- ✅ Form with autofilled fields -->
            <form method="POST">
                <label for="name"><i class="bx bx-user"></i> Full Name:</label><br>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    required 
                    value="<?= htmlspecialchars($name) ?>" 
                    style="width:100%; padding:10px 14px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; font-size:1rem;"><br><br>

                <label for="shippingAddress"><i class="bx bx-package"></i> Shipping Address:</label><br>
                <textarea 
                    name="shippingAddress" 
                    id="shippingAddress" 
                    rows="3" 
                    required 
                    style="width:100%; padding:10px 14px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; font-size:1rem;"><?= htmlspecialchars($shippingAddress) ?></textarea><br><br>

                <label for="billingAddress"><i class="bx bx-credit-card"></i> Billing Address:</label><br>
                <textarea 
                    name="billingAddress" 
                    id="billingAddress" 
                    rows="3" 
                    required 
                    style="width:100%; padding:10px 14px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; font-size:1rem;"><?= htmlspecialchars($billingAddress) ?></textarea><br><br>

                <button 
                    type="submit" 
                    name="paynow" 
                    class="checkout-btn" 
                    style="background:linear-gradient(135deg,#ff4d4d,#cc0000);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;width:100%;font-size:1rem;">
                    Proceed to Payment
                </button>
            </form>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
