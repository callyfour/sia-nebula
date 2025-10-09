<?php
session_start();
require "db.php"; // PDO connection
require_once '../vendor/autoload.php'; // Google API client

// Google OAuth setup
$googleClient = new Google\Client();
$googleClient->setClientId('60704026509-0tbhbko4smo0gendjlbv974dvcc5onkh.apps.googleusercontent.com');
$googleClient->setClientSecret('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');
$googleClient->setRedirectUri('http://localhost/nebula/php/redirect.php'); // adjust URL
$googleClient->addScope('email');
$googleClient->addScope('profile');

$googleClient->setPrompt('select_account');
$googleLoginUrl = $googleClient->createAuthUrl();

// Handle standard login
$error = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user"] = [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"],
            ];

            // ✅ Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: ../admin/admin.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Login</title>
<link rel="stylesheet" href="../style/login.css" />
<link rel="stylesheet" href="../style/index.css" />
</head>
<body>
<div class="login-container">
  <div class="login-wrapper">
    <!-- Login Form -->
    <div class="login-form">
      <h2>Welcome back</h2>
      <p class="subtitle">Please enter your account details</p>

      <?php if (!empty($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <label>Email</label>
        <input type="email" name="email" required />

        <label>Password</label>
        <input type="password" name="password" required />

        <div class="options">
          <label>
            <input type="checkbox" id="keepLoggedIn" /> Keep me logged in
          </label>
          <a href="forgot-password.php">Forgot Password</a>
        </div>

        <button type="submit" name="login" class="btn">Sign In</button>

        <!-- Google Login Button -->
        <button type="button" class="google-btn" onclick="window.location.href='<?= htmlspecialchars($googleLoginUrl) ?>'">
          <img src="../assets/google-logo.png" alt="Google" class="google-logo" />
          Continue with Google
        </button>

        <p class="signup">
          Don’t have an account? <a href="register.php">Sign up</a>
        </p>
      </form>
    </div>

    <!-- Promo Image -->
    <div class="login-promo">
      <img src="../assets/promo-photo.png" alt="Promo" class="promo-image" />
    </div>
  </div>
</div>
</body>
</html>
