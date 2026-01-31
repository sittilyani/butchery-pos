<?php
session_start();
include '../includes/config.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("location: login.php");
    exit;
}

$user = null;
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM tblusers WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'];
    $mobile = trim($_POST['mobile']);
    $userrole = $_POST['userrole'];
    $full_name = $first_name . ' ' . $last_name;

    // Handle photo upload
    $photo_path = $user['photo'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/users/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if ($photo_path && file_exists($photo_path)) {
                    unlink($photo_path);
                }
                $photo_path = $upload_path;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE tblusers SET username=?, first_name=?, last_name=?, email=?, gender=?, mobile=?, userrole=?, photo=? WHERE user_id=?");
    $stmt->bind_param('ssssssssi', $username, $first_name, $last_name, $email, $gender, $mobile, $userrole, $photo_path, $user_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully!";
        header("Location: ../public/userslist.php?success=User updated successfully");
    } else {
        $_SESSION['error'] = "Error updating user: " . $conn->error;
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update User - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:40px 20px}
        .form-container{max-width:700px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);padding:40px;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        .form-header{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:2px solid #e9ecef}
        .form-header h2{color:#2c3e50;font-weight:700;margin:0;display:flex;align-items:center;justify-content:center;gap:10px}
        .form-header h2 i{color:#3498db}
        .photo-upload-section{text-align:center;margin-bottom:30px;padding:20px;background:#f8f9fa;border-radius:8px}
        .photo-preview{width:150px;height:150px;border-radius:50%;margin:0 auto 15px;border:4px solid #3498db;overflow:hidden;position:relative;background:#fff}
        .photo-preview img{width:100%;height:100%;object-fit:cover}
        .photo-preview.empty{display:flex;align-items:center;justify-content:center;background:#e9ecef}
        .photo-preview.empty i{font-size:60px;color:#adb5bd}
        .file-input-wrapper{position:relative;overflow:hidden;display:inline-block}
        .file-input-wrapper input[type=file]{position:absolute;left:-9999px}
        .file-input-label{background:#3498db;color:#fff;padding:10px 20px;border-radius:6px;cursor:pointer;transition:all .3s;display:inline-flex;align-items:center;gap:8px;font-weight:600}
        .file-input-label:hover{background:#2980b9;transform:translateY(-2px)}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;color:#2c3e50;font-size:.9rem}
        .form-group label i{margin-right:5px;color:#3498db}
        .form-group input[type=text],.form-group input[type=email],.form-group select{width:100%;padding:12px 15px;border:2px solid #e9ecef;border-radius:6px;font-size:.95rem;transition:all .3s}
        .form-group input[type=text]:focus,.form-group input[type=email]:focus,.form-group select:focus{border-color:#3498db;box-shadow:0 0 0 3px rgba(52,152,219,.1);outline:none}
        .form-group input[readonly]{background:#f8f9fa;cursor:not-allowed}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        .btn-group{display:flex;gap:15px;margin-top:30px}
        .btn{padding:12px 30px;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;font-size:.95rem}
        .btn-primary{background:linear-gradient(135deg,#3498db,#2980b9);color:#fff;flex:1}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(52,152,219,.3)}
        .btn-secondary{background:#6c757d;color:#fff;flex:1}
        .btn-secondary:hover{background:#5a6268;transform:translateY(-2px)}
        .alert{padding:15px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid;animation:slideIn .3s ease}
        .alert-success{background:#d4edda;color:#155724;border-color:#28a745}
        .alert-danger{background:#f8d7da;color:#721c24;border-color:#dc3545}
        @media (max-width:768px){
            .form-container{padding:25px}
            .form-row{grid-template-columns:1fr}
            .btn-group{flex-direction:column}
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-user-edit"></i>Update User Details</h2>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if($user): ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">

            <div class="photo-upload-section">
                <div class="photo-preview <?= empty($user['photo']) ? 'empty' : '' ?>" id="photoPreview">
                    <?php if(!empty($user['photo']) && file_exists($user['photo'])): ?>
                        <img src="<?= htmlspecialchars($user['photo']) ?>" alt="User Photo">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="file-input-wrapper">
                    <input type="file" name="photo" id="photoInput" accept="image/*">
                    <label for="photoInput" class="file-input-label">
                        <i class="fas fa-camera"></i>Change Photo
                    </label>
                </div>
                <div style="margin-top:10px;font-size:.85rem;color:#6c757d">
                    <i class="fas fa-info-circle"></i> JPG, PNG, GIF (Max 5MB)
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i>Gender</label>
                    <select name="gender" required>
                        <option value="Male" <?= $user['gender']=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= $user['gender']=='Female'?'selected':'' ?>>Female</option>
                        <option value="Other" <?= $user['gender']=='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i>Mobile</label>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($user['mobile']) ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user-tag"></i>User Role</label>
                <select name="userrole" required>
                    <?php
                    $result = $conn->query("SELECT id, userrole FROM userroles");
                    while ($row = $result->fetch_assoc()) {
                        $selected = ($row['userrole'] == $user['userrole']) ? 'selected' : '';
                        echo "<option value='{$row['userrole']}' $selected>{$row['userrole']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="btn-group">
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>Update User
                </button>
                <a href="../users/userslist.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>Cancel
                </a>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>User not found!
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('photoInput').addEventListener('change',function(e){
            const file=e.target.files[0];
            if(file){
                const reader=new FileReader();
                reader.onload=function(e){
                    const preview=document.getElementById('photoPreview');
                    preview.innerHTML='<img src="'+e.target.result+'" alt="Preview">';
                    preview.classList.remove('empty');
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>