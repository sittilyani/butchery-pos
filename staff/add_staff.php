<?php
session_start();
include '../includes/config.php';
include '../includes/header.php';

// Function to generate the next staff number
function generateStaffNumber($conn) {
    // Get the last staff number from the database
    $sql = "SELECT staff_number FROM staff ORDER BY staff_id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $last_staff = $result->fetch_assoc();
        $last_number = substr($last_staff['staff_number'], 3); // Remove 'BSP' prefix
        $next_number = str_pad($last_number + 1, 5, '0', STR_PAD_LEFT);
        return 'BSP' . $next_number;
    } else {
        // If no staff exists yet, start with BSP00001
        return 'BSP00001';
    }
}

//for page title to be echoed
$page_title = "Add New Staff";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Auto-generate staff number instead of getting from POST
    $staff_number = generateStaffNumber($conn);

    // Retrieve and sanitize other data from the form
    $date_of_joining = mysqli_real_escape_string($conn, $_POST['date_of_joining']);
    $job_title = mysqli_real_escape_string($conn, $_POST['job_title']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $nick_name = mysqli_real_escape_string($conn, $_POST['nick_name']);
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $marital_status = mysqli_real_escape_string($conn, $_POST['marital_status']);
    $religion = mysqli_real_escape_string($conn, $_POST['religion']);
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $current_status = mysqli_real_escape_string($conn, $_POST['current_status']);

    // Handle file upload
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/staff/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_name = $staff_number . '.' . $file_ext;
        $target_file = $target_dir . $photo_name;

        // Check if image file is actual image
        $check = getimagesize($_FILES['photo']['tmp_name']);
        if ($check !== false) {
            move_uploaded_file($_FILES['photo']['tmp_name'], $target_file);
            $photo_path = $photo_name;
        }
    }

    // Insert data into staff table
    $sql = "INSERT INTO staff (date_of_joining, staff_number, job_title, first_name, last_name, nick_name, sex, username, email, dob, marital_status, religion, id_number, phone, address, current_status, photo)
    VALUES ('$date_of_joining', '$staff_number', '$job_title', '$first_name', '$last_name', '$nick_name', '$sex', '$username', '$email', '$dob', '$marital_status', '$religion', '$id_number', '$phone', '$address', '$current_status', '$photo_path')";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "New staff added successfully with Staff Number: $staff_number";
    } else {
        $_SESSION['error_message'] = "Error: " . $conn->error;
    }
}

// Generate the next staff number when loading the form
$next_staff_number = generateStaffNumber($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Staff - POS System</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS Variables for easy theme changes */
        :root {
            --primary-color: #000099; /* Darker blue for primary actions */
            --secondary-color: #6c757d; /* Grey for secondary elements */
            --background-light: #f8f9fa; /* Light background for overall page */
            --card-background: #ffffff; /* White for form background */
            --border-color: #dee2e6;
            --success-color: #28a745;
            --success-bg-color: #d4edda;
            --text-color: #343a40;
            --input-border: #ced4da;
            --input-focus-border: #80bdff;
            --shadow-light: rgba(0, 0, 0, 0.1);
            --font-family: 'Arial', sans-serif; /* Changed from Times New Roman for a modern look */
        }

        .main-content {
            padding: 20px;
            max-width: 90%;
            margin: 20px auto; /* Center the main content */
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

        #success-message {
            background-color: var(--success-bg-color);
            color: var(--success-color);
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid var(--success-color);
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        #success-message .fas {
            font-size: 1.2em;
        }


        form {
            display: grid;
            grid-template-columns: repeat(5, 1fr); /* Three equal columns */
            gap: 25px; /* Spacing between columns and rows */
            padding: 20px;
            background-color: #CCCCFF; /* Original light yellow background */
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
        input[type="phone"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--input-border);
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; /* Include padding in width */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus {
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .readonly-input {
            background-color: #e9ecef; /* Light gray for readonly fields */
            cursor: not-allowed;
        }

        .custom-submit-btn {
            grid-column: 1 / -1; /* Make the button span all three columns */
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
            background-color: #004085; /* Darker shade on hover */
            transform: translateY(-2px); /* Slight lift effect */
        }

        .custom-submit-btn:active {
            transform: translateY(0);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            form {
                grid-template-columns: repeat(2, 1fr); /* Two columns on medium screens */
            }
            .custom-submit-btn {
                grid-column: 1 / -1; /* Still span full width */
            }
        }

        @media (max-width: 768px) {
            form {
                grid-template-columns: 1fr; /* Single column on small screens */
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <h2 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?></h2>

            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data" class="form-container">

                        <div class="form-group">
                            <label for="date_of_joining">Date of Joining</label>
                            <input type="date" id="date_of_joining" name="date_of_joining" required>
                        </div>

                        <div class="form-group">
                            <label for="staff_number">Staff Number</label>
                            <input type="text" id="staff_number" name="staff_number" value="<?php echo $next_staff_number; ?>" readonly class="read-only">
                        </div>

                        <div class="form-group">
                            <label for="job_title">Job Title</label>
                            <select id="job_title" name="job_title" required>
                                <option value="">Select Position</option>
                                <option value="Admin">Admin</option>
                                <option value="Manager">Manager</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Pharmaceutical Technologist">Pharmaceutical Technologist</option>
                                <option value="Pharmacist">Pharmacist</option>
                                <option value="Security">Security</option>
                                <option value="Cleaner">Cleaner</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="nick_name">Nick Name</label>
                            <input type="text" id="nick_name" name="nick_name">
                        </div>

                        <div class="form-group">
                            <label for="sex">Gender</label>
                            <select id="sex" name="sex" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>

                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" required>
                        </div>

                        <div class="form-group">
                            <label for="marital_status">Marital Status</label>
                            <select id="marital_status" name="marital_status">
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="religion">Religion</label>
                            <input type="text" id="religion" name="religion">
                        </div>

                        <div class="form-group">
                            <label for="id_number">ID Number</label>
                            <input type="text" id="id_number" name="id_number" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Mobile Number</label>
                            <input type="phone" id="phone" name="phone" required>
                        </div>

                        <div class="form-group">
                            <label for="current_status">Current Status</label>
                            <select id="current_status" name="current_status" required>
                                <option value="">Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Resigned">Resigned</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="photo">Photo Upload</label>
                            <input type="file" id="photo" name="photo" accept="image/*">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" name="address">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="custom-submit-btn">Add new staff</button>
                        </div>
            </form>
        </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set today's date as default for date of joining
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_of_joining').value = today;
        });
    </script>
</body>
</html>