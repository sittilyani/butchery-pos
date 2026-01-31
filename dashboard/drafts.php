<?php
// drafts.php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../includes/config.php";
include "../includes/header.php";

$page_title = "Order Drafts";

// Fetch drafts
$drafts = [];
$query = "SELECT * FROM sales_drafts";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $drafts[] = $row;
}
?>

<div class="main-content">
    <div class="container">
        <h2 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?></h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Receipt ID</th>
                    <th>Waiter</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drafts as $draft): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($draft['receipt_id']); ?></td>
                        <td><?php echo htmlspecialchars($draft['waiter_name']); ?></td>
                        <td><?php echo htmlspecialchars($draft['customer_name'] ?: 'N/A'); ?></td>
                        <td>$<?php echo number_format($draft['grand_total'], 2); ?></td>
                        <td><?php echo htmlspecialchars($draft['created_at']); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm edit-draft" data-draft='<?php echo json_encode($draft); ?>'>Edit</button>
                            <button class="btn btn-danger btn-sm delete-draft" data-id="<?php echo $draft['draft_id']; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../includes/footer.php"; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="../assets/js/bootstrap.min.js"></script>
<script src="../assets/js/functions.js"></script>
<script src="../assets/js/headerstyle.js"></script>
<script>
$(document).ready(function() {
    $('.edit-draft').click(function() {
        const draft = $(this).data('draft');
        localStorage.setItem('editDraft', JSON.stringify(draft));
        window.location.href = 'orders.php';
    });

    $('.delete-draft').click(function() {
        if (confirm('Delete this draft?')) {
            const draftId = $(this).data('id');
            $.ajax({
                url: 'delete_draft.php',
                method: 'POST',
                data: { draft_id: draftId },
                success: function(response) {
                    const res = JSON.parse(response);
                    alert(res.message);
                    if (res.status === 'success') {
                        window.location.reload();
                    }
                }
            });
        }
    });

    // Load draft on orders.php
    if (window.location.pathname.includes('orders.php')) {
        const draft = localStorage.getItem('editDraft');
        if (draft) {
            const draftData = JSON.parse(draft);
            $('#receipt_id').val(draftData.receipt_id);
            $('#waiter_name').val(draftData.waiter_name);
            $('#customer_name').val(draftData.customer_name);
            $('#customer_id').val(draftData.customer_id);
            $('#payment_method').val(draftData.payment_method);
            orderItems = JSON.parse(draftData.items);
            itemCounter = orderItems.length;
            updateOrderTable();
            localStorage.removeItem('editDraft');
        }
    }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>