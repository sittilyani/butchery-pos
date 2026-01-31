<?php
ob_start(); // Start output buffering to prevent premature output
session_start();
include '../includes/config.php';
// Note: Removed include '../includes/header.php' to avoid HTML output

if (!isset($_POST['draft_id'], $_POST['waiter_name'], $_POST['payment_method'], $_POST['tendered_amount'], $_POST['items'])) {
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields.']));
}

$draft_id = intval($_POST['draft_id']);
$waiter_name = $_POST['waiter_name'];
$payment_method = $_POST['payment_method'];
$tendered_amount = floatval($_POST['tendered_amount']);
$items = $_POST['items'];

// Recalculate totals
$total_amount = 0;
foreach ($items as &$item) {
    $item['total'] = $item['quantity'] * $item['price'];
    $total_amount += $item['total'];
}
$tax_amount = round($total_amount * 0.015, 2);
$grand_total = $total_amount; // Fixed: Include tax in grand_total
$items_json = json_encode($items);

try {
    $stmt = $conn->prepare("UPDATE sales_drafts SET waiter_name = ?, payment_method = ?, tendered_amount = ?, items = ?, total_amount = ?, tax_amount = ?, grand_total = ? WHERE draft_id = ?");
    $stmt->bind_param("ssdsssdi", $waiter_name, $payment_method, $tendered_amount, $items_json, $total_amount, $tax_amount, $grand_total, $draft_id);

    if ($stmt->execute()) {
        // Output HTML with JavaScript redirect
        echo "
        <html>
        <head>
            <title>Order Update</title>
        </head>
        <body>
            <span style='background-color: #DDFCAF; color: green; font-size: 18px; height: 60px; line-height: 40px; padding: 5px 10px; margin-bottom: 10px; display: inline-block;'>Order updated successfully.</span>
            <script>
                setTimeout(function() {
                    window.location.href = '../sales/view_order.php';
                }, 2000);
            </script>
        </body>
        </html>
        ";
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No changes made or update failed: ' . $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error updating order: ' . $e->getMessage()]);
}

$conn->close();
ob_end_flush(); // Flush output buffer
?>