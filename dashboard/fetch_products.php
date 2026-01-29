<?php
include "../includes/config.php";

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$category_name = isset($_GET['category_name']) ? trim($_GET['category_name']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get category name from ID if only ID is provided
if ($category_id > 0 && empty($category_name)) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $category_name = $row['name'];
    }
    $stmt->close();
}

// Build query
$query = "SELECT p.id, p.name, p.category, p.selling_price, p.buying_price,
                 p.reorder_level
          FROM products p
          WHERE p.is_active = 1";

if (!empty($category_name)) {
    $query .= " AND p.category = '" . $conn->real_escape_string($category_name) . "'";
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%'
                   OR p.category LIKE '%" . $conn->real_escape_string($search) . "%')";
}

$query .= " ORDER BY p.name";
$result = $conn->query($query);

if ($result->num_rows == 0): ?>
    <div class="col-12">
        <p class="text-muted text-center">No products found in this category.</p>
    </div>
<?php else:
    while ($row = $result->fetch_assoc()):
        // Check if stock table exists and get stock quantity
        $stock_qty = 0;
        $stock_check = $conn->query("SHOW TABLES LIKE 'stock'");
        if ($stock_check->num_rows > 0) {
            $stock_query = "SELECT quantity FROM stock WHERE product_id = " . $row['id'];
            $stock_result = $conn->query($stock_query);
            if ($stock_result && $stock_result->num_rows > 0) {
                $stock_data = $stock_result->fetch_assoc();
                $stock_qty = $stock_data['quantity'];
            }
        }

        $stock_class = $stock_qty <= $row['reorder_level'] ? 'stock-low' : 'stock-ok';
        $stock_text = number_format($stock_qty, 1) . ' kg';
        $disabled = $stock_qty <= 0 ? 'disabled' : '';
?>
<div class="col-6 col-md-4 mb-3">
    <div class="product-item <?php echo $disabled; ?>"
         data-product-id="<?php echo $row['id']; ?>"
         data-product-name="<?php echo htmlspecialchars($row['name']); ?>"
         data-product-price="<?php echo $row['selling_price']; ?>"
         data-stock-qty="<?php echo $stock_qty; ?>">
        <span class="stock-badge <?php echo $stock_class; ?>">
            <?php echo $stock_text; ?>
        </span>
        <h6><?php echo htmlspecialchars($row['name']); ?></h6>
        <div class="price">KES <?php echo number_format($row['selling_price'], 2); ?>/kg</div>
        <div class="category"><?php echo htmlspecialchars($row['category']); ?></div>
    </div>
</div>
<?php
    endwhile;
endif;
?>