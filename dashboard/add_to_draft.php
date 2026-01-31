<?php
header('Content-Type: application/json');
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Add to Draft Input: ' . $input);

if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing items data']);
    exit;
}

$required_fields = ['receipt_id', 'payment_method', 'payment_status', 'tendered_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

$receipt_id = $data['receipt_id'];
$payment_method = $data['payment_method'];
$payment_status = $data['payment_status'];
$tendered_amount = (float)$data['tendered_amount'];

try {
    $stmt = $conn->prepare("
        INSERT INTO sales_drafts (
            receipt_id, name, quantity, price, total_amount,
            tax_amount, grand_total, payment_method, payment_status, tendered_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($data['items'] as $item) {
        $required_item_fields = ['name', 'quantity', 'price', 'total_amount', 'tax_amount', 'grand_total'];
        foreach ($required_item_fields as $field) {
            if (!isset($item[$field])) {
                echo json_encode(['status' => 'error', 'message' => "Missing item field: $field"]);
                exit;
            }
        }

        $name = $item['name'];
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $total_amount = (float)$item['total_amount'];
        $tax_amount = (float)$item['tax_amount'];
        $grand_total = (float)$item['grand_total'];

        $stmt->bind_param(
            "ssdddddssd",
            $receipt_id, $name, $quantity, $price, $total_amount,
            $tax_amount, $grand_total, $payment_method, $payment_status, $tendered_amount
        );

        if (!$stmt->execute()) {
            error_log('SQL Error: ' . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to add item to draft']);
            exit;
        }
    }

    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Draft saved successfully']);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
$conn->close();
?>