<?php
session_start();
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username   = $_POST['username'];
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $email      = $_POST['email'];
    $gender     = $_POST['gender'];
    $mobile     = $_POST['mobile'];
    $userrole   = $_POST['userrole'];

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    /* ===== PHOTO HANDLING (LONGBLOB) ===== */
    $photoData = null;

    // File upload
    if (!empty($_FILES['photo']['tmp_name'])) {
        $photoData = file_get_contents($_FILES['photo']['tmp_name']);
    }

    // Webcam capture (base64)
    elseif (!empty($_POST['webcam_photo'])) {
        $webcam = $_POST['webcam_photo'];
        $webcam = str_replace('data:image/jpeg;base64,', '', $webcam);
        $webcam = str_replace(' ', '+', $webcam);
        $photoData = base64_decode($webcam);
    }

    /* ===== INSERT USER ===== */
    $sql = "INSERT INTO tblusers
        (username, first_name, last_name, email, password, gender, mobile, photo, userrole)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssss",
        $username,
        $first_name,
        $last_name,
        $email,
        $password,
        $gender,
        $mobile,
        $photoData,
        $userrole
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "User registered successfully";
        header("Location: view_user.php");
        exit;
    } else {
        $_SESSION['error'] = "Registration failed";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.css" type="text/css">
    <link rel="stylesheet" href="../assets/css/forms.css" type="text/css">
    <style>
        :root {
            --primary-color: #0056b3;
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

        form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow-light);
        }

        .main-content {
            padding: 20px;
            max-width: 700px;
            margin: 20px auto;
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 10px var(--shadow-light);
        }

        h2 {
            color: linear-gradient(135deg, #1a2a6c, #2b5876);
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
        input[type="date"],
        input[type="file"],
        select,
        video,
        canvas {
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
        input[type="date"]:focus,
        input[type="file"]:focus,
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
            background: linear-gradient(135deg, #1a2a6c, #2b5876);
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
            background: #FF9966;
            transform: translateY(-2px);
        }

        .custom-submit-btn:active {
            transform: translateY(0);
        }

        .webcam-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        #capture-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #capture-btn:hover {
            background-color: #004085;
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
        <div>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
        </div>
        <h2><?php echo htmlspecialchars($page_title); ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <input name="username" placeholder="Username" required><br><br>
            <input name="first_name" placeholder="First Name" required><br><br>
            <input name="last_name" placeholder="Last Name" required><br><br>
            <input name="email" type="email" placeholder="Email" required><br><br>
            <input name="mobile" placeholder="Mobile"><br><br>

            <select name="gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select><br><br>

            <select name="userrole">
                <option value="Admin">Admin</option>
                <option value="User">User</option>
            </select><br><br>

            <input type="password" name="password" placeholder="Password" required><br><br>

            <label>Upload Photo</label><br>
            <input type="file" name="photo" accept="image/*"><br><br>

            <!-- Optional webcam -->
            <input type="hidden" name="webcam_photo" id="webcam_photo">

            <button type="submit">Register</button>
        </form>
    </div>

    <script src="../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Webcam capture functionality
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const preview = document.getElementById('preview');
        const startWebcamBtn = document.getElementById('start-webcam');
        const captureBtn = document.getElementById('capture-btn');
        const webcamPhotoInput = document.getElementById('webcam_photo');
        const photoInput = document.getElementById('photo');

        startWebcamBtn.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                video.style.display = 'block';
                captureBtn.style.display = 'block';
                startWebcamBtn.style.display = 'none';
                preview.style.display = 'none';
                photoInput.value = ''; // Clear file input if webcam is used
            } catch (err) {
                alert('Error accessing webcam: ' + err.message);
            }
        });

        captureBtn.addEventListener('click', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/jpeg');
            preview.src = dataUrl;
            preview.style.display = 'block';
            webcamPhotoInput.value = dataUrl;
            video.style.display = 'none';
            captureBtn.style.display = 'none';
            startWebcamBtn.style.display = 'block';
            photoInput.value = ''; // Clear file input if webcam is used

            // Stop webcam stream
            video.srcObject.getTracks().forEach(track => track.stop());
        });

        // Clear webcam photo if file input is used
        photoInput.addEventListener('change', () => {
            if (photoInput.files.length > 0) {
                webcamPhotoInput.value = '';
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>