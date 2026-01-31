<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Get logged in user's full name
$user_id = $_SESSION['user_id'] ?? 0;
$full_name = "Unknown User";
if ($user_id > 0) {
    $user_query = $conn->prepare("SELECT full_name FROM tblusers WHERE user_id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $full_name = $user_row['full_name'];
    }
}

// Fetch active products for dropdown
$products = [];
$product_query = "SELECT p.id, p.name, p.category, c.name as category_name
                  FROM products p
                  LEFT JOIN categories c ON p.category = c.name
                  WHERE p.is_active = 1
                  ORDER BY p.name";
$product_result = $conn->query($product_query);
if ($product_result) {
    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stock') {
    $product_id = (int)$_POST['product_id'];
    $transactionType = 'Receiving'; // Default for stock addition
    $qty_in = floatval($_POST['qty_in']);
    $received_from = trim($_POST['received_from']);
    $batch_number = trim($_POST['batch_number']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    // Validate
    if ($product_id <= 0 || $qty_in <= 0) {
        $_SESSION['error'] = "Please select a product and enter valid quantity.";
        header("Location: add_stocks.php");
        exit;
    }

    // Get product name
    $product_name = "";
    foreach ($products as $product) {
        if ($product['id'] == $product_id) {
            $product_name = $product['name'];
            break;
        }
    }

    if (empty($product_name)) {
        $_SESSION['error'] = "Invalid product selected.";
        header("Location: add_stocks.php");
        exit;
    }

    // Calculate opening balance (get current stock if exists)
    $opening_bal = 0;
    $stock_check = $conn->query("SHOW TABLES LIKE 'stock_movements'");
    if ($stock_check->num_rows > 0) {
        $latest_stock = $conn->query("SELECT total_qty FROM stock_movements
                                     WHERE name = '" . $conn->real_escape_string($product_name) . "'
                                     ORDER BY trans_date DESC LIMIT 1");
        if ($latest_stock && $latest_stock->num_rows > 0) {
            $stock_row = $latest_stock->fetch_assoc();
            $opening_bal = (float)$stock_row['total_qty'];
        }
    }

    $total_qty = $opening_bal + $qty_in;

    // Insert into stock_movements
    $stmt = $conn->prepare("INSERT INTO stock_movements
                           (transactionType, name, opening_bal, qty_in, received_from, batch_number, expiry_date, received_by, total_qty)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssidssssd",
        $transactionType,
        $product_name,
        $opening_bal,
        $qty_in,
        $received_from,
        $batch_number,
        $expiry_date,
        $full_name,
        $total_qty
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Stock added successfully! Transaction ID: " . $conn->insert_id;
        header("Location: add_stocks.php");
        exit;
    } else {
        $_SESSION['error'] = "Error adding stock: " . $conn->error;
        header("Location: add_stocks.php");
        exit;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Stock - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .card { max-width: 800px; margin: 50px auto; }
        .form-label { font-weight: bold; }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Add New Stock</h4>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <input type="hidden" name="action" value="add_stock">

                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php foreach($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['category_name'] ?? $product['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantity Received (kg) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="qty_in" class="form-control" step="0.1" min="0.1" required>
                            <span class="input-group-text">kg</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Received From (Supplier/Customer) <span class="text-danger">*</span></label>
                        <input type="text" name="received_from" class="form-control" required placeholder="e.g., Supplier Name, Customer Return">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch/Lot Number (Optional)</label>
                            <input type="text" name="batch_number" class="form-control" placeholder="e.g., BATCH-001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date (Optional)</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Received By</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($full_name) ?>" readonly>
                        <small class="text-muted">Automatically logged as the current user</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <a href="index.php" class="btn btn-secondary w-100 py-2">Back to Products</a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success w-100 py-2">Add Stock</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date to today for expiry date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const expiryInput = document.querySelector('input[name="expiry_date"]');
            if (expiryInput) {
                expiryInput.min = today;
            }
        });
    </script>
</body>
</html>