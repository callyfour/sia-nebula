<?php
session_start();
require "db.php"; // PDO connection

// Redirect to login if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user']['id'];
$message = '';
$messageType = 'success';

// Fetch profile from DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile existence
if (!$profile) {
    $profileExists = false;
    $profile = [
        'name' => $_SESSION['user']['name'] ?? '',
        'email' => $_SESSION['user']['email'] ?? '',
        'phone' => '',
        'gender' => '',
        'address' => '',
        'profilePicture' => '',
        'authProvider' => $_SESSION['user']['authProvider'] ?? 'local'
    ];
} else {
    $profileExists = true;
    // Fill missing keys
    $profile = array_merge([
        'name' => '',
        'email' => '',
        'phone' => '',
        'gender' => '',
        'address' => '',
        'profilePicture' => '',
        'authProvider' => 'local'
    ], $profile);
}

$isGoogleUser = ($profile['authProvider'] ?? 'local') === 'google';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $profilePicture = $profile['profilePicture'];

    // Validation
    if (empty($name)) {
        $messageType = 'error';
        $message = "Name is required.";
    } else {

        // Handle profile picture upload (only for local users)
        if (!$isGoogleUser && isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === 0) {
            $allowedExts = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($_FILES['profilePicture']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExts) && $_FILES['profilePicture']['size'] <= 5*1024*1024) {
                $filename = 'profile_'.$userId.'_'.time().'.'.$ext;
                $targetDir = __DIR__.'/uploads/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

                if (move_uploaded_file($_FILES['profilePicture']['tmp_name'], $targetDir.$filename)) {
                    // Delete old picture
                    if (!empty($profile['profilePicture']) && file_exists($targetDir.$profile['profilePicture'])) {
                        unlink($targetDir.$profile['profilePicture']);
                    }
                    $profilePicture = $filename;
                } else {
                    $messageType = 'error';
                    $message = "Error uploading profile picture.";
                }
            } else {
                $messageType = 'error';
                $message = "Invalid image file (JPG, PNG, GIF, WEBP - max 5MB).";
            }
        }

        // Update DB if no errors
        if ($messageType !== 'error') {
            $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, gender=?, address=?, profilePicture=? WHERE id=?");
            if ($stmt->execute([$name, $phone, $gender, $address, $profilePicture, $userId])) {
                $messageType = 'success';
                $message = "✅ Profile updated successfully!";
                // Refresh profile data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $stmt->execute([$userId]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $messageType = 'error';
                $message = "Error updating profile.";
            }
        }
    }
}

// Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get profile picture URL
function getProfilePictureUrl($profile) {
    if (!empty($profile['profilePicture']) && ($profile['authProvider'] ?? 'local') === 'local') {
        return 'uploads/'.$profile['profilePicture'];
    }
    if (!empty($profile['authProvider']) && $profile['authProvider'] === 'google' && !empty($profile['profilePicture'])) {
        return $profile['profilePicture'];
    }
    return '';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?= htmlspecialchars($profile['name'] ?: 'User ') ?></title>
    <link rel="stylesheet" href="../style/profile.css">
    <link rel="stylesheet" href="../style/index.css" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    <div class="profile-container">
        <div class="profile-sidebar">
            <h3><i class='bx bx-menu'></i> Menu</h3>
            <form method="POST">
                <button class="active" type="button"><i class='bx bx-user'></i> Profile</button>
                <button type="button"><i class='bx bx-cog'></i> Settings</button>
                <button type="submit" name="logout"><i class='bx bx-log-out'></i> Logout</button>
            </form>
        </div>

        <div class="profile-card">
            <div class="profile-avatar">
                <img 
                    src="<?= htmlspecialchars(getProfilePictureUrl($profile)) ?>" 
                    alt="Profile Picture"
                    style="display: <?= !empty(getProfilePictureUrl($profile)) ? 'block' : 'none' ?>;"
                >
                <div class="placeholder-avatar">
                    <?= !empty($profile['name']) ? strtoupper(substr($profile['name'], 0, 1)) : '?' ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="file-input-container">
                    <input type="file" id="profilePicture" name="profilePicture" <?= $isGoogleUser  ? 'disabled' : '' ?> accept="image/*">
                    <label for="profilePicture" class="<?= $isGoogleUser  ? 'disabled' : '' ?>">
                        <i class='bx bx-camera'></i>
                        <?= $isGoogleUser  ? 'Google Account Picture' : (!empty($profile['profilePicture']) ? 'Change Picture' : 'Upload Picture') ?>
                    </label>
                </div>

                <?php if ($message): ?>
                    <div class="profile-message <?= $messageType ?>">
                        <i class='bx <?= $messageType === 'success' ? 'bx-check-circle' : 'bx-error' ?>'></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-name"><?= htmlspecialchars($profile['name'] ?: 'Your Name') ?></div>
                <div class="profile-role"><?= $isGoogleUser  ? 'Google User' : 'Local User' ?></div>

                <div class="form-row">
                    <div>
                        <label for="name"><i class='bx bx-user'></i> Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($profile['name']) ?>" required>
                    </div>
                    <div>
                        <label for="email"><i class='bx bx-envelope'></i> Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" disabled>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="phone"><i class='bx bx-phone'></i> Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label><i class='bx bx-male-female'></i> Gender</label>
                        <div class="gender-options">
                            <label><input type="radio" name="gender" value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'checked' : '' ?>> Male</label>
                            <label><input type="radio" name="gender" value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'checked' : '' ?>> Female</label>
                            <label><input type="radio" name="gender" value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'checked' : '' ?>> Other</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address"><i class='bx bx-map'></i> Address</label>
                    <textarea id="address" name="address" placeholder="Enter your address"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                </div>

                <div class="btn-container">
                    <button type="submit" name="save" class="btn-save">
                        <i class='bx bx-save'></i> <?= $profileExists ? 'Save Changes' : 'Create Profile' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ✅ Image Preview for Profile Picture
        document.getElementById('profilePicture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && !<?= json_encode($isGoogleUser) ?>) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const avatarImg = document.querySelector('.profile-avatar img');
                    const placeholder = document.querySelector('.profile-avatar .placeholder-avatar');

                    if (avatarImg) {
                        avatarImg.src = ev.target.result;
                        avatarImg.style.display = 'block'; // show image
                    }
                    if (placeholder) {
                        placeholder.style.display = 'none'; // hide placeholder
                    }
                };
                reader.readAsDataURL(file);
            }
        });


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

        // ✅ Client-Side Validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            if (!name) {
                e.preventDefault();
                alert('Name is required.');
            }
        });
    </script>
</body>
</html>