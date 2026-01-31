<?php
ob_start();
include '../includes/config.php';
include '../includes/header.php';

// Initialize $staff to an empty array to avoid warnings
$staff = [];

if (isset($_GET['staff_id'])) {
    $staff_id = $_GET['staff_id'];

    $sql = "SELECT staff_id, staff_number, date_of_joining, job_title, first_name, last_name, nick_name, sex, email, dob, marital_status, religion, id_number, phone, address, current_status, photo, created_date FROM staff WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
    } else {
        echo "<p class='error'>Error: Staff not found for ID $staff_id. <a href='staffslist.php'>Back to Staff List</a></p>";
        exit();
    }
    $stmt->close();
} else {
    echo "<p class='error'>Error: Staff ID is missing. Please select a staff to view from the <a href='staffslist.php'>Staff List</a>.</p>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff details</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>


        .error {
            color: red;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #00246B;
            color: white;
        }
        .photo-column img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
        }
        .btn-edit {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .staff-view{
            margin-top: 30px;
            margin-left: auto;
            margin-right: auto;
            background-color: #ccccff;
            width: 60%;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 10px  7px 6px #808080;
        }

    </style>
</head>
<body>
    <div class="main-content">
        <div class="staff-view">
        <h2>Staff details for <?php echo htmlspecialchars($staff['first_name']); ?> </h2>
        <?php if (!empty($staff)): ?>
            <table>
                <tr>
                    <th>Detail</th>
                    <th>Value</th>
                    <th>Detail</th>
                    <th>Value</th>
                    <th>Photo</th>
                </tr>
                <tr>
                    <td >Staff ID</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                    <td style='font-weight: bold;'>Staff Number</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['staff_number']); ?></td>
                    <td rowspan="12" class="photo-column">
                        <?php if (!empty($staff['photo'])): ?>
                            <img src="../Uploads/staff/<?php echo htmlspecialchars($staff['photo']); ?>" alt="Staff Photo">
                        <?php else: ?>
                            No Photo
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Date of Joining</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['date_of_joining']); ?></td>
                    <td>Job Title</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['job_title']); ?></td>
                </tr>
                <tr>
                    <td>First Name</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['first_name']); ?></td>
                    <td>Last Name</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['last_name']); ?></td>
                </tr>
                <tr>
                    <td>Nick Name</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['nick_name']); ?></td>
                    <td>Email</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['email']); ?></td>
                </tr>
                <tr>
                    <td>Sex</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['sex']); ?></td>
                </tr>
                <tr>
                    <td>Date of Birth</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['dob']); ?></td>
                    <td>Marital Status</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['marital_status']); ?></td>
                </tr>
                <tr>
                    <td>Religion</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['religion']); ?></td>
                    <td>ID Number</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['id_number']); ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['phone']); ?></td>
                    <td>Address</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['address']); ?></td>
                </tr>
                <tr>
                    <td>Current Status</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['current_status']); ?></td>
                    <td>Date Created</td>
                    <td style='font-weight: bold;'><?php echo htmlspecialchars($staff['created_date']); ?></td>
                </tr>
            </table>
            <div style="margin-top: 20px;">
                <a href="update_staff.php?staff_id=<?php echo htmlspecialchars($staff['staff_id']); ?>" class="btn-edit">Edit Staff</a>
                <a href="staffslist.php" class="btn btn-secondary">Back to Staff List</a>
            </div>
        <?php else: ?>
            <p>Staff not found.</p>
            <a href="staffslist.php" class="btn btn-secondary">Back to Staff List</a>
        <?php endif; ?>
    </div>
    </div>
</body>
</html>