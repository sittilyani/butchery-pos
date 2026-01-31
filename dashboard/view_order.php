<?php
session_start();
include '../includes/config.php';
include '../includes/header.php';

$result = $conn->query("SELECT * FROM sales_drafts
                        where payment_status = 'pending'
                        ORDER BY created_at DESC");
?>
<?php
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    echo "<div>" . $message . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Orders</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="main-content">

    <h2>Pending Orders</h2>
    <table class="table table-bordered" style="width: 90%";>
        <thead>
            <tr>
                <th>Draft ID</th>
                <th>Receipt ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Tax</th>
                <th>Grand Total</th>
                <th>Tendered</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['draft_id'] ?></td>
                    <td><?= $row['receipt_id'] ?></td>
                    <td><?= $row['product_name'] ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= $row['total_amount'] ?></td>
                    <td><?= $row['tax_amount'] ?></td>
                    <td><?= $row['grand_total'] ?></td>
                    <td><?= $row['tendered_amount'] ?></td>
                    <td><?= $row['payment_method'] ?></td>
                    <td><?= $row['payment_status'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Edit</button>
                        <button class="btn btn-sm btn-success mark-paid-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Mark as Paid</button>
                        <button class="btn btn-sm btn-warning update-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Update</button>
                        <button class="btn btn-sm btn-danger delete-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
$('.edit-btn').click(function () {
    const draftId = $(this).data('receipt-id');
    if (!draftId) return alert("Receipt ID missing.");
    window.location.href = 'edit_order.php?receipt_id=' + receiptId;
});

$('.mark-paid-btn').click(function () {
    const receiptId = $(this).data('receipt-id');
    if (!receiptId) return alert("Receipt ID missing.");
    if (!confirm("Mark this order as paid?")) return;
    window.location.href = 'mark_paid.php?receipt_id=' + draftId;
});

$('.update-btn').click(function () {
    const receiptId = $(this).data('receipt-id');
    if (!receiptId) return alert("Receipt ID missing.");
    window.location.href = 'edit_order.php?receipt_id=' + receiptId;
});

$('.delete-btn').click(function () {
    const receiptId = $(this).data('receipt-id');
    if (!receiptId) return alert("Receipt ID missing.");
    if (!confirm("Are you sure you want to delete this draft?")) return;
    window.location.href = 'delete_order.php?receipt_id=' + receiptId;
});
</script>
</body>
</html>
