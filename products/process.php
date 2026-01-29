<?php
session_start();
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? null;
    $buying_price = $_POST['buying_price'] ?? 0.00;
    $selling_price = $_POST['selling_price'] ?? 0.00;
    $reorder_level = $_POST['reorder_level'] ?? 10;

    if ($action === 'add') {
        $stmt = $conn->prepare("
            INSERT INTO products (name, category, buying_price, selling_price, reorder_level, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->bind_param("siddi", $name, $category, $buying_price, $selling_price, $reorder_level);
        $success = $stmt->execute();
    } else { // edit
        $id = $_POST['id'] ?? null;

        $stmt = $conn->prepare("
            UPDATE products
            SET name = ?, category = ?, buying_price = ?, selling_price = ?, reorder_level = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("siddii", $name, $category_id, $buying_price, $selling_price, $reorder_level, $id);
        $success = $stmt->execute();
    }

    if ($success) {
        $_SESSION['success'] = ($action === 'add') ? "Product added successfully!" : "Product updated!";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: index.php");
    exit;
}

// Toggle active/inactive
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $result = $conn->query("SELECT is_active FROM products WHERE id = $id");
    $row = $result->fetch_assoc();
    $new_status = $row['is_active'] ? 0 : 1;

    $conn->query("UPDATE products SET is_active = $new_status, updated_at = NOW() WHERE id = $id");

    $_SESSION['success'] = "Product status updated!";
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;