<?php
session_start();
require "db.php"; // ✅ Your PDO connection

$userWishlist = [];
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $userWishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
}



// Handle adding/removing favorites
if (isset($_GET['toggle_wishlist'])) {
    $productId = (int)$_GET['toggle_wishlist'];
    if (in_array($productId, $_SESSION['wishlist'])) {
        // Remove if already in wishlist
        $_SESSION['wishlist'] = array_diff($_SESSION['wishlist'], [$productId]);
    } else {
        // Add to wishlist
        $_SESSION['wishlist'][] = $productId;
    }

    // Redirect back to avoid query repetition
    header("Location: shop.php?" . http_build_query(array_diff_key($_GET, ['toggle_wishlist' => ''])) . "#products-section");
    exit;
}
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user']['id'];

// Remove product from wishlist if requested
if (isset($_GET['remove_wishlist'])) {
    $productId = (int)$_GET['remove_wishlist'];
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);

    // Redirect to avoid repeated deletion
    header("Location: wishlist.php");
    exit;
}
// --- Capture inputs safely ---
$selectedCategory = $_GET['category'] ?? '';
$selectedBrand    = $_GET['brand'] ?? 'All';
$searchQuery      = $_GET['search'] ?? '';
$sort             = $_GET['sort'] ?? 'name_asc';
$page             = max(1, intval($_GET['page'] ?? 1));
$perPage          = 12; // items per page

// --- Map sort options safely ---
$sortMap = [
    'name_asc'   => 'name ASC',
    'name_desc'  => 'name DESC',
    'price_asc'  => 'price ASC',
    'price_desc' => 'price DESC'
];
$orderBy = $sortMap[$sort] ?? 'name ASC';

// --- Build dynamic WHERE conditions ---
$where = [];
$params = [];

if ($selectedCategory !== '') {
    $where[] = "category = ?";
    $params[] = $selectedCategory;
}

if ($selectedBrand !== 'All' && $selectedBrand !== '') {
    $where[] = "brand = ?";
    $params[] = $selectedBrand;
}

