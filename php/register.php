<?php
session_start();
require "db.php"; // PDO connection
require "../vendor/autoload.php"; // PHPMailer (adjust path if needed)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first = trim($_POST["first_name"]);
    $middle = trim($_POST["middle_name"]);
    $last = trim($_POST["last_name"]);
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

            // Generate a 6-digit verification code
            $code = rand(100000, 999999);

            // Store temporarily in session before verification
            $_SESSION["pending_user"] = [
                "first_name" => $first,
                "middle_name" => $middle,
                "last_name" => $last,
                "email" => $email,
                "password" => $hashed,
                "code" => $code
            ];

            // Send verification email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'nebulaautoparts@gmail.com'; // Your Gmail
                $mail->Password = 'ddmn pawh zyzm qidh';  // Use App Password (not your real one)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('yourgmail@gmail.com', 'Nebula Auto Parts');
                $mail->addAddress($email, $first . " " . $last);
                $mail->isHTML(true);
                $mail->Subject = 'Your Nebula 2FA Verification Code';
                $mail->Body = "<p>Hello $first,</p><p>Your verification code is: <b>$code</b></p>";

                $mail->send();
                header("Location: verify.php");
                exit;
            } catch (Exception $e) {
                $error = "Failed to send verification email. Error: {$mail->ErrorInfo}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Register</title>
  <link rel="stylesheet" href="../style/register.css" />
  <link rel="stylesheet" href="../style/index.css" />
</head>
<body>
  <div class="register-container">
    <div class="register-card">
        <h2>Create an Account</h2>
        <p class="subtitle">Join Nebula Auto Parts today</p>

        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <label>First Name</label>
            <input type="text" name="first_name" required />

            <label>Middle Name</label>
            <input type="text" name="middle_name" />

            <label>Last Name</label>
            <input type="text" name="last_name" required />

            <label>Email</label>
            <input type="email" name="email" required />

            <label>Password</label>
            <input type="password" name="password" required />

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required />

            <button type="submit" class="btn-register">Sign Up</button>

            <p class="text-login">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </form>
    </div>
</div>

      
    </div>
  </div>
</body>
</html>
