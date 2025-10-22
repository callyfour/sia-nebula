<?php
session_start();
require "db.php";

if (!isset($_SESSION['user'])) {
  echo "<p style='color:#aaa;text-align:center;'>Please log in to view notifications.</p>";
  exit;
}

$userId = $_SESSION['user']['id'];

// Fetch recent notifications
$stmt = $pdo->prepare("
  SELECT n.*, p.name AS product_name
  FROM notifications n
  JOIN products p ON n.product_id = p.id
  WHERE n.user_id = ?
  ORDER BY n.created_at DESC
  LIMIT 15
");
$stmt->execute([$userId]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark them as seen
$pdo->prepare("UPDATE notifications SET seen = 1 WHERE user_id = ?")->execute([$userId]);

if (!$notifs) {
  echo "<p style='color:#aaa;text-align:center;'>No notifications yet.</p>";
  exit;
}

foreach ($notifs as $n) {
  echo "<div class='notif-item'>
          <p>" . htmlspecialchars($n['message']) . "</p>
          <p class='notif-time'>" . htmlspecialchars($n['created_at']) . "</p>
        </div>";
}
