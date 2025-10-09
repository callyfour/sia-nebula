<?php
session_start();
require "db.php"; // PDO connection

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm = trim($_POST["confirm_password"]);

    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = "Email already registered!";
        } else {
            // Hash the password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
            $stmt->execute([$name, $email, $hashed]);

            // Store session
            $_SESSION["user"] = [
                "id" => $pdo->lastInsertId(),
                "name" => $name,
                "email" => $email
            ];

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register</title>
    <link rel="stylesheet" href="../style/login.css" />
    <link rel="stylesheet" href="../style/index.css" />
  </head>
  <body>
    <div class="login-container">
      <div class="login-wrapper">
        <!-- Register Form -->
        <div class="login-form">
          <h2>Create an Account</h2>
          <p class="subtitle">Join Nebula Auto Parts today</p>

          <?php if (!empty($error)): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
          <?php endif; ?>

          <form method="POST" action="register.php">
            <label>Name</label>
            <input type="text" name="name" required />

            <label>Email</label>
            <input type="email" name="email" required />

            <label>Password</label>
            <input type="password" name="password" required />

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required />

            <!-- Register Button -->
            <button type="submit" class="btn">Sign Up</button>

            <p class="signup">
              Already have an account? <a href="login.php">Sign in</a>
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
