<?php
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    $photo = null;

    // Handle photo upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) { // 2MB max
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
        } else {
            $_SESSION['error'] = "Invalid file! Only jpg/png/gif = 2MB allowed.";
            $redirect = ($action === 'edit') ? "edit.php?id=$category_id" : "add.php";
            header("Location: $redirect");
            exit;
        }
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("
            INSERT INTO categories (name, photo, is_active)
            VALUES (?, ?, 1)
        ");
        $stmt->bind_param("sb", $name, $photo);
        $stmt->send_long_data(1, $photo); // Important for large binary data
        $success = $stmt->execute();
    } else { // edit
        if ($photo !== null) {
            $stmt = $conn->prepare("
                UPDATE categories
                SET name = ?, photo = ?
                WHERE category_id = ?
            ");
            $stmt->bind_param("sbi", $name, $photo, $category_id);
            $stmt->send_long_data(1, $photo);
        } else {
            $stmt = $conn->prepare("
                UPDATE categories
                SET name = ?
                WHERE category_id = ?
            ");
            $stmt->bind_param("si", $name, $category_id);
        }
        $success = $stmt->execute();
    }

    if ($success) {
        $_SESSION['success'] = ($action === 'add') ? "Category added successfully!" : "Category updated!";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: index.php");
    exit;
}

// Toggle active/inactive
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $result = $conn->query("SELECT is_active FROM categories WHERE category_id = $id");
    $row = $result->fetch_assoc();
    $new_status = $row['is_active'] ? 0 : 1;

    $conn->query("UPDATE categories SET is_active = $new_status WHERE category_id = $id");

    $_SESSION['success'] = "Category status updated!";
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;