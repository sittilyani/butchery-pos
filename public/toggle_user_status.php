<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("location: login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];

    // Prevent user from disabling themselves
    if ($user_id == $_SESSION['user_id']) {
        header("Location: ../public/userslist.php?error=You cannot disable your own account");
        exit;
    }

    $new_status = ($action == 'disable') ? 0 : 1;
    $status_text = ($action == 'disable') ? 'disabled' : 'enabled';

    $stmt = $conn->prepare("UPDATE tblusers SET is_active = ? WHERE user_id = ?");
    $stmt->bind_param('ii', $new_status, $user_id);

    if ($stmt->execute()) {
        header("Location: ../public/userslist.php?success=User successfully $status_text");
    } else {
        header("Location: ../public/userslist.php?error=Error updating user status");
    }
    $stmt->close();
} else {
    header("Location: ../public/userslist.php?error=Invalid request");
}

exit;
?>