<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us</title>
    <link rel="stylesheet" href="../style/about.css" />
    
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

    <img src="../assets/about-us/about-nebula.png" alt="About Nebula" class="about-banner" />

    <img src="../assets/about-us/about-divider.png" alt="Nebula" class="about-banner" />

    <img src="../assets/about-us/about-team.png" alt="Meet Our Team" class="about-banner" />

    <img src="../assets/about-us/about-mission.png" alt="Mission" class="about-banner" />

    <div class="shop-now">
      <a href="shop.php" class="shop-button">SHOP NOW<a/>
    </div>

    

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
