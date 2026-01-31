<?php
header('Content-Type: application/json');
include "../includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Remove Draft Item Input: ' . $input);

if (!$data || !isset($data['draft_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing draft_id']);
    exit;
}

$draft_id = $data['draft_id'];

try {
    $stmt = $conn->prepare("DELETE FROM sales_drafts WHERE draft_id = ?");
    $stmt->bind_param("i", $draft_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Item removed successfully']);
    } else {
        error_log('SQL Error: ' . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
$conn->close();
?>