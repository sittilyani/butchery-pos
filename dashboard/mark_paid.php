<?php
// mark_paid.php
session_start();
include '../includes/config.php';
include '../includes/header.php';

if (!isset($_GET['draft_id'])) {
    die("Draft ID is missing.");
}

$draft_id = $_GET['draft_id'];

// Fetch the draft order
$stmt = $conn->prepare("SELECT * FROM sales_drafts WHERE draft_id = ?");
$stmt->bind_param("s", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Draft not found.");
}

$order = $result->fetch_assoc();
$stmt->close();

// Calculate change
$change = $order['tendered_amount'] - $order['grand_total'];

// Insert into sales table
$insert = $conn->prepare("INSERT INTO sales (receipt_id, waiter_name, payment_method, payment_status, items, total_amount, tax_amount, grand_total, tendered_amount, change_amount, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$paid_status = 'Paid';
$insert->bind_param(
    "ssssssddds",
    $order['receipt_id'],
    $order['waiter_name'],
    $order['payment_method'],
    $paid_status,
    $order['items'],
    $order['total_amount'],
    $order['tax_amount'],
    $order['grand_total'],
    $order['tendered_amount'],
    $change
);

if ($insert->execute()) {
    $insert->close();

    // Delete from drafts
    $delete = $conn->prepare("DELETE FROM sales_drafts WHERE draft_id = ?");
    $delete->bind_param("s", $receipt_id);
    $delete->execute();
    $delete->close();

    echo "Order marked as paid and moved to sales.";
} else {
    echo "Error inserting into sales: " . $conn->error;
}

$conn->close();
?>
