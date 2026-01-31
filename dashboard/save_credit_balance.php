<?php
header('Content-Type: application/json');
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Save Credit Balance Input: ' . $input);

if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing items data']);
    exit;
}

$required_fields = ['receipt_id', 'customer_name', 'balance_amount', 'payment_method', 'payment_status', 'total_amount', 'tax_amount', 'grand_total', 'tendered_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

$receipt_id = $data['receipt_id'];
$customer_name = $data['customer_name'];
$balance_amount = (float)$data['balance_amount'];
$payment_method = $data['payment_method'];
$payment_status = $data['payment_status'];
$total_amount = (float)$data['total_amount'];
$tax_amount = (float)$data['tax_amount'];
$grand_total = (float)$data['grand_total'];
$tendered_amount = (float)$data['tendered_amount'];

// Check if already paid
$stmt = $conn->prepare("SELECT payment_status FROM sales WHERE receipt_id = ?");
$stmt->bind_param("s", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if ($row['payment_status'] === 'Paid') {
        echo json_encode(['status' => 'error', 'message' => 'Order is already paid']);
        exit;
    }
}
$stmt->close();

try {
    $conn->begin_transaction();

    // Insert into sales
    $stmt = $conn->prepare("
        INSERT INTO sales (
            receipt_id, total_amount, tax_amount, grand_total,
            payment_method, payment_status, tendered_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sdddsss",
        $receipt_id, $total_amount, $tax_amount, $grand_total,
        $payment_method, $payment_status, $tendered_amount
    );
    $stmt->execute();
    $sales_id = $conn->insert_id;
    $stmt->close();

    // Insert sale items
    $item_stmt = $conn->prepare("
        INSERT INTO sale_items (
            sales_id, name, quantity, price, total_amount
        ) VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($data['items'] as $item) {
        $name = $item['name'];
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $item_total = (float)$item['total'];

        $item_stmt->bind_param(
            "isidd",
            $sales_id, $name, $quantity, $price, $item_total
        );
        $item_stmt->execute();
    }
    $item_stmt->close();

    // Insert into credit_balances
    $credit_stmt = $conn->prepare("
        INSERT INTO credit_balances (receipt_id, customer_name, balance_amount)
        VALUES (?, ?, ?)
    ");
    $credit_stmt->bind_param("ssd", $receipt_id, $customer_name, $balance_amount);
    $credit_stmt->execute();
    $credit_stmt->close();

    // Delete drafts
    $delete_stmt = $conn->prepare("DELETE FROM sales_drafts WHERE receipt_id = ?");
    $delete_stmt->bind_param("s", $receipt_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Credit balance saved successfully']);
} catch (Exception $e) {
    $conn->rollback();
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
$conn->close();
?>