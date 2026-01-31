<?php
session_start();
require_once '../includes/config.php';

$id = (int)($_GET['id'] ?? 0);
$result = $conn->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category = c.name
    WHERE p.id = $id
");
$product = $result->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found!";
    header("Location: index.php");
    exit;
}

// Fetch categories for dropdown
$categories_result = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.css" type="text/css">
    <style>
        .card { max-width: 1000px; margin: 50px auto; }
    </style>
    <script>
        function calculateProfit() {
            var buying = parseFloat(document.getElementById('buying_price').value) || 0;
            var selling = parseFloat(document.getElementById('selling_price').value) || 0;
            var profit = selling - buying;
            document.getElementById('profit_display').textContent = 'KES' + profit.toFixed(2);
        }
    </script>
</head>
<body class="bg-light">

<div class="container">
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h4>Edit Product: <?= htmlspecialchars($product['name']) ?></h4>
        </div>
        <div class="card-body">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="process.php" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $product['id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id'] == $product['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Buying Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="buying_price" id="buying_price" class="form-control"
                                   value="<?= $product['buying_price'] ?>" step="0.01" min="0" required onkeyup="calculateProfit()">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="selling_price" id="selling_price" class="form-control"
                                   value="<?= $product['selling_price'] ?>" step="0.01" min="0" required onkeyup="calculateProfit()">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Current Profit (Auto-calculated)</label>
                    <div class="alert alert-info">
                        Current profit: <strong><?= number_format($product['profit'], 2) ?></strong>
                    </div>
                    <div class="alert alert-warning">
                        <small>New profit after changes: <strong><span id="profit_display">KES <?= number_format($product['profit'], 2) ?></span></strong></small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Reorder Level <span class="text-danger">*</span></label>
                    <input type="number" name="reorder_level" class="form-control"
                           value="<?= $product['reorder_level'] ?>" min="1" required>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2">Update Product</button>
            </form>
        </div>
    </div>
</div>

<script>
// Calculate initial profit on page load
window.onload = function() {
    calculateProfit();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>