<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php'; 

$result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.css" type="text/css">
    <style>
        .category-img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; }
        .disabled-row { background-color: #f8d7da; opacity: 0.8; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between mb-4">
        <h2>Categories</h2>
        <a href="add.php" class="btn btn-primary">+ New Category</a>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($categories)): ?>
                <tr><td colspan="5" class="text-center py-4">No categories added yet.</td></tr>
            <?php else: ?>
                <?php foreach($categories as $i => $cat): ?>
                <tr class="<?= $cat['is_active'] ? '' : 'disabled-row' ?>">
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?php if($cat['photo']): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($cat['photo']) ?>"
                                 class="category-img" alt="<?= htmlspecialchars($cat['name']) ?>">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td>
                        <span class="badge <?= $cat['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $cat['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td>
                        <a href="edit.php?id=<?= $cat['category_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="process.php?action=toggle_status&id=<?= $cat['category_id'] ?>"
                           class="btn btn-sm <?= $cat['is_active'] ? 'btn-secondary' : 'btn-success' ?>"
                           onclick="return confirm('Are you sure?')">
                            <?= $cat['is_active'] ? 'Disable' : 'Enable' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="../assets/js/bootstrap.bundle.js"></script>
<script src="../assets/js/bootstrap.min.js"></script>

</body>
</html>