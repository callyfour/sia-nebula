<?php
session_start();
require_once '../vendor/autoload.php';
require "db.php"; // your database connection

$client = new Google\Client(); // use $client consistently
$client->setClientId('60704026509-0tbhbko4smo0gendjlbv974dvcc5onkh.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/nebula/php/redirect.php');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    // Fetch token using the authorization code
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        die('Google authentication error: ' . htmlspecialchars($token['error']));
    }

    $client->setAccessToken($token['access_token']);

    // Get user info
    $oauth2 = new Google\Service\Oauth2($client);
    $googleUser = $oauth2->userinfo->get();

    $email = $googleUser->email;
    $name = $googleUser->name;

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, '', 'user')");
        $stmt->execute([$name, $email]);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $user['id'];
    }

    // Save session
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
    ];

    header("Location: index.php");
    exit;
} else {
    die('No code parameter found in URL.');
}
?>
