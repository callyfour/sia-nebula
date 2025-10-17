<?php
session_start();
require "db.php";

if (!isset($_SESSION["pending_user"])) {
    header("Location: register.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input_code = trim($_POST["code"]);
    $stored = $_SESSION["pending_user"];

    if ($input_code == $stored["code"]) {
        // Save user to database
        $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, role, created_at)
                               VALUES (?, ?, ?, ?, ?, 'user', NOW())");
        $stmt->execute([
            $stored["first_name"],
            $stored["middle_name"],
            $stored["last_name"],
            $stored["email"],
            $stored["password"]
        ]);

        $_SESSION["user"] = [
            "id" => $pdo->lastInsertId(),
            "name" => $stored["first_name"] . " " . $stored["last_name"],
            "email" => $stored["email"]
        ];

        unset($_SESSION["pending_user"]);
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid verification code!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Verification</title>
  <link rel="stylesheet" href="../style/verify.css">
</head>
<body>
  <div class="verify-container">

    <h2>Email Verification</h2>
    <p>We’ve sent a 6-digit code to your email. Enter it below to verify your account.</p>

    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="code" maxlength="6" required placeholder="Enter code">
      <button type="submit">Verify</button>
    </form>
  </div>
</body>
</html>

