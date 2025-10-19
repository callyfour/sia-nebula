<?php
session_start();
require "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user']['id'];
$message = '';
$messageType = 'success';

// Fetch profile
$stmt = $pdo->prepare("SELECT phone, billingAddress, shippingAddress FROM users WHERE id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'phone' => '',
    'billingAddress' => '',
    'shippingAddress' => ''
];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $phone = trim($_POST['phone'] ?? '');
    $billingAddress = trim($_POST['billingAddress'] ?? '');
    $shippingAddress = trim($_POST['shippingAddress'] ?? '');

    $stmt = $pdo->prepare("UPDATE users SET phone=?, billingAddress=?, shippingAddress=? WHERE id=?");
    if($stmt->execute([$phone, $billingAddress, $shippingAddress, $userId])){
        $messageType = 'success';
        $message = "✅ Contact & Address updated successfully!";
        $profile = ['phone'=>$phone,'billingAddress'=>$billingAddress,'shippingAddress'=>$shippingAddress];
    } else {
        $messageType = 'error';
        $message = "Error updating data.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact & Address</title>
    <link rel="stylesheet" href="../style/profile.css">
    <link rel="stylesheet" href="../style/index.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
</head>
<body>
    <div class="profile-container">
        <div class="profile-sidebar">
            <h3><i class='bx bx-menu'></i> Menu</h3>
            <form method="POST">
                <button type="button" onclick="goProfilePage('profile')"><i class='bx bx-user'></i> Profile</button>
                <button class="active" type="button"><i class='bx bx-map'></i> Contact & Address</button>
                <button type="submit" name="logout"><i class='bx bx-log-out'></i> Logout</button>
            </form>
        </div>

        <div class="profile-card">
            <?php if($message): ?>
            <div class="profile-message <?= $messageType ?>">
                <i class='bx <?= $messageType === "success" ? "bx-check-circle" : "bx-error" ?>'></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div>
                        <label for="phone"><i class='bx bx-phone'></i> Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="billingAddress"><i class='bx bx-credit-card'></i> Billing Address</label>
                    <textarea id="billingAddress" name="billingAddress"><?= htmlspecialchars($profile['billingAddress'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="shippingAddress"><i class='bx bx-package'></i> Shipping Address</label>
                    <textarea id="shippingAddress" name="shippingAddress"><?= htmlspecialchars($profile['shippingAddress'] ?? '') ?></textarea>
                </div>

                <div class="btn-container">
                    <button type="submit" name="save" class="btn-save">
                        <i class='bx bx-save'></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
function goProfilePage(page) {
    if(page === 'profile') window.location.href = 'profile.php';
    if(page === 'contact') window.location.href = 'profile_contact.php';
}
</script>
</body>
</html>
