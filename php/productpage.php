<?php
session_start();
require "db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;
$error = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) $error = "Product not found.";
} else {
    $error = "Invalid product ID.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $product ? htmlspecialchars($product['name']) : 'Product' ?></title>
    <link rel="stylesheet" href="../style/productpage.css">
    <link rel="stylesheet" href="../style/navbar.css" />
    <link
      href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"
      rel="stylesheet"
    />
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

<?php if ($error): ?>
    <p><?= htmlspecialchars($error) ?></p>
<?php else: ?>
    <div class="product-page">
        <a href="javascript:history.back()" class="back-button">← Back to Shop</a>
        
        <div class="product-page-image-container">
            <img src="<?= htmlspecialchars($product['image'] ?: 'https://via.placeholder.com/400x300?text=Car+Part') ?>" 
                 alt="<?= htmlspecialchars($product['name']) ?>" 
                 class="product-page-image">
        </div>

        <div class="product-page-info">
            <h2 class="product-page-title"><?= htmlspecialchars($product['name']) ?></h2>
            <p class="product-page-description"><?= htmlspecialchars($product['description']) ?></p>
            <p class="product-page-price">₱ <?= number_format($product['price'], 2) ?></p>

            <form action="cart.php" method="POST" class="product-page-actions">
                <input type="hidden" name="productId" value="<?= $product['id'] ?>">
                <label>
                    Quantity:
                    <input type="number" name="quantity" value="1" min="1">
                </label>
                <button type="submit" name="action" value="add" class="add-to-cart-btn">Add to Cart</button>
                <button type="submit" name="action" value="buyNow" class="buy-now-btn">Buy Now</button>
            </form>
        </div>
    </div>

    <script>
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
<?php endif; ?>
</body>
</html>