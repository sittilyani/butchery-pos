<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all stock movements with search
$query = "SELECT * FROM stock_movements ";
if (!empty($search)) {
    $query .= "WHERE name LIKE ? ";
}
$query .= "ORDER BY trans_date DESC";

$stmt = null;
if (!empty($search)) {
    $stmt = $conn->prepare($query);
    $search_param = "%" . $search . "%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$stock_movements = [];
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
    <title>All Stock Movements - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .table-container { margin: 30px auto; max-width: 1400px; }
        .search-box { max-width: 400px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>All Stock Movements</h3>
                <div>
                    <a href="view_stocks.php" class="btn btn-info">Stock Summary</a>
                    <a href="add_stocks.php" class="btn btn-primary">Add New Stock</a>
                    <a href="index.php" class="btn btn-secondary">Back to Products</a>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <form method="GET" action="" class="d-flex">
                    <input type="text" name="search" class="form-control me-2"
                           placeholder="Search by product name..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if(!empty($search)): ?>
                        <a href="view_stock_movements.php" class="btn btn-outline-secondary ms-2">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if(empty($stock_movements)): ?>
                <div class="alert alert-info">
                    <?php if(!empty($search)): ?>
                        No stock movements found matching "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        No stock movements found.
                    <?php endif; ?>
                </div>
            <?php else: ?>
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
                            <?php foreach($stock_movements as $movement): ?>
                                <tr>
                                    <td><?= $movement['trans_id'] ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($movement['trans_date'])) ?></td>
                                    <td>
                                        <a href="stock_card.php?product=<?= urlencode($movement['name']) ?>"
                                           class="text-decoration-none">
                                            <?= htmlspecialchars($movement['name']) ?>
                                        </a>
                                    </td>
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
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>