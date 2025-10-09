<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nebula Auto Parts</title>
    <link rel="stylesheet" href="../style/index.css" />
    
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

    <!-- ✅ Header / Slideshow -->
    <section id="header">
      <div>
        <h1>Your One-Stop Shop for Quality Auto Parts</h1>
        <p>Your go-to online destination for reliable auto parts and accessories.</p>
        <div>
          <button>
            <svg viewBox="0 0 200 50" xmlns="http://www.w3.org/2000/svg">
              <polygon points="12,0 200,0 200,30 188,50 0,50 0,12"
                stroke="black" stroke-width="2" fill="lightgray" />
              <text x="50%" y="60%" text-anchor="middle" fill="black" font-size="14" font-family="Arial, sans-serif">
                LEARN MORE
              </text>
            </svg>
          </button>

          <button>
            <svg viewBox="0 0 200 50" xmlns="http://www.w3.org/2000/svg">
              <polygon points="12,0 200,0 200,30 188,50 0,50 0,12"
                stroke="black" stroke-width="2" fill="lightgray" />
              <text x="50%" y="60%" text-anchor="middle" fill="black" font-size="12" font-family="Arial, sans-serif">
                CONTACT US TODAY
              </text>
            </svg>
          </button>
        </div>
      </div>
    </section>

    <!-- ✅ Extra images -->
    <img src="../assets/logo-words.png" alt="Nebula Logo" class="nebula-logo" />
    <img src="../assets/nebula-pagebreak.png" alt="Page Break" class="page-break" />
    <img src="../assets/nebula-section.png" alt="Nebula Section" class="nebula-section" />

    <!-- ✅ Carousel Section -->
    <section class="carousel-section">
      <div class="carousel-wrapper">
        <div class="carousel-slide active" style="background-image: url('../assets/carousel-1.png')"></div>
        <div class="carousel-slide" style="background-image: url('../assets/carousel-2.png')"></div>
        <div class="carousel-slide" style="background-image: url('../assets/carousel-3.png')"></div>
      </div>

      <!-- Navigation arrows -->
      <button class="carousel-btn left">&#10094;</button>
      <button class="carousel-btn right">&#10095;</button>

      <!-- Page break -->
      <img src="../assets/red-pagebreak.png" alt="pagebreak" class="carousel-pagebreak" />

      <!-- Dots -->
      <div class="carousel-dots">
        <span class="dot active"></span>
        <span class="dot"></span>
        <span class="dot"></span>
      </div>
    </section>

    <section class="appoint-section">
      <div class="appoint-content">
        <button class="appoint-button">
          Let’s Get Started , Find What Your Car Needs!
        </button>
      </div>
    </section>

    <section class="featured-section">
      <h2 class="section-title">Featured Collections</h2>
      <div class="cards-container">
        <div class="card">
          <img src="../assets/sample1.png" alt="Item 1" class="card-image" />
          <h3 class="card-title">Brake Pads</h3>
          <p class="card-price">₱ 1,200</p>
        </div>

        <div class="card">
          <img src="../assets/sample2.png" alt="Item 2" class="card-image" />
          <h3 class="card-title">Engine Oil</h3>
          <p class="card-price">₱ 850</p>
        </div>

        <div class="card">
          <img src="../assets/sample3.png" alt="Item 3" class="card-image" />
          <h3 class="card-title">Car Battery</h3>
          <p class="card-price">₱ 4,500</p>
        </div>
      </div>
    </section>

    <!-- White pagebreak image -->
    <img src="../assets/white-pagebreak.png" alt="pagebreak" class="white-pagebreak" />
    <img src="../assets/promo-banner.png" alt="Promo Banner" class="banner-promo" />

    <div class="promo-banner">
      <img src="../assets/logo.png" alt="Banner" class="promo-bg" />
      <div class="promo-content">
        <h1>Save some money.</h1>
        <p>
          You deserve it. Check out our discounts and specials for new
          customers, students, service industry professionals, and more!
        </p>
      </div>

      <div class="promo-banner-cards">
        <div class="promo-card">
          <div class="promo-card-content">
            <h3>OIL CHANGE</h3>
          </div>
          <div class="promo-card-icon">
            <span style="font-size: 40px; color: white">◆</span>
          </div>
        </div>

        <div class="promo-card">
          <div class="promo-card-content">
            <h3>DISCOUNT FOR NEW CUSTOMER</h3>
          </div>
          <div class="promo-card-icon">
            <span style="font-size: 40px; color: white">◆</span>
          </div>
        </div>

        <div class="promo-card">
          <div class="promo-card-content">
            <h3>STUDENT DISCOUNT</h3>
          </div>
          <div class="promo-card-icon">
            <span style="font-size: 40px; color: white">◆</span>
          </div>
        </div>
      </div>
    </div>

    <img src="/assets/appoint-pagebreak.png" alt="Promo Banner" class="banner-promo" />

    <!-- ✅ Footer -->
    <footer class="footer">
      <div class="footer-brands">
        <img src="../assets/brands/1.png" alt="Brand 1" />
        <img src="../assets/brands/2.png" alt="Brand 2" />
        <img src="../assets/brands/3.png" alt="Brand 3" />
        <img src="../assets/brands/4.png" alt="Brand 4" />
        <img src="../assets/brands/5.png" alt="Brand 5" />
        <img src="../assets/brands/6.png" alt="Brand 6" />
        <img src="../assets/brands/7.png" alt="Brand 7" />
        <img src="../assets/brands/8.png" alt="Brand 8" />
      </div>

      <div class="footer-top">
        <div class="footer-logo">
          <img src="/assets/logo-words.png" alt="Nebula Autoworks Logo" />
        </div>

        <div class="footer-section">
          <h4>We’re here for you</h4>
          <p>
            Reach out to
            <a href="mailto:nebulaautoworks@gmail.com">nebulaautoworks@gmail.com</a>
            for any questions or requests, and we’ll get back to you within one business day.
          </p>
        </div>

        <div class="footer-section">
          <h4>About Nebula Autoworks</h4>
          <ul>
            <li><a href="#">Our Blog</a></li>
            <li><a href="#">Contact us</a></li>
          </ul>
        </div>

        <div class="footer-section">
          <h4>Shop</h4>
          <ul>
            <li><a href="#">About us</a></li>
            <li><a href="#">Services</a></li>
          </ul>
        </div>
      </div>

      <p class="footer-tagline">
        You got a guy. Here at Nebula Autoworks, we take care of the cars and
        people in our community, and we always provide quality without compromise.
      </p>

      <div class="footer-socials">
        <a href="#"><i class="bx bxl-facebook"></i></a>
        <a href="#"><i class="bx bxl-instagram"></i></a>
        <a href="#"><i class="bx bxl-twitter"></i></a>
        <a href="#"><i class="bx bxl-tiktok"></i></a>
      </div>

      <div class="footer-bottom">
        <p>All rights reserved</p>
      </div>
    </footer>

    <!-- ✅ Scripts -->
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

      // --- Slideshow logic ---
      const images = [
        "../assets/banner.jpg",
        "../assets/banner2.jpg",
        "../assets/banner3.jpg",
        "../assets/banner4.jpg",
      ];
      let index = 0;
      const header = document.getElementById("header");
      header.style.backgroundImage = `url(${images[index]})`;

      setInterval(() => {
        index = (index + 1) % images.length;
        header.style.backgroundImage = `url(${images[index]})`;
      }, 5000);

      // --- Carousel logic ---
      const slides = document.querySelectorAll(".carousel-slide");
      const dots = document.querySelectorAll(".dot");
      let carouselIndex = 0;

      function showSlide(i) {
        slides.forEach((slide, idx) => {
          slide.classList.toggle("active", idx === i);
          dots[idx].classList.toggle("active", idx === i);
        });
      }

      document.querySelector(".carousel-btn.left").addEventListener("click", () => {
        carouselIndex = (carouselIndex - 1 + slides.length) % slides.length;
        showSlide(carouselIndex);
      });

      document.querySelector(".carousel-btn.right").addEventListener("click", () => {
        carouselIndex = (carouselIndex + 1) % slides.length;
        showSlide(carouselIndex);
      });

      dots.forEach((dot, i) => {
        dot.addEventListener("click", () => {
          carouselIndex = i;
          showSlide(carouselIndex);
        });
      });

      setInterval(() => {
        carouselIndex = (carouselIndex + 1) % slides.length;
        showSlide(carouselIndex);
      }, 4000);
    </script>
  </body>
</html>