if ($searchQuery !== '') {
    $where[] = "(name LIKE ? OR brand LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// --- Count total products for pagination ---
$countSql = "SELECT COUNT(*) FROM products $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));

// --- Compute offset ---
$offset = ($page - 1) * $perPage;

// --- Fetch products with filters, sorting, and pagination ---
$sql = "SELECT * FROM products $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

// Bind all params correctly
$bindIndex = 1;
foreach ($params as $p) {
    $stmt->bindValue($bindIndex++, $p);
}
$stmt->bindValue($bindIndex++, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch unique brands for brand filter ---
$brandStmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
array_unshift($brands, "All");

// --- Optional: Static categories (adjust if needed) ---
$categories = ['Tires', 'Brakes', 'Engine Parts', 'Suspension', 'Seats', 'Lighting', 'Accessories'];

// ✅ Banner images (same as before)
$banners = [
    "../assets/shopparts/shopparts-1.png",
    "../assets/shopparts/shopparts-2.png",
    "../assets/shopparts/shopparts-3.png",
    "../assets/shopparts/shopparts-4.png",
    "../assets/shopparts/shopparts-5.png",
    "../assets/shopparts/shopparts-6.png",
    "../assets/shopparts/shopparts-7.png",
    "../assets/shopparts/shopparts-8.png",
    "../assets/shopparts/shopparts-9.png",
];


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products</title>
  <link rel="stylesheet" href="../style/products.css">
  <link rel="stylesheet" href="../style/index.css" />
  <link
      href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"
      rel="stylesheet"
    />
</head>
<body>
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
        <?php if (isset($_SESSION['user'])): ?>
          <?php
            // Fetch unread count
            $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND seen = 0");
            $notifStmt->execute([$_SESSION['user']['id']]);
            $unreadCount = $notifStmt->fetchColumn();
          ?>

          <div class="notif-container" style="position:relative;margin-right:15px;">
            <button id="notifBtn" style="background:none;border:none;color:#fff;font-size:1.7rem;cursor:pointer;position:relative;">
              <i class='bx bx-bell'></i>
              <?php if ($unreadCount > 0): ?>
                <span class="notif-count"><?= $unreadCount ?></span>
              <?php endif; ?>
            </button>

            <!-- 🔔 Popup Panel -->
            <div id="notifPopup" class="notif-popup" style="
              display:none;
              position:absolute;
              top:40px;
              right:0;
              background:#1a1a1a;
              border:1px solid #333;
              border-radius:10px;
              width:300px;
              max-height:350px;
              overflow-y:auto;
              box-shadow:0 0 20px rgba(0,0,0,0.5);
              z-index:1000;
            ">
              <div id="notifContent" style="padding:10px;color:#fff;font-size:0.9rem;">
                <p style='color:#aaa;text-align:center;'>Loading...</p>
              </div>
            </div>
          </div>

          <style>
            .notif-count {
              position: absolute;
              top: -5px;
              right: -8px;
              background: #ff4d4d;
              color: white;
              border-radius: 50%;
              font-size: 0.7rem;
              font-weight: bold;
              padding: 3px 6px;
            }
            .notif-item {
              border-bottom: 1px solid #333;
              padding: 8px 10px;
              transition: background 0.3s ease;
            }
            .notif-item:hover {
              background: #222;
            }
            .notif-time {
              font-size: 0.75rem;
              color: #aaa;
            }

            /* Customer Service Chat Styles */
            .cs-chat-button {
              position: fixed;
              bottom: 30px;
              right: 30px;
              width: 60px;
              height: 60px;
              background: linear-gradient(135deg, #ff4d4d, #e60000);
              border-radius: 50%;
              display: flex;
              align-items: center;
              justify-content: center;
              cursor: pointer;
              box-shadow: 0 4px 15px rgba(255, 77, 77, 0.4);
              z-index: 1000;
              transition: all 0.3s ease;
            }

            .cs-chat-button:hover {
              transform: scale(1.1);
              box-shadow: 0 6px 20px rgba(255, 77, 77, 0.6);
            }

            .cs-chat-button i {
              color: white;
              font-size: 1.8rem;
            }

            .cs-chat-window {
              position: fixed;
              bottom: 100px;
              right: 30px;
              width: 380px;
              height: 500px;
              background: #1a1a1a;
              border: 1px solid #333;
              border-radius: 15px;
              display: none;
              flex-direction: column;
              box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
              z-index: 999;
              overflow: hidden;
            }

            .cs-chat-window.active {
              display: flex;
            }

            .cs-chat-header {
              background: linear-gradient(135deg, #ff4d4d, #e60000);
              padding: 15px 20px;
              color: white;
              display: flex;
              justify-content: space-between;
              align-items: center;
            }

            .cs-chat-header h3 {
              margin: 0;
              font-size: 1.1rem;
              font-weight: 600;
            }

            .cs-chat-header .close-btn {
              background: none;
              border: none;
              color: white;
              font-size: 1.5rem;
              cursor: pointer;
              padding: 0;
              line-height: 1;
            }

            .cs-chat-messages {
              flex: 1;
              padding: 20px;
              overflow-y: auto;
              background: #0f0f0f;
            }

            .cs-message {
              margin-bottom: 15px;
              display: flex;
              flex-direction: column;
            }

            .cs-message.user {
              align-items: flex-end;
            }

            .cs-message.agent {
              align-items: flex-start;
            }

            .cs-message-bubble {
              max-width: 75%;
              padding: 10px 15px;
              border-radius: 15px;
              word-wrap: break-word;
            }

            .cs-message.user .cs-message-bubble {
              background: #ff4d4d;
              color: white;
              border-bottom-right-radius: 5px;
            }

            .cs-message.agent .cs-message-bubble {
              background: #222;
              color: #fff;
              border-bottom-left-radius: 5px;
            }

            .cs-message-time {
              font-size: 0.7rem;
              color: #777;
              margin-top: 5px;
            }

            .cs-chat-input-area {
              padding: 15px;
              background: #1a1a1a;
              border-top: 1px solid #333;
              display: flex;
              gap: 10px;
            }

            .cs-chat-input {
              flex: 1;
              background: #0f0f0f;
              border: 1px solid #333;
              border-radius: 20px;
              padding: 10px 15px;
              color: white;
              font-size: 0.9rem;
              outline: none;
              transition: border-color 0.3s ease;
            }

            .cs-chat-input:focus {
              border-color: #ff4d4d;
            }

            .cs-send-btn {
              background: #ff4d4d;
              border: none;
              border-radius: 50%;
              width: 40px;
              height: 40px;
              display: flex;
              align-items: center;
              justify-content: center;
              cursor: pointer;
              transition: background 0.3s ease;
            }

            .cs-send-btn:hover {
              background: #e60000;
            }

            .cs-send-btn i {
              color: white;
              font-size: 1.2rem;
            }

            .cs-quick-replies {
              padding: 10px 20px;
              background: #1a1a1a;
              border-top: 1px solid #333;
              display: flex;
              flex-wrap: wrap;
              gap: 8px;
            }

            .cs-quick-reply-btn {
              background: #222;
              border: 1px solid #333;
              border-radius: 15px;
              padding: 6px 12px;
              color: #ccc;
              font-size: 0.8rem;
              cursor: pointer;
              transition: all 0.3s ease;
            }

            .cs-quick-reply-btn:hover {
              background: #ff4d4d;
              color: white;
              border-color: #ff4d4d;
            }

            @media (max-width: 768px) {
              .cs-chat-window {
                width: calc(100% - 20px);
                height: calc(100% - 120px);
                right: 10px;
                bottom: 90px;
              }
            }
          </style>
        <?php endif; ?>


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

    <!-- ✅ Banner Carousel -->
    <section class="banner-carousel">
      <div class="banner-wrapper" id="bannerWrapper">
        <?php foreach ($banners as $i => $banner): ?>
          <div class="banner-slide <?= $i === 0 ? 'active' : '' ?>" 
               style="background-image: url('<?= htmlspecialchars($banner) ?>');">
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Arrows -->
      <button class="banner-btn-left" onclick="prevSlide()">&#10094;</button>
      <button class="banner-btn-right" onclick="nextSlide()">&#10095;</button>

      <!-- Dots -->
      <div class="banner-dots" id="bannerDots">
        <?php foreach ($banners as $i => $_): ?>
          <span class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></span>
        <?php endforeach; ?>
      </div>
    </section>
    <!-- Pagebreak -->
      <img src="../assets/nebula-pagebreak.png" alt="pagebreak" class="shopparts-pagebreak">
    </section>

    <!-- ✅ Product Section (Target for scroll on pagination) -->
    <section class="shopparts-products-section" id="products-section">
      <?php if (!empty($searchQuery) || $selectedBrand !== "All"): ?>
        <a href="javascript:history.back()" class="back-button">← Back to Shop</a>
      <?php endif; ?>

      <!-- Filters: Category + Brand + Sort -->
      <div class="shopparts-filters" style="display:flex;flex-direction:column;gap:25px;margin-bottom:30px;">

        <!-- Category Filter -->
        <div class="shopparts-category-filter" style="display:flex;flex-wrap:wrap;gap:10px;">
          <?php
          $categories = ['Tires', 'Brakes', 'Engine Parts', 'Suspension', 'Seats', 'Lighting', 'Accessories'];
          foreach ($categories as $cat): ?>
            <a href="?category=<?= urlencode($cat) ?>&brand=<?= urlencode($selectedBrand) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=1#products-section" style="text-decoration:none;">
              <button style="
                background: <?= $selectedCategory === $cat ? '#ff4d4d' : '#111' ?>;
                color: <?= $selectedCategory === $cat ? '#fff' : '#ccc' ?>;
                border: 1px solid <?= $selectedCategory === $cat ? '#ff4d4d' : '#333' ?>;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
              " onmouseover="this.style.borderColor='#ff4d4d';this.style.background='#1a1a1a';" 
                onmouseout="this.style.borderColor='<?= $selectedCategory === $cat ? '#ff4d4d' : '#333' ?>';this.style.background='<?= $selectedCategory === $cat ? '#ff4d4d' : '#111' ?>';">
                <?= htmlspecialchars($cat) ?>
              </button>
            </a>
          <?php endforeach; ?>
        </div>
            <!-- Search Filter -->
            <div class="shopparts-search-filter" style="display:flex;align-items:center;gap:10px;">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search products..." 
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    style="
                        background:#111;
                        color:#fff;
                        border:1px solid #333;
                        border-radius:8px;
                        padding:8px 12px;
                        font-size:0.9rem;
                        transition:all 0.3s ease;
                        flex:1;
                    "
                    onmouseover="this.style.borderColor='#ff4d4d';this.style.background='#1a1a1a';"
                    onmouseout="this.style.borderColor='#333';this.style.background='#111';"
                    onkeypress="if(event.key === 'Enter'){ applySearch(); }"
                />
                <button 
                    type="button" 
                    onclick="applySearch()"
                    style="
                        background:#ff4d4d;
                        color:#fff;
                        border:none;
                        border-radius:8px;
                        padding:8px 14px;
                        cursor:pointer;
                        font-size:0.9rem;
                        transition:all 0.3s ease;
                    "
                    onmouseover="this.style.background='#e60000';"
                    onmouseout="this.style.background='#ff4d4d';"
                >
                    Search
                </button>
            </div>

        <!-- Brand Filter -->
        <div class="shopparts-product-category" style="display:flex;flex-wrap:wrap;gap:10px;">
          <?php foreach ($brands as $brand): ?>
            <a href="?brand=<?= urlencode($brand) ?>&category=<?= urlencode($selectedCategory) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=1#products-section" style="text-decoration:none;">
              <button style="
                background: <?= $selectedBrand === $brand ? '#ff4d4d' : '#111' ?>;
                color: <?= $selectedBrand === $brand ? '#fff' : '#ccc' ?>;
                border: 1px solid <?= $selectedBrand === $brand ? '#ff4d4d' : '#333' ?>;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
              " onmouseover="this.style.borderColor='#ff4d4d';this.style.background='#1a1a1a';" 
                onmouseout="this.style.borderColor='<?= $selectedBrand === $brand ? '#ff4d4d' : '#333' ?>';this.style.background='<?= $selectedBrand === $brand ? '#ff4d4d' : '#111' ?>';">
                <?= htmlspecialchars($brand) ?>
              </button>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Sort Filter -->
        <div class="shopparts-sort-filter" style="display:flex;align-items:center;gap:10px;justify-content:flex-end;">
          <label for="sort-select" style="color:#ccc;font-weight:500;">Sort by:</label>
          <select id="sort-select" onchange="applySort(this.value)" 
            style="background:#111;color:#fff;border:1px solid #333;border-radius:8px;padding:8px 12px;font-size:0.9rem;transition:all 0.3s ease;"
            onmouseover="this.style.borderColor='#ff4d4d';this.style.background='#1a1a1a';"
            onmouseout="this.style.borderColor='#333';this.style.background='#111';">
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
          </select>
        </div>
        <!-- Reset Filters Button -->
      <?php if (!empty($selectedBrand) || !empty($selectedCategory) || !empty($searchQuery) || $sort !== 'name_asc'): ?>
        <div style="display:flex;justify-content:flex-end;margin-top:10px;">
          <a href="shop.php#products-section" 
            style="
              text-decoration:none;
              background:#222;
              color:#ccc;
              border:1px solid #333;
              border-radius:8px;
              padding:8px 14px;
              font-size:0.9rem;
              transition:all 0.3s ease;
            "
            onmouseover="this.style.borderColor='#ff4d4d';this.style.color='#fff';this.style.background='#1a1a1a';"
            onmouseout="this.style.borderColor='#333';this.style.color='#ccc';this.style.background='#222';">
            Reset Filters
          </a>
        </div>
      <?php endif; ?>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:10px;">
          <a href="wishlist.php" 
            style="
              text-decoration:none;
              background:#111;
              color:#ccc;
              border:1px solid #333;
              border-radius:8px;
              padding:8px 14px;
              font-size:0.9rem;
              transition:all 0.3s ease;
            "
            onmouseover="this.style.borderColor='#ff4d4d';this.style.color='#fff';this.style.background='#1a1a1a';"
            onmouseout="this.style.borderColor='#333';this.style.color='#ccc';this.style.background='#111';">
            ❤️ View Wishlist
          </a>
        </div>

      

      <script>
      function applySort(sort) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('sort', sort);
        window.location.search = urlParams.toString();
      }
      </script>



      <!-- Product Grid -->
      <div class="shopparts-product-container">
        <?php if (count($products) > 0): ?>
          <?php foreach ($products as $product): 
              $outOfStock = ($product['stock'] <= 0);
          ?>
          <div class="shopparts-product-card-link" 
              style="<?= $outOfStock ? 'pointer-events:none; opacity:0.5;' : '' ?>">
            <div class="shopparts-product-card" style="position: relative;">
              
              <a href="productpage.php?id=<?= $product['id'] ?>" style="text-decoration:none; color:inherit;">
                <div class="shopparts-product-image-wrapper">
                  <img src="<?= htmlspecialchars($product['image']) ?>" 
                      alt="<?= htmlspecialchars($product['name']) ?>" 
                      class="shopparts-product-image">
                </div>
                <div class="shopparts-product-info">
                  <h3 class="shopparts-product-title"><?= htmlspecialchars($product['name']) ?></h3>
                  <p class="shopparts-product-price">₱ <?= number_format($product['price'], 2) ?></p>
                  <?php if ($outOfStock): ?>
                      <p style="color:#aaa; font-weight:bold;">Out of Stock</p>
                  <?php endif; ?>
                </div>
              </a>

              <!-- ❤️ Wishlist Toggle (bottom-right) -->
              <?php $inWishlist = in_array($product['id'], $userWishlist ?? []); ?>
              <a href="?toggle_wishlist=<?= $product['id'] ?>&<?= http_build_query($_GET) ?>"
                title="<?= $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>"
                onclick="event.stopPropagation(); event.preventDefault(); toggleWishlist(<?= $product['id'] ?>)"
                style="position:absolute; bottom:10px; right:10px; font-size:1.3rem; text-decoration:none;">
                <i id="heart-<?= $product['id'] ?>" 
                  class='bx <?= $inWishlist ? "bxs-heart" : "bx-heart" ?>'
                  style="color:<?= $inWishlist ? '#ff4d4d' : '#777' ?>;transition:color 0.3s ease;"
                  onmouseover="this.style.color='#ff4d4d';"
                  onmouseout="this.style.color='<?= $inWishlist ? '#ff4d4d' : '#777' ?>';"></i>
              </a>

            </div>
          </div>
          <?php endforeach; ?>

        <?php else: ?>
          <p>No products found.</p>
        <?php endif; ?>
      </div>

      <script>
      function toggleWishlist(id) {
        // Use fetch to toggle wishlist without reloading
        fetch(`toggle_wishlist.php?id=${id}`)
          .then(response => response.json())
          .then(data => {
            const icon = document.getElementById('heart-' + id);
            if (!icon) return;
            if (data.inWishlist) {
              icon.classList.remove('bx-heart');
              icon.classList.add('bxs-heart');
              icon.style.color = '#ff4d4d';
            } else {
              icon.classList.remove('bxs-heart');
              icon.classList.add('bx-heart');
              icon.style.color = '#777';
            }
          });
      }
      </script>



      <!-- Pagination with Arrow-Focused Navigation -->
      <?php if ($totalPages > 1): ?>
        <div class="shopparts-pagination">
          <!-- Previous Arrow Button -->
          <?php if ($page > 1): ?>
            <a href="?brand=<?= urlencode($selectedBrand) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page - 1 ?>#products-section" class="pagination-arrow prev-arrow">
              Previous
            </a>
          <?php endif; ?>

          <!-- Page Numbers (Optional, but kept for usability; can be hidden if desired) -->
          <div class="pagination-numbers">
            <?php 
            // Show limited page numbers (e.g., current ±2) to avoid clutter
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($page > 3) echo '<a href="?brand=' . urlencode($selectedBrand) . '&search=' . urlencode($searchQuery) . '&sort=' . urlencode($sort) . '&page=1#products-section" class="pagination-btn">1</a><span>...</span>';
            for ($i = $startPage; $i <= $endPage; $i++): ?>
              <a href="?brand=<?= urlencode($selectedBrand) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=<?= $i ?>#products-section" 
                 class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; 
            if ($page < $totalPages - 2) echo '<span>...</span><a href="?brand=' . urlencode($selectedBrand) . '&search=' . urlencode($searchQuery) . '&sort=' . urlencode($sort) . '&page=' . $totalPages . '#products-section" class="pagination-btn">' . $totalPages . '</a>'; ?>
          </div>

          <!-- Next Arrow Button -->
          <?php if ($page < $totalPages): ?>
            <a href="?brand=<?= urlencode($selectedBrand) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page + 1 ?>#products-section" class="pagination-arrow next-arrow">
              Next
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
            <!-- ✅ Customer Service Chat Widget -->
            <div class="cs-chat-button" id="csChatButton" onclick="toggleChat()">
              <i class='bx bx-message-dots'></i>
            </div>

            <div class="cs-chat-window" id="csChatWindow">
              <div class="cs-chat-header">
                <h3>Customer Support</h3>
                <button class="close-btn" onclick="toggleChat()">&times;</button>
              </div>

              <div class="cs-chat-messages" id="csChatMessages">
                <div class="cs-message agent">
                  <div class="cs-message-bubble">
                    Hello! Welcome to our customer service. How can we help you today?
                  </div>
                  <div class="cs-message-time"><?= date('h:i A') ?></div>
                </div>
              </div>

              <div class="cs-quick-replies">
                <button class="cs-quick-reply-btn" onclick="sendQuickReply('Track my order')">Track Order</button>
                <button class="cs-quick-reply-btn" onclick="sendQuickReply('Product inquiry')">Product Info</button>
                <button class="cs-quick-reply-btn" onclick="sendQuickReply('Shipping info')">Shipping</button>
                <button class="cs-quick-reply-btn" onclick="sendQuickReply('Return/Refund')">Returns</button>
              </div>

              <div class="cs-chat-input-area">
                <input type="text" class="cs-chat-input" id="csChatInput" placeholder="Type your message..." onkeypress="if(event.key === 'Enter'){ sendMessage(); }">
                <button class="cs-send-btn" onclick="sendMessage()">
                  <i class='bx bx-send'></i>
                </button>
              </div>
            </div>
    <!-- ✅ Carousel Script -->
    <script>
      let slideIndex = 0;
      const slides = document.querySelectorAll('.banner-slide');
      const dots = document.querySelectorAll('.dot');

      function showSlide(index) {
        slides.forEach((s, i) => {
          s.classList.remove('active');
          dots[i].classList.remove('active');
        });
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        slideIndex = index;
      }

      function nextSlide() {
        let next = (slideIndex + 1) % slides.length;
        showSlide(next);
      }

      function prevSlide() {
        let prev = (slideIndex - 1 + slides.length) % slides.length;
        showSlide(prev);
      }

      function goToSlide(n) {
        showSlide(n);
      }

      // Auto-slide
      setInterval(nextSlide, 5000);

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

      function applySort(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        url.searchParams.set('page', '1'); // Reset to first page
        url.hash = 'products-section'; // Ensure scroll to products after sort
        window.location.href = url.toString();
      }

      document.addEventListener("click", (e) => {
        const menu = document.getElementById("dropdownMenu");
        const userMenu = document.getElementById("userMenu");
        if (menu && menu.style.display === "block" && !userMenu.contains(e.target)) {
          menu.style.display = "none";
        }
      });

      // Optional: Smooth scroll to products section on page load (enhances the anchor behavior)
      window.addEventListener('load', function() {
        const hash = window.location.hash;
        if (hash === '#products-section') {
          const target = document.getElementById('products-section');
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      });

      function applySearch() {
      const searchTerm = document.getElementById('searchInput').value.trim();
      const urlParams = new URLSearchParams(window.location.search);

      if(searchTerm !== '') {
          urlParams.set('search', searchTerm);
      } else {
          urlParams.delete('search'); // Remove filter if empty
      }

      urlParams.set('page', '1'); // Reset pagination
      window.location.search = urlParams.toString() + '#products-section';
  }

      document.addEventListener('DOMContentLoaded', () => {
      const notifBtn = document.getElementById('notifBtn');
      const notifPopup = document.getElementById('notifPopup');
      const notifContent = document.getElementById('notifContent');

      // Toggle popup visibility
      notifBtn.addEventListener('click', () => {
        notifPopup.style.display = notifPopup.style.display === 'block' ? 'none' : 'block';
        if (notifPopup.style.display === 'block') loadNotifications();
      });

      // Close popup when clicking outside
      document.addEventListener('click', (e) => {
        if (!notifPopup.contains(e.target) && !notifBtn.contains(e.target)) {
          notifPopup.style.display = 'none';
        }
      });

      // Load notifications from backend
      function loadNotifications() {
        fetch('load_notifications.php')
          .then(res => res.text())
          .then(html => notifContent.innerHTML = html)
          .catch(() => notifContent.innerHTML = "<p style='color:#aaa;text-align:center;'>Error loading notifications</p>");
      }

      // Optional: auto-refresh notifications every 30s
      setInterval(() => {
        if (notifPopup.style.display === 'block') loadNotifications();
      }, 30000);
    });

    // ✅ Customer Service Chat Functions
    function toggleChat() {
      const chatWindow = document.getElementById('csChatWindow');
      chatWindow.classList.toggle('active');
    }

    function sendMessage() {
      const input = document.getElementById('csChatInput');
      const message = input.value.trim();
      
      if (message === '') return;

      addMessage(message, 'user');
      input.value = '';

      setTimeout(() => {
        const response = getAutomatedResponse(message);
        addMessage(response, 'agent');
      }, 1000);
    }

    function sendQuickReply(message) {
      addMessage(message, 'user');
      
      setTimeout(() => {
        const response = getAutomatedResponse(message);
        addMessage(response, 'agent');
      }, 1000);
    }

    function addMessage(text, sender) {
      const messagesContainer = document.getElementById('csChatMessages');
      const messageDiv = document.createElement('div');
      messageDiv.className = `cs-message ${sender}`;
      
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
      
      messageDiv.innerHTML = `
        <div class="cs-message-bubble">${text}</div>
        <div class="cs-message-time">${timeStr}</div>
      `;
      
      messagesContainer.appendChild(messageDiv);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function getAutomatedResponse(message) {
      const lowerMsg = message.toLowerCase();
      
      if (lowerMsg.includes('track') || lowerMsg.includes('order')) {
        return 'To track your order, please go to your profile page and check the "My Orders" section. You can also contact us with your order number for assistance.';
      } else if (lowerMsg.includes('product') || lowerMsg.includes('info')) {
        return 'For product information, you can click on any product card to see detailed specifications, pricing, and availability. Need help with a specific product?';
      } else if (lowerMsg.includes('ship')) {
        return 'We offer nationwide shipping! Standard delivery takes 3-7 business days. Express shipping is also available. Shipping costs are calculated at checkout based on your location.';
      } else if (lowerMsg.includes('return') || lowerMsg.includes('refund')) {
        return 'We have a 7-day return policy for unused items in original packaging. Please contact us through our Contact Us page to initiate a return.';
      } else if (lowerMsg.includes('payment')) {
        return 'We accept various payment methods including credit/debit cards, bank transfers, and cash on delivery. All transactions are secure and encrypted.';
      } else if (lowerMsg.includes('stock') || lowerMsg.includes('available')) {
        return 'Product availability is shown on each product page. If an item shows "Out of Stock", you can add it to your wishlist to be notified when it\'s back in stock.';
      } else {
        return 'Thank you for your message! For more specific assistance, please visit our Contact Us page or call our customer service hotline. Our team will be happy to help you!';
      }
    }

    </script>
</body>
</html>