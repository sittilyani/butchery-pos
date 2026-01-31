<?php
// delete_receipt.php
ob_start();
include '../includes/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['receipt_id'])) {
        throw new Exception("Invalid request.");
    }

    $receipt_id = intval($_POST['receipt_id']);
    $query = "DELETE FROM sales_receipts WHERE receipt_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $receipt_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'receipt deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
ob_end_flush();
?>