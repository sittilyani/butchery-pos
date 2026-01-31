<?php
include '../includes/config.php';
include '../includes/header.php';

if (isset($_GET['id'])) {
    $supplierId = intval($_GET['id']); // Correct variable and force it to an integer for safety

    // Delete supplier from the database
    $sqlDeleteSupplier = "DELETE FROM suppliers WHERE supplier_id = ?";
    $stmtDeleteSupplier = $conn->prepare($sqlDeleteSupplier);

    if ($stmtDeleteSupplier) {
        $stmtDeleteSupplier->bind_param('i', $supplierId);

        if ($stmtDeleteSupplier->execute()) {
            $_SESSION['success_message'] = "Supplier deleted successfully!";
            echo '<script>
                setTimeout(function() {
                    window.location.href = "../products/view_suppliers.php";
                }, 3000);
            </script>';
            exit;
        } else {
            $_SESSION['error_message'] = "Error deleting supplier: " . $stmtDeleteSupplier->error;
        }
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "No supplier ID specified.";
    header("Location: ../products/view_suppliers.php");
    exit;
}
?>
