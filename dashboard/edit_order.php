<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../includes/config.php";
include "../includes/header.php";

$page_title = "Edit Draft Order";

// Check for logged-in user
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

// Check for receipt_id
if (!isset($_GET['receipt_id'])) {
    die("Receipt ID missing.");
}

$receipt_id = $_GET['receipt_id'];

// Check if order is already paid
$is_paid = false;
$stmt = $conn->prepare("SELECT payment_status FROM sales WHERE receipt_id = ?");
$stmt->bind_param("s", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $is_paid = $row['payment_status'] === 'Paid';
}
$stmt->close();

// Fetch draft items
$items = [];
$stmt = $conn->prepare("
    SELECT draft_id, name, quantity, price, total_amount, tax_amount, grand_total,
           payment_method, payment_status, tendered_amount
    FROM sales_drafts WHERE receipt_id = ?
");
$stmt->bind_param("s", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$draft = $items[0] ?? [
    'receipt_id' => $receipt_id,
    'payment_method' => 'Cash',
    'payment_status' => 'Pending',
    'tendered_amount' => '0.00'
];

// Fetch categories
$categories = [];
$query = "SELECT id, name, photo FROM categories";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
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
        .container-fluid {
            width: 100%;
            margin-top: 70px;
        }
        .category-card {
            transition: transform 0.2s;
            overflow: hidden;
            cursor: pointer;
        }
        .category-card:hover {
            transform: scale(1.05);
        }
        .product-item {
            cursor: pointer;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 120px;
            background-color: #FFFF33;
            text-align: center;
        }
        .product-item:hover {
            background-color: #f8f9fa;
        }
        .product-item h6 {
            font-size: 1rem;
            margin: 5px 0;
        }
        .product-item p {
            font-size: 0.9rem;
            margin: 0;
        }
        #products-list .col-2 {
            flex: 0 0 20%;
            max-width: 20%;
        }
        #order-items tr td {
            vertical-align: middle;
        }
        .quantity-input {
            width: 60px;
            text-align: center;
        }
        #credit-form {
            display: none;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="main-content">
        <h2 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?> - Receipt ID: <?php echo htmlspecialchars($receipt_id); ?></h2>
        <?php if ($is_paid): ?>
            <div class="alert alert-danger">This order is already paid and cannot be edited.</div>
        <?php else: ?>
        <div class="row">
            <div class="col-md-3">
                <h4>Categories</h4>
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-4 mb-3">
                            <div class="card category-card text-center" data-category-id="<?php echo $category['id']; ?>" style="width: 120px; height: 120px; border-radius: 50%; margin: 0 auto;">
                                <img src="../<?php echo htmlspecialchars($category['photo'] ?: 'assets/images/default.jpg'); ?>"
                                     class="card-img-top rounded-circle"
                                     alt="<?php echo htmlspecialchars($category['name']); ?>"
                                     style="width: 80px; height: 80px; object-fit: cover; margin: 10px auto;">
                                <div class="card-body p-1">
                                    <h6 class="card-title" style="font-size: 0.9rem;"><?php echo htmlspecialchars($category['name']); ?></h6>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-5">
                <h4>Products</h4>
                <div id="products-list" class="row"></div>
            </div>

            <div class="col-md-4">
                <h4>Order Summary</h4>
                <form id="order-form" method="post">
                    <input type="hidden" name="receipt_id" id="receipt_id" value="<?php echo htmlspecialchars($draft['receipt_id'] ?? $receipt_id); ?>">

                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="Cash" <?php echo $draft['payment_method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Mpesa" <?php echo $draft['payment_method'] === 'Mpesa' ? 'selected' : ''; ?>>Mpesa</option>
                            <option value="Credit" <?php echo $draft['payment_method'] === 'Credit' ? 'selected' : ''; ?>>Credit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-control" id="payment_status" name="payment_status" disabled>
                            <option value="Pending" <?php echo $draft['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo $draft['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="order-items"></tbody>
                    </table>

                    <div class="mb-3">
                        <p><strong>Total Amount:</strong> KES <span id="total-amount">0.00</span></p>
                        <p><strong>Tax (1.5%):</strong> KES <span id="tax-amount">0.00</span></p>
                        <p><strong>Grand Total:</strong> KES <span id="grand-total">0.00</span></p>
                        <div class="form-group">
                            <label for="tendered-amount" class="form-label">Tendered Amount</label>
                            <input type="number" class="form-control" id="tendered-amount" name="tendered_amount" value="<?php echo htmlspecialchars($draft['tendered_amount']); ?>" step="0.01" min="0">
                        </div>
                        <p><strong>Change:</strong> KES <span id="change-amount">0.00</span></p>
                    </div>

                    <div id="credit-form" class="card p-3">
                        <h5>Credit Balance</h5>
                        <div class="mb-3">
                            <label for="customer-name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="customer-name" name="customer_name">
                        </div>
                        <div class="mb-3">
                            <label for="balance-amount" class="form-label">Balance Amount</label>
                            <input type="number" class="form-control" id="balance-amount" name="balance_amount" readonly>
                        </div>
                        <button type="button" class="btn btn-primary" id="save-credit">Save Credit</button>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="mark-paid">Mark as Paid</button>
                        <button type="button" class="btn btn-secondary" id="save-draft">Save as Draft</button>
                        <button type="button" class="btn btn-info" id="print-receipt">Print Receipt</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let orderItems = <?php echo json_encode($items); ?>.map(item => ({
        draft_id: item.draft_id || 0,
        product_id: item.draft_id || 0, // For new items
        name: item.name || 'Unknown Product',
        quantity: parseInt(item.quantity) || 1,
        price: parseFloat(item.price) || 0,
        total_amount: parseFloat(item.total_amount) || 0,
        tax_amount: parseFloat(item.tax_amount) || 0,
        grand_total: parseFloat(item.grand_total) || 0
    }));
    console.log('Initial orderItems:', orderItems);

    // Load products
    $('.category-card').click(function() {
        const categoryId = $(this).data('category-id');
        $.ajax({
            url: 'fetch_products.php',
            method: 'GET',
            data: { category_id: categoryId },
            success: function(response) {
                $('#products-list').html(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { status, error, responseText: xhr.responseText });
                alert('Failed to load products.');
            }
        });
    });

    // Add product
    $('#products-list').on('click', '.product-item', function() {
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid and cannot be edited.');
            return;
        }

        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name') || 'Unknown Product';
        const productPrice = parseFloat($(this).data('product-price')) || 0;

        const existingItem = orderItems.find(item => item.product_id === productId && !item.draft_id);
        if (existingItem) {
            existingItem.quantity++;
            existingItem.total_amount = existingItem.quantity * existingItem.price;
            existingItem.tax_amount = existingItem.total_amount * 0.015;
            existingItem.grand_total = existingItem.total_amount;
        } else {
            orderItems.push({
                draft_id: 0,
                product_id: productId,
                name: productName,
                quantity: 1,
                price: productPrice,
                total_amount: productPrice,
                tax_amount: productPrice * 0.015,
                grand_total: productPrice
            });
        }
        updateOrderTable();
    });

    // Update quantity
    $('#order-items').on('change', '.quantity-input', function() {
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid and cannot be edited.');
            return;
        }

        const draftId = $(this).data('draft-id');
        const quantity = parseInt($(this).val()) || 1;

        const item = orderItems.find(item => item.draft_id == draftId || item.product_id == draftId);
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
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid and cannot be edited.');
            return;
        }

        const draftId = $(this).data('draft-id');
        orderItems = orderItems.filter(item => item.draft_id != draftId && item.product_id != draftId);
        if (draftId) {
            $.ajax({
                url: 'remove_draft_item.php',
                method: 'POST',
                data: JSON.stringify({ draft_id: draftId }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    console.log('Remove Item Response:', response);
                    if (response.status !== 'success') {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('Error removing item.');
                }
            });
        }
        updateOrderTable();
    });

    // Update order table
    function updateOrderTable() {
        let html = '';
        orderItems.forEach((item, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.name}</td>
                    <td><input type="number" class="form-control quantity-input" data-draft-id="${item.draft_id || item.product_id}" value="${item.quantity}" min="1"></td>
                    <td>${parseFloat(item.price).toFixed(2)}</td>
                    <td>${parseFloat(item.total_amount).toFixed(2)}</td>
                    <td><button class="btn btn-danger btn-sm remove-item" data-draft-id="${item.draft_id || item.product_id}">Remove</button></td>
                </tr>
            `;
        });
        $('#order-items').html(html);
        updateTotals();
    }

    // Update totals
    function updateTotals() {
        const total = orderItems.reduce((sum, item) => sum + parseFloat(item.total_amount), 0);
        const tax = orderItems.reduce((sum, item) => sum + parseFloat(item.tax_amount), 0);
        const grand = orderItems.reduce((sum, item) => sum + parseFloat(item.grand_total), 0);

        $('#total-amount').text(total.toFixed(2));
        $('#tax-amount').text(tax.toFixed(2));
        $('#grand-total').text(grand.toFixed(2));

        const tendered = parseFloat($('#tendered-amount').val()) || 0;
        const change = tendered - grand;

        $('#change-amount').text(change.toFixed(2));
        $('#payment_status').val(tendered >= grand ? 'Paid' : 'Pending');

        if (tendered > 0 && tendered < grand) {
            $('#credit-form').show();
            $('#balance-amount').val((grand - tendered).toFixed(2));
        } else {
            $('#credit-form').hide();
        }
    }

    // Handle tendered amount input
    $('#tendered-amount').on('input', updateTotals);

    // Mark as Paid
    $('#mark-paid').click(function() {
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid.');
            return;
        }
        if (orderItems.length === 0) {
            alert('Please add items to the order.');
            return;
        }

        const tendered = parseFloat($('#tendered-amount').val()) || 0;
        const grand = parseFloat($('#grand-total').text()) || 0;

        if (tendered < grand) {
            alert('Tendered amount is less than grand total. Please complete the credit form.');
            return;
        }

        const data = {
            receipt_id: $('#receipt_id').val(),
            payment_method: $('#payment_method').val(),
            payment_status: 'Paid',
            items: orderItems.map(item => ({
                name: item.name,
                quantity: item.quantity,
                price: item.price,
                total: item.total_amount
            })),
            total_amount: $('#total-amount').text(),
            tax_amount: $('#tax-amount').text(),
            grand_total: $('#grand-total').text(),
            tendered_amount: $('#tendered-amount').val() || '0.00'
        };
        console.log('Mark as Paid Data:', data);

        $.ajax({
            url: 'submit_order.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                console.log('Mark as Paid Response:', response);
                if (response.status === 'success') {
                    const successMessage = $('<span>')
                        .text('Order marked as paid and receipt saved.')
                        .css({
                            'background-color': '#DDFCAF',
                            'color': 'green',
                            'font-size': '18px',
                            'padding': '5px 10px',
                            'margin-bottom': '10px',
                            'display': 'inline-block'
                        });
                    $('#order-form').prepend(successMessage);
                    setTimeout(function() {
                        window.location.href = '../sales/view_order.php';
                    }, 2000);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.responseText);
                alert('Error marking order as paid.');
            }
        });
    });

    // Save draft
    $('#save-draft').click(function() {
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid and cannot be edited.');
            return;
        }
        if (orderItems.length === 0) {
            alert('Please add items to the draft.');
            return;
        }

        const data = {
            receipt_id: $('#receipt_id').val(),
            payment_method: $('#payment_method').val(),
            payment_status: $('#payment_status').val(),
            tendered_amount: $('#tendered-amount').val() || '0.00',
            items: orderItems.map(item => ({
                draft_id: item.draft_id,
                name: item.name,
                quantity: item.quantity,
                price: item.price,
                total_amount: item.total_amount,
                tax_amount: item.tax_amount,
                grand_total: item.grand_total
            }))
        };
        console.log('Save Draft Data:', data);

        $.ajax({
            url: 'save_draft.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                console.log('Save Draft Response:', response);
                if (response.status === 'success') {
                    const successMessage = $('<span>')
                        .text(response.message)
                        .css({
                            'background-color': '#DDFCAF',
                            'color': 'green',
                            'font-size': '18px',
                            'padding': '5px 10px',
                            'margin-bottom': '10px',
                            'display': 'inline-block'
                        });
                    $('#order-form').prepend(successMessage);
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

    // Save credit balance
    $('#save-credit').click(function() {
        if (<?php echo json_encode($is_paid); ?>) {
            alert('This order is already paid and cannot be edited.');
            return;
        }

        const customerName = $('#customer-name').val().trim();
        const balanceAmount = parseFloat($('#balance-amount').val()) || 0;

        if (!customerName) {
            alert('Please enter the customer name.');
            return;
        }

        const data = {
            receipt_id: $('#receipt_id').val(),
            customer_name: customerName,
            balance_amount: balanceAmount,
            payment_method: $('#payment_method').val(),
            payment_status: 'Pending',
            items: orderItems.map(item => ({
                name: item.name,
                quantity: item.quantity,
                price: item.price,
                total: item.total_amount
            })),
            total_amount: $('#total-amount').text(),
            tax_amount: $('#tax-amount').text(),
            grand_total: $('#grand-total').text(),
            tendered_amount: $('#tendered-amount').val() || '0.00'
        };
        console.log('Save Credit Data:', data);

        $.ajax({
            url: 'save_credit_balance.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                console.log('Save Credit Response:', response);
                if (response.status === 'success') {
                    const successMessage = $('<span>')
                        .text('Credit balance saved successfully.')
                        .css({
                            'background-color': '#DDFCAF',
                            'color': 'green',
                            'font-size': '18px',
                            'padding': '5px 10px',
                            'margin-bottom': '10px',
                            'display': 'inline-block'
                        });
                    $('#order-form').prepend(successMessage);
                    $('#credit-form').hide();
                    setTimeout(function() {
                        window.location.href = '../sales/view_order.php';
                    }, 2000);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr.responseText);
                alert('Error saving credit balance.');
            }
        });
    });

    // Print receipt
    $('#print-receipt').click(function() {
        if (orderItems.length === 0) return alert('No items to print.');

        const printWindow = window.open('', '_blank');

        let itemsHtml = `
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        `;

        orderItems.forEach((item, i) => {
            itemsHtml += `
                <tr>
                    <td style="padding:2px;border:1px solid #000;">${i + 1}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.name}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.quantity}</td>
                    <td style="padding:2px;border:1px solid #000;">${parseFloat(item.price).toFixed(2)}</td>
                    <td style="padding:2px;border:1px solid #000;">${parseFloat(item.total_amount).toFixed(2)}</td>
                </tr>`;
        });

        itemsHtml += '</table>';

        printWindow.document.write(`
            <html><head><title>Receipt</title>
            <style>
                @media print {
                    @page {
                        size: 148mm auto;
                        margin: 5mm;
                        padding: 10px;
                    }
                }
                body {
                    width: 148mm;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    padding: 10px;
                    margin: 10px;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 10px;
                }
                h2 {
                    text-align: center;
                    margin: 5px 0;
                    font-size: 14px;
                }
                .receipt-info, .totals {
                    margin: 5px 0;
                }
                .receipt-info p, .totals p {
                    margin: 2px 0;
                }
                table, th, td {
                    border: 1px solid #000;
                    border-collapse: collapse;
                    text-align: left;
                }
            </style>
            </head><body>
            <h2>Order Receipt</h2>
            <div class="logo"><img src="../assets/images/TheTouch2.jpg" width="214" height="112" alt=""></div>
            <div class="receipt-info">
                <p><strong>Receipt ID:</strong> ${$('#receipt_id').val()}</p>
                <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>
                <p><strong>You were served by:</strong> The Touch Haven</p>
                <p><strong>Payment Method:</strong> ${$('#payment_method').val()}</p>
            </div>
            ${itemsHtml}
            <div class="totals">
                <p>Total Amount: KES ${$('#total-amount').text()}</p>
                <p>Tax (1.5%): KES ${$('#tax-amount').text()}</p>
                <p>Grand Total: KES ${$('#grand-total').text()}</p>
                <p>Tendered: KES ${$('#tendered-amount').val() || '0.00'}</p>
                <p>Change: KES ${$('#change-amount').text()}</p>
                <p>Payment Status: ${$('#payment_status').val()}</p>
                <p><span style="font-weight: bold; font-size: 16px; color: blue;">Till Number: 3393870</span></p>
                <p><span style="font-weight: bold; font-size: 16px; color: red;">Name: Mark Khwatenge Lyani</span></p>
                <p><span style="font-style: italic;">We Are You</span></p>
            </div>
            </body></html>
        `);
        printWindow.document.close();
        printWindow.print();
    });

    // Initialize order table and totals
    updateOrderTable();
    updateTotals();
});
</script>
</body>
</html>