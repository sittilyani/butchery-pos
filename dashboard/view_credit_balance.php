<?php
session_start();
include '../includes/config.php';
include '../includes/header.php';

$result = $conn->query("SELECT * FROM credit_balances
                        /*where payment_status = 'paid'*/
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
    <title>Credit Balances</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .main-content{
            position: flex;
            z-index: -1;
        }

    </style>
</head>
<body>
<div class="main-content" style="min-width: 90%; margin-top: 10px;";>
    <h2>Creditors</h2>
    <table class="table table-bordered" style="width: 90%";>
        <thead>
            <tr>
                <th>ID</th>
                <th>Receipt ID</th>
                <th>Customer Name</th>
                <th>Balance</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['receipt_id'] ?></td>
                    <td><?= $row['customer_name'] ?></td>
                    <td><?= $row['balance_amount'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-success mark-paid-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Mark as Paid</button>
                        <button class="btn btn-sm btn-danger delete-btn" data-receipt-id="<?= $row['receipt_id'] ?>">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</script>
</body>
</html>
