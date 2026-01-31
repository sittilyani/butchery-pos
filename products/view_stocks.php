<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'stock_movements'");
if ($table_check->num_rows == 0) {
    // Create stock_movements table based on your structure
    $create_table = "CREATE TABLE stock_movements (
        trans_id INT PRIMARY KEY AUTO_INCREMENT,
        transactionType VARCHAR(100) NOT NULL DEFAULT 'Receiving',
        name VARCHAR(100) NOT NULL,
        opening_bal DOUBLE DEFAULT 0,
        qty_in DOUBLE NOT NULL,
        received_from VARCHAR(100) NOT NULL,
        qty_out DOUBLE DEFAULT 0,
        batch_number VARCHAR(50),
        expiry_date DATE,
        received_by VARCHAR(100) NOT NULL,
        total_qty DOUBLE,
        trans_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table);
}

// Fetch stock movements
$stock_movements = [];
$query = "SELECT * FROM stock_movements ORDER BY trans_date DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stock_movements[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Stock Movements - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .table-container { margin: 30px auto; max-width: 1400px; }
        .btn-add { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Stock Movements</h3>
                <div>
                    <a href="add_stocks.php" class="btn btn-primary">Add New Stock</a>
                    <a href="index.php" class="btn btn-secondary">Back to Products</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Opening Bal</th>
                            <th>Qty In</th>
                            <th>Qty Out</th>
                            <th>Total Qty</th>
                            <th>Received From</th>
                            <th>Batch No.</th>
                            <th>Expiry Date</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock_movements)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No stock movements found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($stock_movements as $movement): ?>
                                <tr>
                                    <td><?= $movement['trans_id'] ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($movement['trans_date'])) ?></td>
                                    <td><?= htmlspecialchars($movement['name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $movement['transactionType'] == 'Receiving' ? 'success' : 'danger' ?>">
                                            <?= htmlspecialchars($movement['transactionType']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($movement['opening_bal'], 2) ?> kg</td>
                                    <td class="text-success">
                                        <?= $movement['qty_in'] > 0 ? '+' . number_format($movement['qty_in'], 2) . ' kg' : '-' ?>
                                    </td>
                                    <td class="text-danger">
                                        <?= $movement['qty_out'] > 0 ? '-' . number_format($movement['qty_out'], 2) . ' kg' : '-' ?>
                                    </td>
                                    <td><strong><?= number_format($movement['total_qty'], 2) ?> kg</strong></td>
                                    <td><?= htmlspecialchars($movement['received_from']) ?></td>
                                    <td><?= htmlspecialchars($movement['batch_number'] ?? '-') ?></td>
                                    <td><?= $movement['expiry_date'] ? date('Y-m-d', strtotime($movement['expiry_date'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($movement['received_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>