<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../includes/config.php";
include "../includes/header.php";

$page_title = "Butchery POS - Take Orders";

// Check for logged-in user
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

// Generate or use existing receipt ID
$receipt_id = isset($_GET['receipt_id']) ? $_GET['receipt_id'] : 'BUT' . date('YmdHis') . rand(100, 999);

// Load draft order if receipt_id is provided
$draft = null;
$items = [];
if (isset($_GET['receipt_id'])) {
    $stmt = $conn->prepare("SELECT * FROM sales_drafts WHERE receipt_id = ?");
    $stmt->bind_param("s", $receipt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $draft = $items[0]; // Use first row for defaults
    }
    $stmt->close();
}

// Fetch categories
$categories = [];
$query = "SELECT category_id as id, name, photo FROM categories WHERE is_active = 1";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Check if customers table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'customers'");
if ($check_table->num_rows == 0) {
    // Create customers table
    $create_customers = "CREATE TABLE customers (
        customer_id INT PRIMARY KEY AUTO_INCREMENT,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_customers);

    // Insert default walk-in customer
    $conn->query("INSERT INTO customers (customer_name, phone) VALUES ('Walk-in Customer', '')");
}

// Fetch customers for dropdown
$customers = [];
$customer_query = "SELECT customer_id, customer_name, phone FROM customers ORDER BY customer_name";
$customer_result = $conn->query($customer_query);
if ($customer_result) {
    while ($row = $customer_result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .category-card {
            transition: transform 0.2s;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid #ddd;
            background-color: #fff;
            border-radius: 10px;
        }
        .category-card:hover {
            transform: scale(1.05);
            border-color: #dc3545;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        }
        .category-card.active {
            border-color: #dc3545;
            background-color: #ffe6e6;
        }
        .product-item {
            cursor: pointer;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 120px;
            background-color: #fff;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        .product-item:hover {
            background-color: #f8f9fa;
            border-color: #dc3545;
            transform: translateY(-2px);
        }
        .product-item.disabled {
            background-color: #f0f0f0;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .product-item h6 {
            font-size: 1rem;
            margin: 5px 0;
            font-weight: bold;
            color: #333;
        }
        .product-item .price {
            font-size: 0.9rem;
            color: #dc3545;
            font-weight: bold;
        }
        .product-item .category {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .butchery-header {
            background-color: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .customer-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .stock-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .stock-low {
            background-color: #ffc107;
            color: #000;
        }
        .stock-ok {
            background-color: #28a745;
            color: white;
        }
        .category-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            margin: 10px auto;
            border: 2px solid #ddd;
        }
        .category-name {
            font-size: 0.8rem;
            font-weight: bold;
            color: #333;
            padding: 0 5px 5px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="butchery-header">
        <h2 class="text-center mb-2"><?php echo htmlspecialchars($page_title); ?></h2>
        <h4 class="text-center">Receipt ID: <?php echo htmlspecialchars($receipt_id); ?></h4>
        <p class="text-center mb-0">Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
    </div>

    <div class="row">
        <!-- Customer Information Section -->
        <div class="col-12">
            <div class="customer-section">
                <div class="row">
                    <div class="col-md-4">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select class="form-control" id="customer_id" name="customer_id">
                            <option value="0" selected>Walk-in Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['customer_id']; ?>">
                                    <?php echo htmlspecialchars($customer['customer_name'] . ($customer['phone'] ? ' - ' . $customer['phone'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="new_customer_name" class="form-label">New Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="new_customer_name" placeholder="Enter new customer name">
                    </div>
                    <div class="col-md-4">
                        <label for="new_customer_phone" class="form-label">New Customer Phone (Optional)</label>
                        <input type="text" class="form-control" id="new_customer_phone" placeholder="Enter phone number">
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Section -->
        <div class="col-md-3">
            <h4>Butchery Categories</h4>
            <div class="row">
                <?php foreach ($categories as $category): ?>
                    <div class="col-6 mb-3">
                        <div class="card category-card text-center"
                             data-category-id="<?php echo $category['id']; ?>"
                             data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                             style="height: 120px;">
                            <?php if (!empty($category['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($category['photo']); ?>"
                                     class="category-image"
                                     alt="<?php echo htmlspecialchars($category['name']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/Logo1-rb1.png"
                                     class="category-image"
                                     alt="<?php echo htmlspecialchars($category['name']); ?>">
                            <?php endif; ?>
                            <div class="category-name">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Section -->
        <div class="col-md-5">
            <h4>Butchery Products</h4>
            <div class="search-container">
                <input type="text" id="product-search" class="form-control"
                       placeholder="Search by product name (e.g., Beef Ribs, Chicken Wings)">
                <button type="button" id="clear-search" class="btn btn-danger">Clear</button>
            </div>
            <div id="products-list" class="row">
                <div class="col-12">
                    <p class="text-muted text-center">Select a category to view products</p>
                </div>
            </div>
        </div>

        <!-- Order Summary Section -->
        <div class="col-md-4">
            <h4>Order Summary</h4>
            <form id="order-form" method="post">
                <input type="hidden" name="receipt_id" id="receipt_id" value="<?php echo htmlspecialchars($receipt_id); ?>">
                <input type="hidden" name="draft_id" id="draft_id" value="<?php echo htmlspecialchars($draft['draft_id'] ?? ''); ?>">

                <div class="mb-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method">
                        <option value="Cash" <?php echo ($draft['payment_method'] ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="Mpesa" <?php echo ($draft['payment_method'] ?? '') === 'Mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                        <option value="Credit Card" <?php echo ($draft['payment_method'] ?? '') === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="Bank Transfer" <?php echo ($draft['payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="Cheque" <?php echo ($draft['payment_method'] ?? '') === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                        <option value="Airtel Money" <?php echo ($draft['payment_method'] ?? '') === 'Airtel Money' ? 'selected' : ''; ?>>Airtel Money</option>
                        <option value="T-Kash" <?php echo ($draft['payment_method'] ?? '') === 'T-Kash' ? 'selected' : ''; ?>>T-Kash</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="payment_status" class="form-label">Payment Status</label>
                    <select class="form-control" id="payment_status" name="payment_status">
                        <option value="Pending" <?php echo ($draft['payment_status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo ($draft['payment_status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partially Paid" <?php echo ($draft['payment_status'] ?? '') === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty (Kgs)</th>
                                <th>Price/Kg</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="order-items">
                            <tr>
                                <td colspan="6" class="text-center text-muted">No items added yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <p><strong>Subtotal:</strong> KES <span id="total-amount">0.00</span></p>
                    <p><strong>Tax (1.5%):</strong> KES <span id="tax-amount">0.00</span></p>
                    <p><strong>Grand Total:</strong> KES <span id="grand-total">0.00</span></p>
                    <div class="form-group">
                        <label for="tendered-amount" class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="tendered-amount" name="tendered_amount"
                               value="<?php echo htmlspecialchars($draft['tendered_amount'] ?? '0.00'); ?>" step="0.01" min="0">
                    </div>
                    <p><strong>Balance:</strong> KES <span id="change-amount">0.00</span></p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" id="save-draft">Save Draft</button>
                    <button type="button" class="btn btn-success" id="submit-order">Submit Order</button>
                    <button type="button" class="btn btn-info" id="print-receipt">Print Receipt</button>
                    <button type="button" class="btn btn-warning" id="clear-cart">Clear Cart</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Client-side order items array
    let orderItems = [];
    let currentCategoryId = 0;
    let currentCategoryName = '';

    // Load products
    function loadProducts(categoryId, categoryName, search = '') {
        currentCategoryId = categoryId || currentCategoryId;
        currentCategoryName = categoryName || currentCategoryName;

        $.ajax({
            url: 'fetch_products.php',
            method: 'GET',
            data: {
                category_id: currentCategoryId,
                category_name: currentCategoryName,
                search: search
            },
            success: function(response) {
                $('#products-list').html(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { status, error, responseText: xhr.responseText });
                $('#products-list').html('<div class="col-12"><p class="text-danger">Error loading products. Please try again.</p></div>');
            }
        });
    }

    // Category click
    $('.category-card').click(function() {
        $('.category-card').removeClass('active');
        $(this).addClass('active');
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        $('#product-search').val('');
        loadProducts(categoryId, categoryName);
    });

    // Search input
    $('#product-search').on('input', function() {
        const search = $(this).val().trim();
        if (search.length >= 1) {
            loadProducts(currentCategoryId, currentCategoryName, search);
        } else {
            loadProducts(currentCategoryId, currentCategoryName);
        }
    });

    // Clear search
    $('#clear-search').click(function() {
        $('#product-search').val('');
        loadProducts(currentCategoryId, currentCategoryName);
    });

    // Add product to client-side order
    $('#products-list').on('click', '.product-item:not(.disabled)', function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name') || 'Unknown Product';
        const productPrice = parseFloat($(this).data('product-price')) || 0;
        const stockQty = parseFloat($(this).data('stock-qty')) || 0;

        // Check if product already exists
        const existingItem = orderItems.find(item => item.product_id === productId);
        if (existingItem) {
            existingItem.quantity += 0.5; // Add 0.5 kg by default
            existingItem.total_amount = existingItem.price * existingItem.quantity;
            existingItem.tax_amount = existingItem.total_amount * 0.015;
            existingItem.grand_total = existingItem.total_amount;
        } else {
            orderItems.push({
                product_id: productId,
                product_name: productName,
                quantity: 0.5, // Default 0.5 kg
                price: productPrice,
                total_amount: productPrice * 0.5,
                tax_amount: (productPrice * 0.5) * 0.015,
                grand_total: productPrice * 0.5
            });
        }

        updateOrderTable();
    });

    // Update quantity
    $('#order-items').on('change', '.quantity-input', function() {
        const productId = $(this).data('product-id');
        const quantity = parseFloat($(this).val()) || 0.5;

        const item = orderItems.find(item => item.product_id === productId);
        if (item) {
            item.quantity = quantity;
            item.total_amount = item.price * quantity;
            item.tax_amount = item.total_amount * 0.015;
            item.grand_total = item.total_amount;
        }

        updateOrderTable();
    });

    // Remove item
    $('#order-items').on('click', '.remove-item', function() {
        const productId = $(this).data('product-id');
        orderItems = orderItems.filter(item => item.product_id !== productId);
        updateOrderTable();
    });

    // Update order table
    function updateOrderTable() {
        if (orderItems.length === 0) {
            $('#order-items').html('<tr><td colspan="6" class="text-center text-muted">No items added yet</td></tr>');
        } else {
            let html = '';
            orderItems.forEach((item, index) => {
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.product_name}</td>
                        <td>
                            <input type="number" class="form-control quantity-input"
                                   data-product-id="${item.product_id}"
                                   value="${item.quantity.toFixed(2)}"
                                   min="0.1" step="0.1" style="width: 80px;">
                        </td>
                        <td>KES ${parseFloat(item.price).toFixed(2)}</td>
                        <td>KES ${parseFloat(item.total_amount).toFixed(2)}</td>
                        <td><button class="btn btn-danger btn-sm remove-item" data-product-id="${item.product_id}">Remove</button></td>
                    </tr>
                `;
            });
            $('#order-items').html(html);
        }
        updateTotals();
    }

    // Update totals
    function updateTotals() {
        const total = orderItems.reduce((sum, item) => sum + parseFloat(item.total_amount), 0);
        const tax = orderItems.reduce((sum, item) => sum + parseFloat(item.tax_amount), 0);
        const grand = total + tax;

        $('#total-amount').text(total.toFixed(2));
        $('#tax-amount').text(tax.toFixed(2));
        $('#grand-total').text(grand.toFixed(2));

        const tendered = parseFloat($('#tendered-amount').val()) || 0;
        const change = tendered - grand;

        $('#change-amount').text(change.toFixed(2));

        // Update payment status
        if (tendered >= grand && grand > 0) {
            $('#payment_status').val('Paid');
        } else if (tendered > 0 && tendered < grand) {
            $('#payment_status').val('Partially Paid');
        } else {
            $('#payment_status').val('Pending');
        }
    }

    // Save draft to database
    $('#save-draft').click(function() {
        if (orderItems.length === 0) {
            alert('Please add items to the order.');
            return;
        }

        // Get customer info
        const customerId = $('#customer_id').val();
        const newCustomerName = $('#new_customer_name').val();
        const newCustomerPhone = $('#new_customer_phone').val();

        const data = {
            receipt_id: $('#receipt_id').val(),
            customer_id: customerId,
            new_customer_name: newCustomerName,
            new_customer_phone: newCustomerPhone,
            payment_method: $('#payment_method').val(),
            payment_status: $('#payment_status').val(),
            tendered_amount: $('#tendered-amount').val() || '0.00',
            items: orderItems.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: item.quantity,
                price: item.price,
                total_amount: item.total_amount,
                tax_amount: item.tax_amount,
                grand_total: item.grand_total
            }))
        };

        $.ajax({
            url: 'add_to_draft.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                console.log('Save Draft Response:', response);
                if (response.status === 'success') {
                    showMessage('Draft saved successfully.', 'success');
                    // Clear client-side items after saving
                    orderItems = [];
                    updateOrderTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.responseText);
                alert('Error saving draft.');
            }
        });
    });

    // Update tendered amount
    $('#tendered-amount').on('input', function() {
        updateTotals();
    });

    // Submit order
    $('#submit-order').click(function() {
        if (orderItems.length === 0) {
            alert('Please add items to the order.');
            return;
        }

        // Get customer info
        const customerId = $('#customer_id').val();
        const newCustomerName = $('#new_customer_name').val();
        const newCustomerPhone = $('#new_customer_phone').val();

        const data = {
            receipt_id: $('#receipt_id').val(),
            customer_id: customerId,
            new_customer_name: newCustomerName,
            new_customer_phone: newCustomerPhone,
            payment_method: $('#payment_method').val(),
            payment_status: $('#payment_status').val(),
            items: orderItems.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: item.quantity,
                price: item.price,
                total_amount: item.total_amount
            })),
            total_amount: $('#total-amount').text(),
            tax_amount: $('#tax-amount').text(),
            grand_total: $('#grand-total').text(),
            tendered_amount: $('#tendered-amount').val() || '0.00'
        };

        console.log('Submit Order Data:', data);

        $.ajax({
            url: 'submit_order.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                console.log('Submit Order Response:', response);
                if (response.status === 'success') {
                    showMessage(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = '../views/view_order.php';
                    }, 2000);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.responseText);
                alert('Error submitting order.');
            }
        });
    });

    // Print receipt
    $('#print-receipt').click(function() {
        if (orderItems.length === 0) {
            alert('No items to print.');
            return;
        }

        const printWindow = window.open('', '_blank');
        const customerId = $('#customer_id').val();
        const customerText = customerId === '0' ? 'Walk-in Customer' :
                            $('#customer_id option:selected').text().split(' - ')[0];

        let itemsHtml = `
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <tr><th>#</th><th>Product</th><th>Qty(Kgs)</th><th>Price/Kg</th><th>Total</th></tr>
        `;

        orderItems.forEach((item, i) => {
            itemsHtml += `
                <tr>
                    <td style="padding:2px;border:1px solid #000;">${i + 1}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.product_name}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.quantity.toFixed(2)}</td>
                    <td style="padding:2px;border:1px solid #000;">KES ${parseFloat(item.price).toFixed(2)}</td>
                    <td style="padding:2px;border:1px solid #000;">KES ${parseFloat(item.total_amount).toFixed(2)}</td>
                </tr>`;
        });

        itemsHtml += '</table>';

        printWindow.document.write(`
            <html><head><title>Butchery Receipt</title>
            <style>
                @media print {
                    @page {
                        size: 80mm auto;
                        margin: 0;
                        padding: 0;
                    }
                    body {
                        width: 80mm;
                        margin: 0;
                        padding: 0;
                    }
                }
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 10px;
                    padding: 5px;
                    margin: 0;
                    width: 80mm;
                }
                .receipt-header {
                    text-align: center;
                    border-bottom: 1px dashed #000;
                    padding-bottom: 5px;
                    margin-bottom: 5px;
                }
                .receipt-header h2 {
                    font-size: 12px;
                    margin: 2px 0;
                    color: #dc3545;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 9px;
                }
                th, td {
                    border-bottom: 1px dashed #ccc;
                    padding: 2px;
                    text-align: left;
                }
                .totals {
                    margin-top: 10px;
                    border-top: 2px solid #000;
                    padding-top: 5px;
                }
                .footer {
                    text-align: center;
                    margin-top: 10px;
                    font-size: 8px;
                    border-top: 1px dashed #000;
                    padding-top: 5px;
                }
            </style>
            </head><body>
            <div class="receipt-header">
                <img src="../assets/images/Logo1-rb1.png" width="562" height="444" alt="">
                <h2>BUTCHERY POS</h2>
                <p><strong>Receipt:</strong> ${$('#receipt_id').val()}</p>
                <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>
                <p><strong>Customer:</strong> ${customerText}</p>
                <p><strong>Server:</strong> ${'<?php echo $_SESSION["username"] ?? "Staff"; ?>'}</p>
            </div>
            ${itemsHtml}
            <div class="totals">
                <p><strong>Subtotal:</strong> KES ${$('#total-amount').text()}</p>
                <p><strong>Tax (1.5%):</strong> KES ${$('#tax-amount').text()}</p>
                <p><strong>Grand Total:</strong> KES ${$('#grand-total').text()}</p>
                <p><strong>Payment Method:</strong> ${$('#payment_method').val()}</p>
                <p><strong>Amount Paid:</strong> KES ${$('#tendered-amount').val() || '0.00'}</p>
                <p><strong>Balance:</strong> KES ${$('#change-amount').text()}</p>
                <p><strong>Status:</strong> ${$('#payment_status').val()}</p>
            </div>
            <div class="footer">
                <p>Thank you for your business!</p>
                <p>Quality Meat & Excellent Service</p>
                <p>Tel: 0712 345 678</p>
                <p>**END OF RECEIPT**</p>
            </div>
            </body></html>
        `);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    });

    // Clear cart
    $('#clear-cart').click(function() {
        if (orderItems.length > 0 && confirm('Are you sure you want to clear the cart?')) {
            orderItems = [];
            updateOrderTable();
            $('#tendered-amount').val('0.00');
            $('#payment_status').val('Pending');
            updateTotals();
            showMessage('Cart cleared.', 'info');
        }
    });

    // Show message function
    function showMessage(message, type) {
        const colors = {
            success: '#DDFCAF',
            error: '#F8D7DA',
            info: '#D1ECF1'
        };
        const textColors = {
            success: 'green',
            error: '#721c24',
            info: '#0c5460'
        };

        const messageDiv = $('<div>')
            .text(message)
            .css({
                'background-color': colors[type] || colors.info,
                'color': textColors[type] || textColors.info,
                'font-size': '14px',
                'padding': '10px',
                'margin-bottom': '15px',
                'border-radius': '5px',
                'text-align': 'center'
            });

        $('.butchery-header').after(messageDiv);
        setTimeout(() => messageDiv.fadeOut(), 3000);
    }

    // Initialize order table
    updateOrderTable();

    // Auto-select first category
    if ($('.category-card').length > 0) {
        $('.category-card').first().click();
    }
});
</script>
</body>
</html>