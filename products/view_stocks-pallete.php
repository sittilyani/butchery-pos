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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch unique products with their latest stock
$products = [];
if (!empty($search)) {
    // Search by product name
    $query = "SELECT DISTINCT name FROM stock_movements
              WHERE name LIKE ?
              ORDER BY name";
    $stmt = $conn->prepare($query);
    $search_param = "%" . $search . "%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all unique products
    $query = "SELECT DISTINCT name FROM stock_movements ORDER BY name";
    $result = $conn->query($query);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $product_name = $row['name'];

        // Get latest transaction for this product
        $latest_query = "SELECT * FROM stock_movements
                         WHERE name = ?
                         ORDER BY trans_date DESC
                         LIMIT 1";
        $stmt2 = $conn->prepare($latest_query);
        $stmt2->bind_param("s", $product_name);
        $stmt2->execute();
        $latest_result = $stmt2->get_result();

        if ($latest_result->num_rows > 0) {
            $product_data = $latest_result->fetch_assoc();

            // Get total transactions count for this product
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
    <style>
        .table-container { margin: 30px auto; max-width: 1400px; }
        .btn-add { margin-bottom: 20px; }
        .stock-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .stock-card:hover {
            background: #e9ecef;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .product-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stock-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .stock-low { background-color: #f8d7da; color: #721c24; }
        .stock-medium { background-color: #fff3cd; color: #856404; }
        .stock-high { background-color: #d4edda; color: #155724; }
        .stock-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .detail-item {
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        .detail-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 3px;
        }
        .detail-value {
            font-weight: bold;
            color: #495057;
        }
        .search-box {
            max-width: 400px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Stock Summary</h3>
                <div>
                    <a href="add_stocks.php" class="btn btn-primary">Add New Stock</a>
                    <a href="view_stock_movements.php" class="btn btn-info">View All Movements</a>
                    <a href="index.php" class="btn btn-secondary">Back to Products</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search Box -->
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
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if(!empty($search)): ?>
                        <a href="view_stocks.php" class="btn btn-outline-secondary ms-2">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Stock Summary -->
            <div class="row">
                <?php if (empty($products)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <?php if(!empty($search)): ?>
                                No products found matching "<?= htmlspecialchars($search) ?>"
                            <?php else: ?>
                                No stock data found. <a href="add_stocks.php">Add some stock</a> to get started.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($products as $product):
                        // Determine stock status
                        $total_qty = (float)$product['total_qty'];
                        $stock_class = 'stock-high';
                        $stock_text = 'Good';

                        // Get reorder level from products table if exists
                        $reorder_level = 10; // default
                        $product_info_query = $conn->prepare("SELECT reorder_level FROM products WHERE name = ? LIMIT 1");
                        $product_info_query->bind_param("s", $product['name']);
                        $product_info_query->execute();
                        $product_info_result = $product_info_query->get_result();
                        if ($product_info_result->num_rows > 0) {
                            $product_info = $product_info_result->fetch_assoc();
                            $reorder_level = (float)$product_info['reorder_level'];
                        }

                        if ($total_qty <= 0) {
                            $stock_class = 'stock-low';
                            $stock_text = 'Out of Stock';
                        } elseif ($total_qty <= $reorder_level) {
                            $stock_class = 'stock-medium';
                            $stock_text = 'Low Stock';
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="stock-card">
                            <div class="stock-header">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <span class="stock-badge <?= $stock_class ?>"><?= $stock_text ?></span>
                            </div>

                            <div class="stock-details">
                                <div class="detail-item">
                                    <div class="detail-label">Current Stock</div>
                                    <div class="detail-value"><?= number_format($total_qty, 2) ?> kg</div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label">Reorder Level</div>
                                    <div class="detail-value"><?= number_format($reorder_level, 2) ?> kg</div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label">Last Transaction</div>
                                    <div class="detail-value">
                                        <?= date('M d, Y', strtotime($product['trans_date'])) ?>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label">Total Transactions</div>
                                    <div class="detail-value"><?= $product['total_transactions'] ?></div>
                                </div>

                                <?php if(!empty($product['batch_number'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Latest Batch</div>
                                    <div class="detail-value"><?= htmlspecialchars($product['batch_number']) ?></div>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($product['expiry_date'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Expiry Date</div>
                                    <div class="detail-value">
                                        <?= date('Y-m-d', strtotime($product['expiry_date'])) ?>
                                        <?php
                                        $expiry_days = floor((strtotime($product['expiry_date']) - time()) / (60 * 60 * 24));
                                        if ($expiry_days <= 30 && $expiry_days > 0): ?>
                                            <small class="text-warning">(<?= $expiry_days ?> days)</small>
                                        <?php elseif ($expiry_days <= 0): ?>
                                            <small class="text-danger">(Expired)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="action-buttons">
                                <a href="stock_card.php?product=<?= urlencode($product['name']) ?>"
                                   class="btn btn-sm btn-info">
                                    View Stock Card
                                </a>
                                <a href="add_stocks.php?product=<?= urlencode($product['name']) ?>"
                                   class="btn btn-sm btn-success">
                                    Add Stock
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('product-search');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>