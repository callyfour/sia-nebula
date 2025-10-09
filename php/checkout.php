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

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT c.product_id, c.quantity, p.name, p.price
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1. Fetch cart & calculate total
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}

// 2. Handle form submission
if (isset($_POST['paynow'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name || !$address) {
        $error = "Please fill in all fields.";
    } elseif (empty($cartItems)) {
        $error = "Your cart is empty.";
    } else {
        // 3. Create Stripe session
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

        // 4. Insert order AFTER session is created
        $stmt = $pdo->prepare("INSERT INTO orders 
            (user_id, stripe_session_id, total_amount, shipping_name, shipping_address, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $userId,
            $checkout_session->id,
            $totalPrice,
            $name,
            $address,
            date('Y-m-d H:i:s')
        ]);
        $orderId = $pdo->lastInsertId();

        // 5. Insert order items
        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items 
                (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        }

        // 6. Redirect to Stripe checkout
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
 <!-- PayMongo JS SDK -->
<style>
    #card-element { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
    .payment-form { max-width: 400px; margin: 20px 0; }
    .payment-form input[type="submit"] { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
</style>
</head>
<body>
<!-- Navbar (unchanged) -->
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
            <table style="width:100%; margin-bottom:20px; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #444;">
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr style="border-bottom:1px solid #333;">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td>₱ <?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱ <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Total: ₱ <?= number_format($totalPrice, 2) ?></h3>

            <!-- Shipping form only -->
            <form method="POST">
                <label for="name">Name:</label><br>
                <input type="text" name="name" id="name" required style="width:100%; padding:8px;"><br><br>

                <label for="address">Shipping Address:</label><br>
                <textarea name="address" id="address" rows="4" required style="width:100%; padding:8px;"></textarea><br><br>

                <button type="submit" name="paynow" class="checkout-btn">Proceed to Payment</button>
            </form>
        <?php endif; ?>
    </div>
</div>







</body>
</html>