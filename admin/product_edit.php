<?php
session_start();
require "../php/db.php";

// ✅ Only admin can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

// ✅ Validate product ID
if (!isset($_GET['id'])) {
    header("Location: admin.php?msg=No+product+ID");
    exit;
}

$id = (int)$_GET['id'];

// ✅ Fetch existing product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: admin.php?msg=Product+not+found");
    exit;
}

$msg = null;
$msgType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $brand = trim($_POST['brand']);
    $description = trim($_POST['description']);
    $stock = intval($_POST['stock']);
    $image = $product['image']; // keep old image by default

    // ✅ Basic validation
    if (empty($name) || empty($brand) || $price <= 0 || $stock < 0) {
        $msg = "Please fill in all required fields with valid data (price > 0, stock ≥ 0).";
    } else {
        // ✅ Handle image upload if new one is selected
        if (!empty($_FILES['image']['name'])) {
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $fileName = time() . "_" . basename($_FILES["image"]["name"]);
            $targetFile = $targetDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            $allowed = ["jpg", "jpeg", "png", "gif", "webp"];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($imageFileType, $allowed)) {
                $msg = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
            } elseif ($_FILES["image"]["size"] > $maxSize) {
                $msg = "Image file is too large (max 5MB).";
            } else {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    // Delete old image if exists
                    if (!empty($product['image']) && file_exists("../uploads/" . $product['image'])) {
                        unlink("../uploads/" . $product['image']);
                    }
                    $image = $fileName;
                } else {
                    $msg = "Error uploading image. Please try again.";
                }
            }
        }

        if (!$msg) {
            // ✅ Get old stock before updating
            $oldStock = (int)$product['stock'];
            $newStock = (int)$stock;

            // ✅ Update product
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name=?, price=?, brand=?, description=?, image=?, stock=? 
                WHERE id=?
            ");
            if ($stmt->execute([$name, $price, $brand, $description, $image, $newStock, $id])) {

                // ✅ Detect Stock Change → Send Notifications
                if ($oldStock != $newStock) {
                    $notifMessage = null;

                    if ($oldStock > 0 && $newStock <= 0) {
                        // ⚠️ Product just went OUT OF STOCK
                        $notifMessage = "⚠️ '{$name}' is now out of stock.";
                    } elseif ($oldStock <= 0 && $newStock > 0) {
                        // ✅ Product just came BACK IN STOCK
                        $notifMessage = "✅ '{$name}' is now back in stock!";
                    }

                    if ($notifMessage) {
                        // Send notifications to all wishlisted users
                        $wishlistUsers = $pdo->prepare("SELECT user_id FROM wishlist WHERE product_id = ?");
                        $wishlistUsers->execute([$id]);
                        $users = $wishlistUsers->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($users as $u) {
                            $insertNotif = $pdo->prepare("
                                INSERT INTO notifications (user_id, product_id, message)
                                VALUES (?, ?, ?)
                            ");
                            $insertNotif->execute([$u['user_id'], $id, $notifMessage]);
                        }
                    }
                }

                if ($newStock <= 0) {
                $wishlistUsers = $pdo->prepare("SELECT user_id FROM wishlist WHERE product_id = ?");
                $wishlistUsers->execute([$productId]);
                $users = $wishlistUsers->fetchAll(PDO::FETCH_COLUMN);

                foreach ($users as $uid) {
                    $check = $pdo->prepare("
                        SELECT COUNT(*) FROM notifications 
                        WHERE user_id = ? AND message LIKE ? 
                        AND created_at >= NOW() - INTERVAL 1 DAY
                    ");
                    $check->execute([$uid, "%{$productName}%"]);
                    $alreadyNotified = $check->fetchColumn();

                    if ($alreadyNotified == 0) {
                        $msg = "The product '{$productName}' you wishlisted is now out of stock.";
                        $insertNotif = $pdo->prepare("
                            INSERT INTO notifications (user_id, message) VALUES (?, ?)
                        ");
                        $insertNotif->execute([$uid, $msg]);
                    }
                }
            }



                $msgType = 'success';
                $msg = "Product updated successfully!";
                header("Location: products.php?msg=Product+updated");
                exit;
            } else {
                $msg = "Error updating product in database.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin Panel</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Form Styles (Integrated with Red/Black Theme) */
        :root {
            --form-max-width: 600px;
            --input-padding: 14px 18px;
            --input-border: 2px solid var(--border);
            --input-focus: var(--accent-red);
            --label-color: var(--text-primary);
            --error-color: #ff5252;
            --success-color: #4caf50;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar (Modern, matching admin.php) */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            padding: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            box-shadow: 4px 0 20px var(--shadow);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            color: var(--accent-red);
            margin: 30px 0;
            padding: 20px;
            background: rgba(255, 23, 68, 0.05);
            border-bottom: 1px solid var(--border);
        }

        .sidebar .logo i {
            font-size: 28px;
            color: var(--accent-red);
        }

        .sidebar nav {
            flex: 1;
            padding: 10px 0;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }

        .sidebar nav a:hover {
            background: rgba(255, 23, 68, 0.1);
            color: var(--accent-red);
            border-left-color: var(--accent-red);
            transform: translateX(4px);
        }

        /* Main Content */
        .content {
            flex: 1;
            padding: 40px;
            background: var(--bg-primary);
            overflow-y: auto;
        }

        .content h1 {
            color: var(--text-primary);
            margin: 0 0 24px 0;
            font-size: 2.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content h1 i {
            color: var(--accent-red);
        }

        /* Message Alerts */
        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px var(--shadow);
        }

        .alert-error {
            background: rgba(255, 82, 82, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .alert i {
            font-size: 20px;
        }

        /* Form Styles */
        .product-form {
            max-width: var(--form-max-width);
            background: var(--bg-card);
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 8px 32px var(--shadow);
            border: 1px solid var(--border);
        }

        .product-form .form-group {
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
        }

        .product-form label {
            font-weight: 600;
            color: var(--label-color);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-form input,
        .product-form textarea,
        .product-form select {
            padding: var(--input-padding);
            background: var(--bg-secondary);
            border: var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .product-form input:focus,
        .product-form textarea:focus,
        .product-form select:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(255, 23, 68, 0.1);
            transform: translateY(-1px);
        }

        .product-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Current Image Display */
        .current-image-section {
            margin-bottom: 24px;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .current-image-section h3 {
            color: var(--text-primary);
            margin: 0 0 12px 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-image {
            text-align: center;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow);
            border: 1px solid var(--border);
        }

        .no-image {
            color: var(--text-secondary);
            font-style: italic;
        }

        /* Custom File Upload (Optional New Image) */
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
            margin-bottom: 8px;
        }

        .file-upload-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: var(--input-padding);
            background: var(--bg-secondary);
            border: var(--input-border);
            border-radius: 8px;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 16px;
            justify-content: center;
        }

        .file-upload-label:hover {
            border-color: var(--input-focus);
            background: rgba(255, 23, 68, 0.05);
            color: var(--accent-red);
        }

        .file-upload-label i {
            font-size: 20px;
            color: var(--accent-red);
        }

        /* Image Preview (New Image) */
        .image-preview {
            margin-top: 12px;
            text-align: center;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow);
            border: 1px solid var(--border);
        }

        /* Submit Button */
        .product-form .btn {
            background: linear-gradient(135deg, var(--accent-red) 0%, var(--dark-red) 100%);
            color: var(--text-primary);
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            width: 100%;
        }

        .product-form .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--shadow-hover);
        }

        .product-form .btn:active {
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .admin-layout {
                flex-direction: column;
            }
            .content {
                padding: 20px;
            }
            .product-form {
                padding: 24px;
            }
            .current-image img,
            .image-preview img {
                max-width: 150px;
                max-height: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar (Modern) -->
        <aside class="sidebar">
            <div class="logo">
                <i class='bx bxs-edit-alt'></i>
                <span>Edit Product</span>
            </div>
            <nav>
                <a href="admin.php"><i class='bx bxs-dashboard'></i> Back to Dashboard</a>
                <a href="../php/logout.php"><i class='bx bxs-log-out'></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <h1><i class='bx bxs-edit'></i> Edit Product: <?= htmlspecialchars($product['name']) ?></h1>
            
            <?php if ($msg): ?>
                <div class="alert <?= $msgType === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?php if ($msgType === 'success'): ?>
                        <i class='bx bx-check-circle'></i>
                    <?php else: ?>
                        <i class='bx bx-error'></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="product-form">
                <div class="form-group">
                    <label for="name"><i class='bx bx-tag'></i> Product Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter product name" required value="<?= htmlspecialchars($_POST['name'] ?? $product['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="price"><i class='bx bx-dollar'></i> Price (₱)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" placeholder="0.00" required value="<?= htmlspecialchars($_POST['price'] ?? $product['price']) ?>">
                </div>

                <div class="form-group">
                    <label for="stock"><i class='bx bx-cube'></i> Stock</label>
                    <input type="number" id="stock" name="stock" min="0" step="1" required
                        value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock'] ?? 0) ?>">
                </div>


                <div class="form-group">
                    <label for="brand"><i class='bx bx-store'></i> Brand</label>
                    <input type="text" id="brand" name="brand" placeholder="Enter brand name" required value="<?= htmlspecialchars($_POST['brand'] ?? $product['brand']) ?>">
                </div>

                <div class="form-group">
                    <label for="description"><i class='bx bx-text'></i> Description</label>
                    <textarea id="description" name="description" placeholder="Enter product description" rows="4"><?= htmlspecialchars($_POST['description'] ?? $product['description']) ?></textarea>
                </div>

                <!-- Current Image Section -->
                <div class="current-image-section">
                    <h3><i class='bx bx-image'></i> Current Image</h3>
                    <div class="current-image">
                        <?php if ($product['image'] && file_exists("../uploads/" . $product['image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($product['image']) ?>" alt="Current Product Image">
                        <?php else: ?>
                            <p class="no-image">No image uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Upload New Image (optional - will replace current)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="image" name="image" class="file-upload-input" accept="image/*" onchange="previewImage(event)">
                        <label for="image" class="file-upload-label">
                            <i class='bx bx-cloud-upload'></i>
                            Choose New Image (JPG, PNG, GIF, WEBP - Max 5MB)
                        </label>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="New Image Preview">
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">This will replace the current image.</p>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class='bx bx-save'></i> Update Product
                </button>
            </form>
        </main>
    </div>

    <script>
        // ✅ Image Preview on Upload (New Image)
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        // ✅ Form Validation Enhancement (Client-Side)
        document.querySelector('.product-form').addEventListener('submit', function(e) {
            const price = document.getElementById('price').value;
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0.');
                return;
            }
        });
    </script>
</body>
</html>
