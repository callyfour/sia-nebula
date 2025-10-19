<?php
session_start();
require "db.php";

$products = [];

if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM products p 
        INNER JOIN wishlist w ON p.id = w.product_id 
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Wishlist</title>
  <link rel="stylesheet" href="../style/index.css">
  <link rel="stylesheet" href="../style/products.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body style="background:#0a0a0a; color:#fff; font-family:'Poppins', sans-serif; margin:0; padding:0;">

  <!-- ✅ Navbar -->
  <div class="navbar" style="position:fixed;top:0;left:0;width:100%;z-index:1000;">
    <div class="nav-logo" onclick="goHome()" style="cursor:pointer;">
      <img src="../assets/logo.png" alt="Logo" />
    </div>
    <ul class="nav-menu">
      <li><a href="index.php">Home</a></li>
      <li><a href="shop.php">Shop Parts</a></li>
      <li><a href="contact.php">Contact Us</a></li>
      <li><a href="about.php">About Us</a></li>
      <li><a href="gallery.php">Gallery</a></li>
      <li><a href="cart.php">Cart</a></li>
    </ul>
  </div>

  <!-- ❤️ Wishlist Header -->
  <section style="text-align:center; padding-top:120px; margin-bottom:30px;">
    <h1 style="font-size:2.2rem;font-weight:700;color:#ff4d4d;">My Wishlist</h1>
    <p style="color:#aaa;">Items you've added to your favorites</p>
  </section>

  <!-- ✅ Wishlist Grid -->
  <div style="display:flex;flex-wrap:wrap;gap:25px;justify-content:center;padding:0 40px 80px;">
    <?php if (!empty($products)): ?>
      <?php foreach ($products as $product): ?>
        <div style="
          background:#111;
          border:1px solid #222;
          border-radius:15px;
          text-align:center;
          width:220px;
          padding:15px;
          box-shadow:0 0 15px rgba(255,77,77,0.05);
          transition:transform 0.3s ease, box-shadow 0.3s ease;
        " 
        onmouseover="this.style.transform='translateY(-5px)';this.style.boxShadow='0 0 25px rgba(255,77,77,0.2)';"
        onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 0 15px rgba(255,77,77,0.05)';">

          <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
               style="width:100%;border-radius:10px;height:180px;object-fit:cover;">
          <h3 style="margin:10px 0 5px;color:#fff;font-size:1rem;"><?= htmlspecialchars($product['name']) ?></h3>
          <p style="color:#ff4d4d;font-weight:600;">₱ <?= number_format($product['price'], 2) ?></p>
          
          <div style="display:flex;justify-content:center;gap:10px;margin-top:10px;">
            <a href="productpage.php?id=<?= $product['id'] ?>"
              style="background:#ff4d4d;color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:0.9rem;transition:all 0.3s ease;"
              onmouseover="this.style.background='#ff6666';"
              onmouseout="this.style.background='#ff4d4d';">
              View
            </a>

            <a href="shop.php?remove_wishlist=<?= $product['id'] ?>"
            style="background:#222;color:#ff4d4d;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:0.9rem;transition:all 0.3s ease;"
            onmouseover="this.style.background='#ff1a1a';this.style.color='#fff';"
            onmouseout="this.style.background='#222';this.style.color='#ff4d4d';">
            Remove
            </a>

          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center;font-size:1.1rem;color:#aaa;">No items in your wishlist yet!</p>
    <?php endif; ?>
  </div>

  <!-- 🔙 Continue Shopping Button -->
  <div style="text-align:center;margin-bottom:60px;">
    <a href="shop.php#products-section"
      style="
        background:#111;
        color:#ccc;
        border:1px solid #333;
        border-radius:10px;
        padding:10px 20px;
        font-size:1rem;
        text-decoration:none;
        transition:all 0.3s ease;
      "
      onmouseover="this.style.borderColor='#ff4d4d';this.style.color='#fff';this.style.background='#1a1a1a';"
      onmouseout="this.style.borderColor='#333';this.style.color='#ccc';this.style.background='#111';">
      ← Continue Shopping
    </a>
  </div>

  <script>
  function goHome() {
    window.location.href = "index.php";
  }
  </script>
</body>
</html>
