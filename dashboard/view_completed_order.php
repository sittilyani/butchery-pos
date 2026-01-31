<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../includes/config.php";
include "../includes/header.php";

$page_title = "View Completed Order";

// Check for logged-in user
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

// Check for draft_id
if (!isset($_GET['draft_id'])) {
    die("Draft ID missing.");
}

$draft_id = intval($_GET['draft_id']);
$stmt = $conn->prepare("SELECT * FROM sales_drafts WHERE draft_id = ?");
$stmt->bind_param("i", $draft_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Draft order not found.");
}

$draft = $result->fetch_assoc();
$items = json_decode($draft['items'], true);
if (!is_array($items)) {
    $items = [];
}

// Set default values for missing fields
$draft['receipt_id'] = $draft['receipt_id'] ?? 'ORD' . date('Ymd') . sprintf("%04d", rand(1, 9999));
$draft['waiter_name'] = $draft['waiter_name'] ?? $_SESSION['username'];
$draft['payment_method'] = $draft['payment_method'] ?? 'Cash';
$draft['payment_status'] = $draft['payment_status'] ?? 'Pending';
$draft['tendered_amount'] = $draft['tendered_amount'] ?? '0.00';

$stmt->close();

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
    </style>
</head>
<body>
<div class="main-content">
    <div class="container-fluid">
        <h2 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?> - Receipt ID: <?php echo htmlspecialchars($draft['receipt_id']); ?></h2>
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
                    <input type="hidden" name="receipt_id" id="receipt_id" value="<?php echo htmlspecialchars($draft['receipt_id']); ?>">
                    <input type="hidden" name="waiter_name" id="waiter_name" value="<?php echo htmlspecialchars($draft['waiter_name']); ?>">
                    <input type="hidden" name="draft_id" id="draft_id" value="<?php echo htmlspecialchars($draft_id); ?>">

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
                        <select class="form-control" id="payment_status" name="payment_status">
                            <option value="Pending" <?php echo $draft['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo $draft['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
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

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="submit-order">Submit Order</button>
                        <button type="button" class="btn btn-secondary" id="save-draft">Save as Draft</button>
                        <button type="button" class="btn btn-info" id="print-receipt">Print Receipt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let orderItems = <?php echo json_encode($items ?: []); ?>;
    console.log('Initial orderItems:', orderItems);

    // Initialize orderItems with id for consistency
    orderItems = orderItems.map(item => ({
        id: item.id || 0,
        name: item.name || 'Unknown Product',
        quantity: parseInt(item.quantity) || 1,
        price: parseFloat(item.price) || 0,
        total: parseFloat(item.total) || (parseInt(item.quantity || 1) * parseFloat(item.price || 0))
    }));
    console.log('Processed orderItems:', orderItems);

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
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name') || 'Unknown Product';
        const productPrice = parseFloat($(this).data('product-price')) || 0;

        const existingItem = orderItems.find(item => item.id === productId);
        if (existingItem) {
            existingItem.quantity++;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            orderItems.push({ id: productId, name: productName, quantity: 1, price: productPrice, total: productPrice });
        }
        updateOrderTable();
    });

    function updateOrderTable() {
        let html = '';
        orderItems.forEach((item, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.name}</td>
                    <td><input type="number" class="form-control quantity-input" data-index="${index}" value="${item.quantity}" min="1"></td>
                    <td>${item.price.toFixed(2)}</td>
                    <td>${item.total.toFixed(2)}</td>
                    <td><button class="btn btn-danger btn-sm remove-item" data-index="${index}">Remove</button></td>
                </tr>
            `;
        });
        $('#order-items').html(html);
        updateTotals();
    }

    function updateTotals() {
        const total = orderItems.reduce((sum, item) => sum + item.total, 0);
        const tax = total * 0.015;
        const grand = total + tax;

        $('#total-amount').text(total.toFixed(2));
        $('#tax-amount').text(tax.toFixed(2));
        $('#grand-total').text(grand.toFixed(2));

        const tendered = parseFloat($('#tendered-amount').val()) || 0;
        const change = tendered - grand;

        $('#change-amount').text(change.toFixed(2));
        $('#payment_status').val(tendered >= grand ? 'Paid' : 'Pending');
    }

    $('#tendered-amount').on('input', updateTotals);

    $('#submit-order').click(function() {
        if (orderItems.length === 0) return alert('Please add items to the order.');

        const data = {
            receipt_id: $('#receipt_id').val() || '<?php echo htmlspecialchars($draft['receipt_id']); ?>',
            waiter_name: $('#waiter_name').val() || '<?php echo htmlspecialchars($draft['waiter_name']); ?>',
            payment_method: $('#payment_method').val() || '<?php echo htmlspecialchars($draft['payment_method']); ?>',
            payment_status: $('#payment_status').val() || '<?php echo htmlspecialchars($draft['payment_status']); ?>',
            items: orderItems,
            total_amount: $('#total-amount').text() || '0.00',
            tax_amount: $('#tax-amount').text() || '0.00',
            grand_total: $('#grand-total').text() || '0.00',
            tendered_amount: $('#tendered-amount').val() || '<?php echo htmlspecialchars($draft['tendered_amount']); ?>',
            draft_id: $('#draft_id').val() || '<?php echo htmlspecialchars($draft_id); ?>'
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
                    setTimeout(function() {
                        window.location.href = '../sales/view_order.php';
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

    $('#save-draft').click(function() {
        if (orderItems.length === 0) return alert('Please add items to the draft.');

        const data = {
            receipt_id: $('#receipt_id').val() || '<?php echo htmlspecialchars($draft['receipt_id']); ?>',
            waiter_name: $('#waiter_name').val() || '<?php echo htmlspecialchars($draft['waiter_name']); ?>',
            payment_method: $('#payment_method').val() || '<?php echo htmlspecialchars($draft['payment_method']); ?>',
            payment_status: $('#payment_status').val() || '<?php echo htmlspecialchars($draft['payment_status']); ?>',
            items: orderItems,
            total_amount: $('#total-amount').text() || '0.00',
            tax_amount: $('#tax_amount').text() || '0.00',
            grand_total: $('#grand_total').text() || '0.00',
            tendered_amount: $('#tendered-amount').val() || '<?php echo htmlspecialchars($draft['tendered_amount']); ?>',
            draft_id: $('#draft_id').val() || '<?php echo htmlspecialchars($draft_id); ?>'
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

    $('#print-receipt').click(function() {
        if (orderItems.length === 0) return alert('No items to print.');

        const printWindow = window.open('', '_blank');

        let itemsHtml = `
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <tr><th>#</th><th>Name</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        `;

        orderItems.forEach((item, i) => {
            itemsHtml += `
                <tr>
                    <td style="padding:2px;border:1px solid #000;">${i + 1}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.name}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.quantity}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.price.toFixed(2)}</td>
                    <td style="padding:2px;border:1px solid #000;">${item.total.toFixed(2)}</td>
                </tr>`;
        });

        itemsHtml += '</table>';

        const waiterName = $('#waiter_name').val() || 'The Touch Haven';

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
                <p><strong>You were served By:</strong> ${waiterName}</p>
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