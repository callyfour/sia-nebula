<?php
session_start();
require "db.php"; // ✅ PDO connection

// --- Handle search query from URL ---
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : "";

// --- Handle brand filter ---
$selectedBrand = isset($_GET['brand']) ? $_GET['brand'] : "All";

// --- Handle sort ---
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc'; // Default: name ascending
$orderBy = match($sort) {
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    default => 'name ASC'
};

// --- Handle pagination ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 8; // Limit to 8 products per page (as requested)
$offset = ($page - 1) * $limit;

// --- Build WHERE clause for filtering ---
$whereConditions = [];
$params = [];

if (!empty($searchQuery)) {
    $whereConditions[] = "(name LIKE ? OR brand LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($selectedBrand !== "All") {
    $whereConditions[] = "brand = ?";
    $params[] = $selectedBrand;
}

$whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

// --- Fetch total count for pagination ---
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// --- Fetch paginated and sorted products ---
$stmt = $pdo->prepare("SELECT * FROM products $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Unique brands for filter buttons (fetch all unique brands) ---
$brandStmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
array_unshift($brands, "All");

// ✅ Local banner images (add your own files in /assets/banners/)
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

      <!-- Filters: Brand + Sort -->
      <div class="shopparts-filters">
        <!-- Brand Filter -->
        <div class="shopparts-product-category">
          <?php foreach ($brands as $brand): ?>
            <a href="?brand=<?= urlencode($brand) ?>&search=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sort) ?>&page=1#products-section">
              <button class="brand-btn <?= $selectedBrand === $brand ? 'active' : '' ?>">
                <?= htmlspecialchars($brand) ?>
              </button>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Sort Filter -->
        <div class="shopparts-sort-filter">
          <label for="sort-select">Sort by:</label>
          <select id="sort-select" onchange="applySort(this.value)">
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
          </select>
        </div>
      </div>

      <!-- Product Grid -->
      <div class="shopparts-product-container">
        <?php if (count($products) > 0): ?>
          <?php foreach ($products as $product): ?>
            <a href="productpage.php?id=<?= $product['id'] ?>" class="shopparts-product-card-link">
              <div class="shopparts-product-card">
                <div class="shopparts-product-image-wrapper">
                  <img src="<?= htmlspecialchars($product['image']) ?>" 
                       alt="<?= htmlspecialchars($product['name']) ?>" 
                       class="shopparts-product-image">
                </div>
                <div class="shopparts-product-info">
                  <h3 class="shopparts-product-title"><?= htmlspecialchars($product['name']) ?></h3>
                  <p class="shopparts-product-price">₱ <?= number_format($product['price'], 2) ?></p>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No products found.</p>
        <?php endif; ?>
      </div>

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
    </script>
</body>
</html>