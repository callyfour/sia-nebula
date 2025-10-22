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

// ⭐ NEW: Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    if (!isset($_SESSION['user'])) {
        $error = "You must be logged in to submit a review.";
    } else {
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $userId = $_SESSION['user']['id'];

        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, rating, comment)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $userId, $rating, $comment]);
        } else {
            $error = "Please select a rating and write a comment.";
        }
    }
}

// ⭐ NEW: Fetch reviews and average rating
$reviews = [];
$avgRating = 0;

if ($product) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name AS user_name 
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$product['id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average
    $stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating FROM reviews WHERE product_id = ?");
    $stmt->execute([$product['id']]);
    $avgRating = round($stmt->fetchColumn(), 1);
}

// Fetch related products (same category if available)
$recommendations = [];

if ($product) {
    if (!empty($product['category'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE category = ? AND id != ? 
            ORDER BY RAND() 
            LIMIT 4
        ");
        $stmt->execute([$product['category'], $product['id']]);
    } else {
        // Fallback: Random products if category missing
        $stmt = $pdo->query("
            SELECT * FROM products 
            WHERE id != {$product['id']} 
            ORDER BY RAND() 
            LIMIT 4
        ");
    }
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <a href="shop.php" class="back-button">← Back to Shop</a>
        
        <div class="product-page-image-container">
            <img src="<?= htmlspecialchars($product['image'] ?: 'https://via.placeholder.com/400x300?text=Car+Part') ?>" 
                 alt="<?= htmlspecialchars($product['name']) ?>" 
                 class="product-page-image">
        </div>

        <div class="product-page-info">
        <h2 class="product-page-title">
          <?= htmlspecialchars($product['name']) ?>
        </h2>

        <!-- ⭐ Show average rating beside the title -->
        <?php if ($avgRating > 0): ?>
          <div class="product-page-rating">
            <?php 
              $fullStars = floor($avgRating);
              $halfStar = ($avgRating - $fullStars >= 0.5);
            ?>
            <span class="stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= $fullStars): ?>
                  <i class="bx bxs-star"></i>
                <?php elseif ($halfStar && $i == $fullStars + 1): ?>
                  <i class="bx bxs-star-half"></i>
                <?php else: ?>
                  <i class="bx bx-star"></i>
                <?php endif; ?>
              <?php endfor; ?>
            </span>
            <span class="rating-text"><?= $avgRating ?>/5</span>
            <span class="review-count">(<?= count($reviews) ?> reviews)</span>
          </div>
        <?php else: ?>
          <div class="product-page-rating no-reviews">
            <span>No reviews yet</span>
          </div>
        <?php endif; ?>

        <p class="product-page-description"><?= htmlspecialchars($product['description']) ?></p>

        <p class="product-page-price">₱ <?= number_format($product['price'], 2) ?></p>
        
        <!-- ✅ Stock Display -->
        <?php if ($product['stock'] > 0): ?>
            <p class="product-page-stock">
                <strong>In Stock:</strong> <?= (int)$product['stock'] ?> item<?= $product['stock'] > 1 ? 's' : '' ?>
            </p>
        <?php else: ?>
            <p class="product-page-stock out-of-stock"><strong>Out of Stock</strong></p>
        <?php endif; ?>

        <form action="cart.php" method="POST" class="product-page-actions">
            <input type="hidden" name="productId" value="<?= $product['id'] ?>">
            
            <label>
                Quantity:
                <input 
                    type="number" 
                    name="quantity" 
                    value="1" 
                    min="1"
                    max="<?= (int)$product['stock'] ?>"
                    <?= $product['stock'] <= 0 ? 'disabled' : '' ?>
                    id="quantityInput"
                >
            </label>


            <!-- ✅ Disable buttons if out of stock -->
            <button 
                type="submit" 
                name="action" 
                value="add" 
                class="add-to-cart-btn"
                <?= $product['stock'] <= 0 ? 'disabled' : '' ?>
            >Add to Cart</button>

            <button 
                type="submit" 
                name="action" 
                value="buyNow" 
                class="buy-now-btn"
                <?= $product['stock'] <= 0 ? 'disabled' : '' ?>
            >Buy Now</button>
        </form>
    </div>
      
    <!-- ⭐ NEW: Reviews & Ratings Section -->
        <section class="reviews-section">
          <h3>Customer Reviews</h3>

          <!-- Show Average Rating -->
          <?php if ($avgRating > 0): ?>
            <p class="average-rating">
              ⭐ <?= $avgRating ?> / 5 (<?= count($reviews) ?> reviews)
            </p>
          <?php else: ?>
            <p>No reviews yet. Be the first to review this product!</p>
          <?php endif; ?>

          <!-- Review Form -->
          <?php if (isset($_SESSION['user'])): ?>
            <form method="POST" class="review-form">
              <h4>Write a Review</h4>
              <label>Rating:</label>
              <div class="star-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>">
                  <label for="star<?= $i ?>">★</label>
                <?php endfor; ?>
              </div>

              <textarea name="comment" rows="3" placeholder="Share your thoughts..." required></textarea>
              <button type="submit" name="review_submit" class="submit-review-btn">Submit Review</button>
            </form>
          <?php else: ?>
            <p><a href="login.php">Login</a> to write a review.</p>
          <?php endif; ?>

          <!-- Display Reviews -->
          <div class="review-list">
            <?php foreach ($reviews as $r): ?>
              <div class="review-item">
                <p class="review-user"><strong><?= htmlspecialchars($r['user_name']) ?></strong></p>
                <p class="review-rating">
                  <?= str_repeat("⭐", (int)$r['rating']) ?> (<?= $r['rating'] ?>/5)
                </p>
                <p class="review-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                <small class="review-date"><?= date("F j, Y", strtotime($r['created_at'])) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

    <?php if (!empty($recommendations)): ?>
      <section class="recommended-section">
        <h3>You Might Also Like</h3>
        <div class="recommended-grid">
          <?php foreach ($recommendations as $rec): ?>
            <div class="recommended-card">
              <a href="productpage.php?id=<?= $rec['id'] ?>">
                <img src="<?= htmlspecialchars($rec['image'] ?: 'https://via.placeholder.com/200x150?text=Product') ?>" 
                    alt="<?= htmlspecialchars($rec['name']) ?>">
                <h4><?= htmlspecialchars($rec['name']) ?></h4>
                <p>₱ <?= number_format($rec['price'], 2) ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
          
    

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