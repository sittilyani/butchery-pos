<?php
include '../includes/config.php';
include '../includes/header.php';
// Fetch supplier data from the database
$sql = "SELECT * FROM suppliers";
$result = $conn->query($sql);
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>suppliers List</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.css" type="text/css">
    <link rel="icon" href="../assets/favicons/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/favicons/favicon.ico" type="image/x-icon">
    <script src="../assets/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-color: #000099;
            --secondary-color: #2980b9;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
        }
        .suppliers {
           width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            overflow: hidden;
        }

        .suppliers h2 {
            color: var(--dark-color);
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .suppliers h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        thead {
            background: #000099;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 5px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 5px;
            font-size: 0.8rem;
        }

        .btn-update {
            background-color: var(--warning-color);
            color: white;
            border: none;
        }

        .btn-update:hover {
            background-color: #e67e22;
            transform: translateY(-1px);
        }

        .btn-delete {
            background-color: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-delete:hover {
            background-color: #c0392b;
            transform: translateY(-1px);
        }

        .btn-view {
            background-color: var(--success-color);
            color: white;
            border: none;
        }

        .btn-view:hover {
            background-color: #27ae60;
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        /* Responsive table */
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .btn {
                margin-bottom: 5px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        /* Status indicators */
        .status-active {
            color: var(--success-color);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--danger-color);
            font-weight: 600;
        }

        /* Add supplier button */
        .add-supplier-btn {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .add-supplier-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            color: white;
        }

        .add-supplier-btn i {
            margin-right: 8px;
        }

        /* Table row animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        tbody tr {
            animation: fadeIn 0.3s ease forwards;
        }

        tbody tr:nth-child(odd) {
            background-color: rgba(0, 0, 0, 0.01);
        }
    </style>
</head>
<div class="main-content">
    <div class="suppliers">
        <a href="../stocks/suppliers.php" class="add-supplier-btn">
            <i class="fas fa-supplier-plus"></i> Add New supplier
        </a>

        <table class="table-responsive">
            <thead>
                <tr>
                    <th>Sup ID</th>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Mobile Phone</th>
                    <th>Address</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($supplier['supplier_id']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($supplier['date_created'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-update" onclick="location.href='../products/update_supplier.php?supplier_id=<?php echo $supplier['supplier_id']; ?>'">
                                    Update
                                </button>
                                <button class="btn btn-delete" onclick="if(confirm('Are you sure you want to delete this supplier?')) location.href='../products/delete_supplier.php?supplier_id=<?php echo $supplier['supplier_id']; ?>'">
                                    Delete
                                </button>
                                <button class="btn btn-view" onclick="location.href='view_supplier.php?supplier_id=<?php echo $supplier['supplier_id']; ?>'">
                                    View
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


