<?php
session_start();
require_once '../includes/config.php';

$result = $conn->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category = c.name
    ORDER BY p.name
");
$products = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.css" type="text/css">
    <style>
        .disabled-row { background-color: #f8d7da; opacity: 0.8; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between mb-4">
        <h2>Products</h2>
        <a href="add.php" class="btn btn-primary">+ New Product</a>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Buying Price</th>
                    <th>Selling Price</th>
                    <th>Profit</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($products)): ?>
                <tr><td colspan="11" class="text-center py-4">No products added yet.</td></tr>
            <?php else: ?>
                <?php foreach($products as $i => $prod): ?>
                <tr class="<?= $prod['is_active'] ? '' : 'disabled-row' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($prod['name']) ?></td>
                    <td><?= htmlspecialchars($prod['category_name']) ?></td>
                    <td><?= number_format($prod['buying_price'], 2) ?></td>
                    <td><?= number_format($prod['selling_price'], 2) ?></td>
                    <td><?= number_format($prod['profit'], 2) ?></td>
                    <td><?= $prod['reorder_level'] ?></td>
                    <td>
                        <span class="badge <?= $prod['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $prod['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($prod['created_at'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($prod['updated_at'])) ?></td>
                    <td>
                        <a href="edit.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="process.php?action=toggle_status&id=<?= $prod['id'] ?>"
                           class="btn btn-sm <?= $prod['is_active'] ? 'btn-secondary' : 'btn-success' ?>"
                           onclick="return confirm('Are you sure?')">
                            <?= $prod['is_active'] ? 'Disable' : 'Enable' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>