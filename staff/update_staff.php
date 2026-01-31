<?php
ob_start();
include '../includes/config.php';
include '../includes/header.php';

// Initialize $staff to an empty array
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
    echo "<p class='error'>Error: Staff ID is missing. Please select a staff to edit from the <a href='staffslist.php'>Staff List</a>.</p>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $staff_id = $_POST['staff_id'];
    $date_of_joining = $_POST['date_of_joining'];
    $job_title = $_POST['job_title'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $nick_name = $_POST['nick_name'];
    $sex = $_POST['sex'];
    $email = $_POST['email'];
    $dob = $_POST['dob'];
    $marital_status = $_POST['marital_status'];
    $religion = $_POST['religion'];
    $id_number = $_POST['id_number'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $current_status = $_POST['current_status'];
    $created_date = $_POST['created_date'];

    // Basic validation
    if (empty($date_of_joining) || empty($job_title) || empty($first_name) || empty($last_name) || empty($sex) || empty($email) || empty($dob) || empty($marital_status) || empty($id_number) || empty($phone) || empty($address) || empty($current_status) || empty($created_date)) {
        $error = "All required fields must be filled.";
    } else {
        // Handle file upload
        $photo_path = $staff['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../Uploads/staff/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_name = $staff['staff_number'] . '.' . $file_ext;
            $target_file = $target_dir . $photo_name;
            $check = getimagesize($_FILES['photo']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    $photo_path = $photo_name;
                } else {
                    $error = "Error uploading photo.";
                }
            } else {
                $error = "File is not a valid image.";
            }
        }

        if (!isset($error)) {
            $sql = "UPDATE staff SET date_of_joining = ?, job_title = ?, first_name = ?, last_name = ?, nick_name = ?, sex = ?, email = ?, dob = ?, marital_status = ?, religion = ?, id_number = ?, phone = ?, address = ?, current_status = ?,  photo = ? WHERE staff_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssssssssssssi', $date_of_joining, $job_title, $first_name, $last_name, $nick_name,
                                                    $sex,  $email, $dob, $marital_status, $religion, $id_number,
                                                    $phone, $address, $current_status,  $photo_path, $staff_id);

            if ($stmt->execute()) {
                header("Location: staffslist.php?success=staff_updated");
                exit();
            } else {
                $error = "Error updating staff: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>

        .form-container {
            position: relative;
            width: 70%;
            margin: 20px auto;
            padding: 20px;
            background-color: #ccccff;
            border-radius: 8px;
            box-shadow: 12px 18px #808080;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
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
            vertical-align: top;
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
        .btn-primary {
            background-color: #000099;
            border: none;
        }
        .form-control, select, textarea {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="form-container">
        <h2>Edit Staff</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($staff)): ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff['staff_id']); ?>">
                <table>
                    <tr>
                        <th>Detail</th>
                        <th>Value</th>
                        <th>Detail</th>
                        <th>Value</th>
                        <th>Photo</th>
                    </tr>
                    <tr>
                        <td>Staff ID</td>
                        <td><input type="text" class="form-control" name="staff_id" value="<?php echo htmlspecialchars($staff['staff_id']); ?>" readonly></td>
                        <td>Staff Number</td>
                        <td><input type="text" class="form-control" name="staff_number" value="<?php echo htmlspecialchars($staff['staff_number']); ?>" readonly></td>
                        <td rowspan="9" class="photo-column">
                            <?php if (!empty($staff['photo'])): ?>
                                <img src="../Uploads/staff/<?php echo htmlspecialchars($staff['photo']); ?>" alt="Current Photo">
                                <p>Current Photo</p>
                            <?php else: ?>
                                <p>No Photo</p>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        </td>
                    </tr>
                    <tr>
                        <td>Date of Joining</td>
                        <td><input type="date" class="form-control" name="date_of_joining" value="<?php echo htmlspecialchars($staff['date_of_joining']); ?>" required></td>
                        <td>Job Title</td>
                        <td><input type="text" class="form-control" name="job_title" value="<?php echo htmlspecialchars($staff['job_title']); ?>" required></td>
                    </tr>
                    <tr>
                        <td>First Name</td>
                        <td><input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($staff['first_name']); ?>" required></td>
                        <td>Last Name</td>
                        <td><input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($staff['last_name']); ?>" required></td>
                    </tr>
                    <tr>
                        <td>Nick Name</td>
                        <td><input type="text" class="form-control" name="nick_name" value="<?php echo htmlspecialchars($staff['nick_name']); ?>"></td>
                        <td>Email</td>
                        <td><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required></td>
                    </tr>
                    <tr>
                        <td>Sex</td>
                        <td>
                            <select class="form-control" name="sex" required>
                                <option value="Male" <?php echo $staff['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $staff['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </td>

                    </tr>
                    <tr>
                        <td>Date of Birth</td>
                        <td><input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($staff['dob']); ?>" required></td>
                        <td>Marital Status</td>
                        <td>
                            <select class="form-control" name="marital_status" required>
                                <option value="Single" <?php echo $staff['marital_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo $staff['marital_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo $staff['marital_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo $staff['marital_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Religion</td>
                        <td><input type="text" class="form-control" name="religion" value="<?php echo htmlspecialchars($staff['religion']); ?>"></td>
                        <td>ID Number</td>
                        <td><input type="text" class="form-control" name="id_number" value="<?php echo htmlspecialchars($staff['id_number']); ?>" required></td>
                    </tr>
                    <tr>
                        <td>Phone</td>
                        <td><input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>" required></td>
                        <td>Address</td>
                        <td><textarea class="form-control" name="address" required><?php echo htmlspecialchars($staff['address']); ?></textarea></td>
                    </tr>
                    <tr>
                        <td>Current Status</td>
                        <td>
                            <select class="form-control" name="current_status" required>
                                <option value="Active" <?php echo $staff['current_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $staff['current_status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </td>
                        <td>Date Created</td>
                        <td><input type="text" class="form-control" name="created_date" value="<?php echo htmlspecialchars($staff['created_date']); ?>" required></td>
                    </tr>
                </table>
                <div style="margin-top: 20px;">
                    <button type="submit" name="submit" class="btn btn-primary">Update Staff</button>
                    <a href="staffslist.php" class="btn btn-secondary">Back to Staff List</a>
                </div>
            </form>
        <?php else: ?>
            <p>Staff not found.</p>
            <a href="staffslist.php" class="btn btn-secondary">Back to Staff List</a>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>