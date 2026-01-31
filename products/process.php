<?php
session_start();
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // ADD PRODUCT ACTION
        if ($_POST['action'] === 'add') {
            // Get form data
            $name = trim($_POST['name']);
            $category_id = floatval($_POST['category_id']);
            $category = trim($_POST['category']);
            $buying_price = floatval($_POST['buying_price']);
            $selling_price = floatval($_POST['selling_price']);
            $reorder_level = intval($_POST['reorder_level']);
            $initial_stock = isset($_POST['initial_stock']) ? floatval($_POST['initial_stock']) : 0;

            // Validate data
            if (empty($name) || empty($category)) {
                $_SESSION['error'] = "Product name and category are required.";
                header("Location: add_product.php");
                exit;
            }

            if ($buying_price <= 0 || $selling_price <= 0) {
                $_SESSION['error'] = "Prices must be greater than 0.";
                header("Location: add_product.php");
                exit;
            }

            if ($selling_price <= $buying_price) {
                $_SESSION['error'] = "Selling price must be greater than buying price.";
                header("Location: add_product.php");
                exit;
            }

            // Check if product already exists
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE name = ?");
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = "A product with this name already exists.";
                header("Location: add_product.php");
                exit;
            }

            // Insert product - FIXED: Removed category_id parameter since it's not in your form
            $stmt = $conn->prepare("INSERT INTO products (name, category_id, category, buying_price, selling_price, reorder_level, is_active)
                                   VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssddi", $name, $category_id, $category, $buying_price, $selling_price, $reorder_level);

            if ($stmt->execute()) {
                $product_id = $stmt->insert_id;

                $_SESSION['success'] = "Product added successfully!";
                header("Location: index.php"); // Redirect to products list
                exit;
            } else {
                $_SESSION['error'] = "Error adding product: " . $conn->error;
                header("Location: index.php");
                exit;
            }

            $stmt->close();
        }

        // EDIT PRODUCT ACTION
        if ($_POST['action'] === 'edit') {
            // Get form data
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $category_id = trim($_POST['category_id']);
            $category = trim($_POST['category']);
            $buying_price = floatval($_POST['buying_price']);
            $selling_price = floatval($_POST['selling_price']);
            $reorder_level = intval($_POST['reorder_level']);

            // Validate data
            if (empty($name) || empty($category)) {
                $_SESSION['error'] = "Product name and category are required.";
                header("Location: edit.php?id=" . $id);
                exit;
            }

            if ($buying_price <= 0 || $selling_price <= 0) {
                $_SESSION['error'] = "Prices must be greater than 0.";
                header("Location: edit.php?id=" . $id);
                exit;
            }

            if ($selling_price <= $buying_price) {
                $_SESSION['error'] = "Selling price must be greater than buying price.";
                header("Location: edit.php?id=" . $id);
                exit;
            }

            // Check if product name already exists (excluding current product)
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
            $check_stmt->bind_param("si", $name, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = "Another product with this name already exists.";
                header("Location: edit.php?id=" . $id);
                exit;
            }

            // Update product
            $stmt = $conn->prepare("UPDATE products SET
                                   name = ?,
                                   category_id = ?,
                                   category = ?,
                                   buying_price = ?,
                                   selling_price = ?,
                                   reorder_level = ?,
                                   updated_at = CURRENT_TIMESTAMP
                                   WHERE id = ?");
            $stmt->bind_param("ssddii", $name, $category, $buying_price, $selling_price, $reorder_level, $id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated successfully!";
                header("Location: edit.php?id=" . $id);
                exit;
            } else {
                $_SESSION['error'] = "Error updating product: " . $conn->error;
                header("Location: edit.php?id=" . $id);
                exit;
            }

            $stmt->close();
        }

        // DELETE PRODUCT ACTION
        if ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];

            // Soft delete by setting is_active to 0
            $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Product deleted successfully!";
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error'] = "Error deleting product: " . $conn->error;
                header("Location: index.php");
                exit;
            }

            $stmt->close();
        }

    }
} else {
    header("Location: index.php");
    exit;
}
?>