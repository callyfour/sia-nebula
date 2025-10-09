<?php
session_start();
require "../php/db.php";

// ✅ Only admin can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}

$msg = null;
$msgType = 'error'; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $brand = trim($_POST['brand']);
    $description = trim($_POST['description']);

    // Validation
    if (empty($name) || empty($brand) || $price <= 0) {
        $msg = "Please fill in all required fields with valid data (price must be greater than 0).";
    } elseif (!empty($_FILES['image']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Create folder if not exists (consider security in production)
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Allow certain formats and check size (e.g., max 5MB)
        $allowed = ["jpg", "jpeg", "png", "gif", "webp"];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (!in_array($imageFileType, $allowed)) {
            $msg = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
        } elseif ($_FILES["image"]["size"] > $maxSize) {
            $msg = "Image file is too large (max 5MB).";
        } else {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                // Insert into database
                $stmt = $pdo->prepare("INSERT INTO products (name, price, brand, description, image) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $price, $brand, $description, $fileName])) {
                    $msgType = 'success';
                    $msg = "Product added successfully!";
                    header("Location: admin.php?msg=Product+added");
                    exit;
                } else {
                    $msg = "Error saving product to database.";
                }
            } else {
                $msg = "Error uploading image. Please try again.";
            }
        }
    } else {
        $msg = "Please upload a product image.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Panel</title>
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

        /* Custom File Upload */
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

        /* Image Preview */
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
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar (Modern) -->
        <aside class="sidebar">
            <div class="logo">
                <i class='bx bxs-plus-circle'></i>
                <span>Add Product</span>
            </div>
            <nav>
                <a href="admin.php"><i class='bx bxs-dashboard'></i> Back to Dashboard</a>
                <a href="../php/logout.php"><i class='bx bxs-log-out'></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <h1><i class='bx bxs-box'></i> Add New Product</h1>
            
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
                    <input type="text" id="name" name="name" placeholder="Enter product name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="price"><i class='bx bx-dollar'></i> Price (₱)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" placeholder="0.00" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="brand"><i class='bx bx-store'></i> Brand</label>
                    <input type="text" id="brand" name="brand" placeholder="Enter brand name" required value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="description"><i class='bx bx-text'></i> Description</label>
                    <textarea id="description" name="description" placeholder="Enter product description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Upload Image</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="image" name="image" class="file-upload-input" accept="image/*" required onchange="previewImage(event)">
                        <label for="image" class="file-upload-label">
                            <i class='bx bx-cloud-upload'></i>
                            Choose Image (JPG, PNG, GIF, WEBP - Max 5MB)
                        </label>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class='bx bx-plus'></i> Add Product
                </button>
            </form>
        </main>
    </div>

    <script>
        // ✅ Image Preview on Upload
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
            }
            const image = document.getElementById('image').files[0];
            if (!image) {
                e.preventDefault();
                alert('Please upload an image.');
            }
        });
    </script>
</body>
</html>
