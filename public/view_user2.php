<?php
session_start();
require_once '../includes/config.php';

if (!isset($_GET['id'])) {
    die("User ID not provided");
}

$user_id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM tblusers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found");
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View User</title>
    <style>
        .profile-pic {
            width:150px;
            height:150px;
            border-radius:50%;
            object-fit:cover;
            border:2px solid #ccc;
        }
        table {
            border-collapse: collapse;
            width: 60%;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        td:first-child {
            font-weight: bold;
            width: 30%;
        }
    </style>
</head>
<body>

<h2>User Details</h2>

<!-- PHOTO -->
<?php if (!empty($user['photo'])): ?>
    <img class="profile-pic"
         src="data:image/jpeg;base64,<?= base64_encode($user['photo']) ?>">
<?php else: ?>
    <p><em>No photo available</em></p>
<?php endif; ?>

<br><br>

<table>
    <tr>
        <td>Full Name</td>
        <td><?= htmlspecialchars($user['full_name']) ?></td>
    </tr>
    <tr>
        <td>Username</td>
        <td><?= htmlspecialchars($user['username']) ?></td>
    </tr>
    <tr>
        <td>Email</td>
        <td><?= htmlspecialchars($user['email']) ?></td>
    </tr>
    <tr>
        <td>Gender</td>
        <td><?= htmlspecialchars($user['gender']) ?></td>
    </tr>
    <tr>
        <td>Mobile</td>
        <td><?= htmlspecialchars($user['mobile']) ?></td>
    </tr>
    <tr>
        <td>User Role</td>
        <td><?= htmlspecialchars($user['userrole']) ?></td>
    </tr>
    <tr>
        <td>Status</td>
        <td><?= htmlspecialchars($user['status']) ?></td>
    </tr>
    <tr>
        <td>Created At</td>
        <td><?= htmlspecialchars($user['created_at']) ?></td>
    </tr>
</table>

<br>

<a href="update_user.php?id=<?= $user['user_id'] ?>">Edit User</a>

</body>
</html>
