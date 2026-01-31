<?php
include '../includes/config.php';
include '../includes/header.php';

$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $supplierId = intval($_POST['supplier_id']);
    $newname = $_POST['newname'];
    $newcontact_person = $_POST['newcontact_person'];
    $newphone = $_POST['newphone'];
    $newemail = $_POST['newemail'];
    $newaddress = $_POST['newaddress'];

    // Update supplier details
    $sqlUpdateSupplier = "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE supplier_id = ?";
    $stmtUpdateSupplier = $conn->prepare($sqlUpdateSupplier);
    $stmtUpdateSupplier->bind_param('sssssi', $newname, $newcontact_person, $newphone, $newemail, $newaddress, $supplierId);

    if ($stmtUpdateSupplier->execute()) {
        $_SESSION['success_message'] = "Supplier updated successfully!";
        echo '<script>
            setTimeout(function() {
                window.location.href = "../views/view_suppliers.php";
            }, 3000);
        </script>';
        exit;
    } else {
        $_SESSION['error_message'] = "Error updating supplier: " . $conn->error;
    }
}

// Retrieve supplier details
$supplier = [];
if ($supplierId > 0) {
    $sql = "SELECT name, contact_person, phone, email, address FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();

    if (!$supplier) {
        $_SESSION['error_message'] = "Supplier not found.";
        header("Location: ../views/view_suppliers.php");
        exit;
    }
} else {
    $_SESSION['error_message'] = "Invalid Supplier ID.";
    header("Location: ../views/view_suppliers.php");
    exit;
}
?>

<!-- HTML Form for updating supplier -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Supplier</title>
    <style>
        :root {
            --primary-color: #000099;
            --secondary-color: #6c757d;
            --background-light: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --success-bg-color: #d4edda;
            --text-color: #343a40;
            --input-border: #ced4da;
            --input-focus-border: #80bdff;
            --shadow-light: rgba(0, 0, 0, 0.1);
            --font-family: 'Arial', sans-serif;
        }

        .main-content {
            padding: 20px;
            width: 800px;
            margin: 20px auto;
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 10px var(--shadow-light);
        }

        h2 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .alert-success {
            background-color: var(--success-bg-color);
            color: var(--success-color);
            border-color: var(--success-color);
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #dc3545;
            border-color: #f5c6cb;
        }

        form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            padding: 20px;
            background-color: #66ccff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow-light);
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-color);
        }

        input[type="text"],
        input[type="number"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--input-border);
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .readonly-input {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .custom-submit-btn {
            grid-column: 1 / -1;
            padding: 15px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .custom-submit-btn:hover {
            background-color: #004085;
            transform: translateY(-2px);
        }

        .custom-submit-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 992px) {
            form {
                grid-template-columns: repeat(2, 1fr);
            }
            .custom-submit-btn {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="main-content">
<h2>Supplier Details</h2>

<form action="update_supplier.php?id=<?php echo $supplierId; ?>" method="post">
    <div class='form-group'>
        <label for="newname">Supplier Name:</label>
        <input type="text" id="newname" name="newname" value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
    </div>

    <div class='form-group'>
        <label for="newcontact_person">Contact Person:</label>
        <input type="text" id="newcontact_person" name="newcontact_person" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>">
    </div>

    <div class='form-group'>
        <label for="newphone">Mobile Phone:</label>
        <input type="text" id="newphone" name="newphone" value="<?php echo htmlspecialchars($supplier['phone']); ?>">
    </div>

    <div class='form-group'>
        <label for="newemail">Email:</label>
        <input type="email" class="newemail" name="newemail" value="<?php echo htmlspecialchars($supplier['email']); ?>">
    </div>

    <div class='form-group'>
        <label for="newaddress">Address:</label>
        <input type="text" id="newaddress" name="newaddress" value="<?php echo htmlspecialchars($supplier['address']); ?>">
    </div>

    <input type="hidden" name="supplier_id" value="<?php echo $supplierId; ?>">

    <button type="submit" class="custom-submit-btn" name="submit">Update Supplier</button>
</form>

</body>
</html>
