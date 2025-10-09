<?php
session_start();
require "db.php";

$userId = $_SESSION['user']['id'] ?? null;

if (!$userId) {
    header("Location: login.php");
    exit;
}

$action = $_POST['action'] ?? '';
$productId = intval($_POST['productId'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);
$productIds = $_POST['productIds'] ?? [];

if ($action) {
    switch ($action) {
        case 'add':
        case 'buyNow':
            if ($productId > 0) {
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                    $stmt->execute([$quantity, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $productId, $quantity]);
                }
            }

            if ($action === 'buyNow') {
                header("Location: checkout.php");
                exit;
            }
            break;

        case 'inc':
            if ($productId > 0) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            }
            break;

        case 'dec':
            if ($productId > 0) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = GREATEST(quantity - 1, 1) WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            }
            break;

        case 'delete':
            if ($productId > 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            }
            break;

        case 'deleteSelected':
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders)");
                $stmt->execute(array_merge([$userId], $productIds));
            }
            break;
    }

    header("Location: cart.php");
    exit;
}

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT c.product_id, c.quantity, p.name, p.price, p.image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}
?>

<!-- HTML rendering cart here (reuse your existing HTML) -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="../style/navbar.css" />
    <link rel="stylesheet" href="../style/cart.css">
    
    <link
      href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"
      rel="stylesheet"
    />
    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }
        function goHome() {
        window.location.href = "index.php";
      }
      function goProfile() {
        window.location.href = "profile.php";
      }
      function toggleDropdown() {
        const menu = document.getElementById("dropdownMenu");
        menu.style.display = menu.style.display === "block" ? "none" : "block";
      }
      function handleSearch(e) {
        e.preventDefault();
        const term = document.getElementById("searchInput")?.value.trim();
        if (term !== "") {
          window.location.href = "shop.php?search=" + encodeURIComponent(term);
        }
      }

      document.addEventListener("click", (e) => {
        const menu = document.getElementById("dropdownMenu");
        const userMenu = document.getElementById("userMenu");
        if (menu && menu.style.display === "block" && !userMenu.contains(e.target)) {
          menu.style.display = "none";
        }
      });
    </script>
</head>
<body>
    <!-- ✅ Navbar -->
    <div class="navbar">
      <!-- Logo -->
      <div class="nav-logo" onclick="goHome()">
        <img src="../assets/logo.png" alt="Logo" />
      </div>

      <!-- Middle Menu -->
      <ul class="nav-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="shop.php">Shop Parts</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="gallery.php">Gallery</a></li>
        <li><a href="cart.php">Cart</a></li>
      </ul>

      <!-- User Menu -->
      <div class="user-menu" id="userMenu">
        <?php if (isset($_SESSION["user"])): ?>
          <!-- ✅ Logged in -->
          <button class="svg-btn login-btn" type="button" onclick="toggleDropdown()">
            <svg width="50" height="50" viewBox="0 0 50 50">
              <polygon points="10,0 50,0 50,40 40,50 0,50 0,10" fill="#1f1f1f"/>
            </svg>
            <span class="icon-wrapper"><i class="bx bxs-user"></i></span>
            <span class="user-name">
              <?= htmlspecialchars($_SESSION["user"]["name"]); ?>
            </span>
          </button>

          <div class="dropdown" id="dropdownMenu" style="display: none">
            <button class="dropdown-item" onclick="goProfile()">Profile</button>
            <form action="logout.php" method="POST" style="margin:0;">
              <button type="submit" class="dropdown-item">Logout</button>
            </form>
          </div>

        <?php else: ?>
          <!-- ❌ Guest -->
          <a href="login.php" class="svg-btn login-btn">
            <svg width="50" height="50" viewBox="0 0 50 50">
              <polygon points="10,0 50,0 50,40 40,50 0,50 0,10" fill="#1f1f1f"/>
            </svg>
            <span class="icon-wrapper"><i class="bx bxs-user"></i></span>
            <span class="user-name">Login</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="cart-wrapper">
<div class="shopping-cart">
    <!-- Step Tracker -->
   <div class="step-tracker">
  <div class="step active"><span>Shopping Cart</span></div>
  <div class="step"><span>Checking Details</span></div>
  <div class="step"><span>Order Complete</span></div>
</div>


    <?php if (empty($cartItems)): ?>
        <p class="empty-cart">Your cart is empty.</p>
    <?php else: ?>
        <!-- Select All + Delete -->
<form method="POST" id="cartForm">
    <div class="cart-header">
        <label>
            <input type="checkbox" onclick="toggleSelectAll(this)"> Select All
        </label>
        <button type="submit" name="action" value="deleteSelected" class="delete-btn">Delete Selected</button>
    </div>

    <?php foreach ($cartItems as $item): ?>
        <div class="cart-item">
            <input type="checkbox" name="productIds[]" value="<?= $item['product_id'] ?>" class="item-checkbox">
            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image">
            <div class="cart-item-info">
                <h3><?= htmlspecialchars($item['name']) ?></h3>
                <p>Unit Price: ₱ <?= number_format($item['price'], 2) ?></p>
                <p>Subtotal (<?= $item['quantity'] ?> pcs): <strong>₱ <?= number_format($item['price'] * $item['quantity'], 2) ?></strong></p>

                <div class="cart-item-controls">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="productId" value="<?= $item['product_id'] ?>">
                        <button type="submit" name="action" value="dec">-</button>
                        <span><?= $item['quantity'] ?></span>
                        <button type="submit" name="action" value="inc">+</button>
                    </form>
                </div>
            </div>

            <form method="POST" class="inline-form">
                <input type="hidden" name="productId" value="<?= $item['product_id'] ?>">
                <button class="remove-icon" type="submit" name="action" value="delete">🗑</button>
            </form>
        </div>
    <?php endforeach; ?>



        <div class="cart-total">
            <h2>Total: ₱ <?= number_format($totalPrice, 2) ?></h2>
        </div>
    <?php endif; ?>

    <div class="cart-footer">
        <div class="cart-footer-left">
            <button onclick="window.location.href='orders.php'">Track my order</button>
            <button onclick="window.location.href='purchases.php'">My Purchases</button>
            <button onclick="window.location.href='shop.php'">Shop more</button>
        </div>
        <div class="cart-footer-right">
            <button class="checkout-btn" onclick="window.location.href='checkout.php'" <?= empty($cartItems) ? 'disabled' : '' ?>>
                Proceed to Checkout
            </button>
        </div>
    </div>
</div>
</div>
</body>
</html>
