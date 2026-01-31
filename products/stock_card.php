<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
}

// Get product name from URL
$product_name = isset($_GET['product']) ? urldecode($_GET['product']) : '';

if (empty($product_name)) {
        $_SESSION['error'] = "No product specified.";
        header("Location: view_stocks.php");
        exit;
}

// Fetch all transactions for this product
$transactions = [];
$query = "SELECT * FROM stock_movements
                    WHERE name = ?
                    ORDER BY trans_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $product_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
        $_SESSION['error'] = "No stock movements found for product: " . htmlspecialchars($product_name);
        header("Location: view_stocks.php");
        exit;
}

while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Stock Card - <?= htmlspecialchars($product_name) ?></title>
        <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
        <style>
                .stock-card-container {
                        max-width: 1400px;
                        margin: 30px auto;
                }
                .stock-card-header {
                        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 8px 8px 0 0;
                        margin-bottom: 0;
                }
                .bin-card {
                        border: 2px solid #dee2e6;
                        border-radius: 8px;
                        margin-bottom: 30px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .table-responsive {
                        border-radius: 0 0 8px 8px;
                        overflow: hidden;
                }
                .transaction-type {
                        font-weight: bold;
                }
                .transaction-in {
                        color: #28a745;
                }
                .transaction-out {
                        color: #dc3545;
                }
                .btn-print {
                        margin-top: 20px;
                }
        </style>
</head>
<body>
        <div class="container-fluid">
                <div class="stock-card-container">
                        <!-- Bin Card Header -->
                        <div class="bin-card">
                                <div class="stock-card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                        <h2 class="mb-1">Stock Card / Bin Card</h2>
                                                        <h3 class="mb-0"><?= htmlspecialchars($product_name) ?></h3>
                                                </div>
                                                <div class="text-end">
                                                        <div>Total Transactions: <strong><?= count($transactions) ?></strong></div>
                                                        <div>Current Stock:
                                                                <strong><?= number_format($transactions[0]['total_qty'], 2) ?> kg</strong>
                                                        </div>
                                                </div>
                                        </div>
                                </div>

                                <!-- Transactions Table -->
                                <div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0">
                                                <thead class="table-dark">
                                                        <tr>
                                                                <th>#</th>
                                                                <th>Date & Time</th>
                                                                <th>Transaction Type</th>
                                                                <th>Opening Balance</th>
                                                                <th>Quantity In</th>
                                                                <th>Quantity Out</th>
                                                                <th>Closing Balance</th>
                                                                <th>Received From</th>
                                                                <th>Batch No.</th>
                                                                <th>Expiry Date</th>
                                                                <th>Received By</th>
                                                        </tr>
                                                </thead>
                                                <tbody>
                                                        <?php foreach($transactions as $index => $movement):
                                                                $is_receiving = $movement['transactionType'] == 'Receiving';
                                                        ?>
                                                        <tr>
                                                                <td><?= $index + 1 ?></td>
                                                                <td><?= date('Y-m-d H:i', strtotime($movement['trans_date'])) ?></td>
                                                                <td>
                                                                        <span class="transaction-type <?= $is_receiving ? 'transaction-in' : 'transaction-out' ?>">
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
                                                                <td>
                                                                        <?php if(!empty($movement['expiry_date'])):
                                                                                $expiry_date = date('Y-m-d', strtotime($movement['expiry_date']));
                                                                                $expiry_days = floor((strtotime($movement['expiry_date']) - time()) / (60 * 60 * 24));
                                                                        ?>
                                                                                <?= $expiry_date ?>
                                                                                <?php if ($expiry_days <= 30 && $expiry_days > 0): ?>
                                                                                        <br><small class="text-warning">(<?= $expiry_days ?> days)</small>
                                                                                <?php elseif ($expiry_days <= 0): ?>
                                                                                        <br><small class="text-danger">(Expired)</small>
                                                                                <?php endif; ?>
                                                                        <?php else: ?>
                                                                                -
                                                                        <?php endif; ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($movement['received_by']) ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-light">
                                                        <tr>
                                                                <td colspan="11" class="text-center">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                                <div>
                                                                                        Generated on: <?= date('Y-m-d H:i:s') ?>
                                                                                </div>
                                                                                <div>
                                                                                        Prepared by: <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?>
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                        </tr>
                                                </tfoot>
                                        </table>
                                </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                                <div>
                                        <a href="view_stocks.php" class="btn btn-secondary">Back to Stock Summary</a>
                                        <a href="add_stocks.php?product=<?= urlencode($product_name) ?>" class="btn btn-success">
                                                Add New Stock
                                        </a>
                                </div>
                                <div>
                                        <button onclick="window.print()" class="btn btn-primary">
                                                <i class="bi bi-printer"></i> Print Stock Card
                                        </button>
                                </div>
                        </div>
                </div>
        </div>

        <script src="../assets/js/bootstrap.bundle.min.js"></script>
        <style media="print">
                @media print {
                        .btn, .stock-card-header {
                                display: none !important;
                        }
                        .bin-card {
                                border: none !important;
                                box-shadow: none !important;
                        }
                        body {
                                font-size: 12px;
                        }
                        table {
                                font-size: 10px;
                        }
                }
        </style>
</body>
</html>