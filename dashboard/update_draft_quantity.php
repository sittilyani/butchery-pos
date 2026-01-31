<?php
header('Content-Type: application/json');
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Update Quantity Input: ' . $input);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

$required_fields = ['draft_id', 'quantity', 'receipt_id', 'payment_method', 'payment_status', 'tendered_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

$draft_id = $data['draft_id'];
$quantity = (int)$data['quantity'];
$receipt_id = $data['receipt_id'];
$payment_method = $data['payment_method'];
$payment_status = $data['payment_status'];
$tendered_amount = (float)$data['tendered_amount'];

try {
    // Fetch current price to recalculate totals
    $stmt = $conn->prepare("SELECT price FROM sales_drafts WHERE draft_id = ?");
    $stmt->bind_param("i", $draft_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $price = (float)$row['price'];
        $total_amount = $quantity * $price;
        $tax_amount = $total_amount * 0.015;
        $grand_total = $total_amount + $tax_amount;

        $update_stmt = $conn->prepare("
            UPDATE sales_drafts SET
                quantity = ?, total_amount = ?, tax_amount = ?, grand_total = ?,
                payment_method = ?, payment_status = ?, tendered_amount = ?
            WHERE draft_id = ?
        ");
        $update_stmt->bind_param(
            "idddsssi",
            $quantity, $total_amount, $tax_amount, $grand_total,
            $payment_method, $payment_status, $tendered_amount, $draft_id
        );

        if ($update_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Quantity updated successfully']);
        } else {
            error_log('SQL Error: ' . $update_stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update quantity']);
        }
        $update_stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Draft item not found']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
$conn->close();
?>