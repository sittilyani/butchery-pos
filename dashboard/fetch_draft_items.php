<?php
header('Content-Type: application/json');
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['receipt_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing receipt_id']);
    exit;
}

$receipt_id = $_GET['receipt_id'];

try {
    $stmt = $conn->prepare("
        SELECT draft_id, receipt_id, name, quantity, price, total_amount,
               tax_amount, grand_total, payment_method, payment_status, tendered_amount
        FROM sales_drafts WHERE receipt_id = ?
    ");
    $stmt->bind_param("s", $receipt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($items);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
$conn->close();
?>