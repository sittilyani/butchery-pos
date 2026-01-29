<?php
session_start();
require_once '../includes/config.php';

$id = (int)($_GET['id'] ?? 0);
$result = $conn->query("SELECT * FROM categories WHERE category_id = $id");
$category = $result->fetch_assoc();

if (!$category) {
    $_SESSION['error'] = "Category not found!";
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preview-img { max-height: 220px; object-fit: contain; border: 1px solid #ddd; border-radius: 8px; }
        .card { max-width: 520px; margin: 50px auto; }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h4>Edit Category: <?= htmlspecialchars($category['name']) ?></h4>
        </div>
        <div class="card-body">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="process.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" value="<?= $category['category_id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold">Category Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Current Photo</label><br>
                    <?php if($category['photo']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($category['photo']) ?>"
                             class="img-fluid" style="max-height:180px;">
                    <?php else: ?>
                        <span class="text-muted">No photo</span>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Replace Photo (optional)</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                    <div class="mt-3 text-center">
                        <img src="" alt="New Preview" class="preview-img d-none" id="preview">
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2">Update Category</button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('preview').src = URL.createObjectURL(file);
        document.getElementById('preview').classList.remove('d-none');
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>