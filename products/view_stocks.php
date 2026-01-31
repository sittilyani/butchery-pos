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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch unique products with their latest stock
$products = [];
if (!empty($search)) {
    $query = "SELECT DISTINCT name FROM stock_movements WHERE name LIKE ? ORDER BY name";
    $stmt = $conn->prepare($query);
    $search_param = "%" . $search . "%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT DISTINCT name FROM stock_movements ORDER BY name";
    $result = $conn->query($query);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $product_name = $row['name'];

        $latest_query = "SELECT * FROM stock_movements WHERE name = ? ORDER BY trans_date DESC LIMIT 1";
        $stmt2 = $conn->prepare($latest_query);
        $stmt2->bind_param("s", $product_name);
        $stmt2->execute();
        $latest_result = $stmt2->get_result();

        if ($latest_result->num_rows > 0) {
            $product_data = $latest_result->fetch_assoc();

            $count_query = "SELECT COUNT(*) as total_transactions FROM stock_movements WHERE name = ?";
            $stmt3 = $conn->prepare($count_query);
            $stmt3->bind_param("s", $product_name);
            $stmt3->execute();
            $count_result = $stmt3->get_result();
            $count_data = $count_result->fetch_assoc();

            $product_data['total_transactions'] = $count_data['total_transactions'];
            $products[] = $product_data;
        }
    }
}

// Get all product names for autocomplete
$all_products_query = "SELECT DISTINCT name FROM stock_movements ORDER BY name";
$all_products_result = $conn->query($all_products_query);
$all_products = [];
if ($all_products_result) {
    while ($row = $all_products_result->fetch_assoc()) {
        $all_products[] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Summary - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container{margin:30px auto;max-width:1400px}
        .search-box{max-width:400px;margin-bottom:20px}
        .stock-table{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .table{margin:0}
        .table thead{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
        .table thead th{padding:15px;font-weight:600;border:none;text-align:center}
        .table tbody td{padding:12px;vertical-align:middle;text-align:center}
        .table tbody tr:hover{background:#f8f9fa}
        .badge-stock{padding:6px 12px;border-radius:20px;font-weight:600;font-size:.85em;display:inline-block}
        .badge-out{background:#f8d7da;color:#721c24}
        .badge-low{background:#fff3cd;color:#856404}
        .badge-good{background:#d4edda;color:#155724}
        .product-name{font-weight:600;color:#2c3e50;text-align:left}
        .btn-sm{padding:4px 10px;font-size:.85em}
        .expiry-warning{color:#856404;font-size:.85em}
        .expiry-danger{color:#721c24;font-size:.85em}
        .action-btns{display:flex;gap:5px;justify-content:center}
        .header-section{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
        .btn-group-header{display:flex;gap:10px;flex-wrap:wrap}
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="table-container">
            <div class="header-section">
                <h3><i class="fas fa-boxes me-2"></i>Stock Summary</h3>
                <div class="btn-group-header">
                    <a href="add_stocks.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Stock</a>
                    <a href="view_stock_movements.php" class="btn btn-info"><i class="fas fa-list me-1"></i>All Movements</a>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="search-box">
                <form method="GET" action="" class="d-flex">
                    <input type="text" name="search" class="form-control me-2"
                           placeholder="Search product by name..."
                           value="<?= htmlspecialchars($search) ?>"
                           id="product-search"
                           list="product-names">
                    <datalist id="product-names">
                        <?php foreach($all_products as $prod_name): ?>
                            <option value="<?= htmlspecialchars($prod_name) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if(!empty($search)): ?>
                        <a href="view_stocks.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if(!empty($search)): ?>
                        No products found matching "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        No stock data found. <a href="add_stocks.php">Add some stock</a> to get started.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="stock-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:5%">#</th>
                                <th style="width:25%;text-align:left">Product Name</th>
                                <th style="width:12%">Current Stock</th>
                                <th style="width:10%">Status</th>
                                <th style="width:10%">Reorder Level</th>
                                <th style="width:12%">Batch Number</th>
                                <th style="width:10%">Expiry Date</th>
                                <th style="width:10%">Transactions</th>
                                <th style="width:15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $counter = 1;
                            foreach($products as $product):
                                $total_qty = (float)$product['total_qty'];
                                $stock_class = 'badge-good';
                                $stock_text = 'Good';

                                // Get reorder level from products table
                                $reorder_level = 10;
                                $product_info_query = $conn->prepare("SELECT reorder_level FROM products WHERE name = ? LIMIT 1");
                                $product_info_query->bind_param("s", $product['name']);
                                $product_info_query->execute();
                                $product_info_result = $product_info_query->get_result();
                                if ($product_info_result->num_rows > 0) {
                                    $product_info = $product_info_result->fetch_assoc();
                                    $reorder_level = (float)$product_info['reorder_level'];
                                }

                                if ($total_qty <= 0) {
                                    $stock_class = 'badge-out';
                                    $stock_text = 'Out of Stock';
                                } elseif ($total_qty <= $reorder_level) {
                                    $stock_class = 'badge-low';
                                    $stock_text = 'Low Stock';
                                }
                            ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td class="product-name"><?= htmlspecialchars($product['name']) ?></td>
                                <td><strong><?= number_format($total_qty, 2) ?></strong> kg</td>
                                <td><span class="badge-stock <?= $stock_class ?>"><?= $stock_text ?></span></td>
                                <td><?= number_format($reorder_level, 2) ?> kg</td>
                                <td><?= !empty($product['batch_number']) ? htmlspecialchars($product['batch_number']) : '<span class="text-muted">-</span>' ?></td>
                                <td>
                                    <?php if(!empty($product['expiry_date'])):
                                        $expiry_days = floor((strtotime($product['expiry_date']) - time()) / (60 * 60 * 24));
                                    ?>
                                        <?= date('Y-m-d', strtotime($product['expiry_date'])) ?><br>
                                        <?php if ($expiry_days <= 30 && $expiry_days > 0): ?>
                                            <small class="expiry-warning">(<?= $expiry_days ?> days)</small>
                                        <?php elseif ($expiry_days <= 0): ?>
                                            <small class="expiry-danger">(Expired)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary"><?= $product['total_transactions'] ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="stock_card.php?product=<?= urlencode($product['name']) ?>"
                                           class="btn btn-sm btn-info" title="View Stock Card">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="add_stocks.php?product=<?= urlencode($product['name']) ?>"
                                           class="btn btn-sm btn-success" title="Add Stock">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-muted">
                    <small><i class="fas fa-info-circle me-1"></i>Showing <?= count($products) ?> product(s)</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded',function(){
            const searchInput=document.getElementById('product-search');
            if(searchInput){searchInput.focus();}
        });
    </script>
</body>
</html>