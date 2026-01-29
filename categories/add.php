<?php
session_start();
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preview-img { max-height: 220px; object-fit: contain; border: 1px solid #ddd; border-radius: 8px; }
        .card { max-width: 520px; margin: 50px auto; }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New Category</h4>
        </div>
        <div class="card-body">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="process.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="mb-3">
                    <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Beef, Mbuzi, Matumbo...">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Category Photo (optional)</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                    <div class="mt-3 text-center">
                        <img src="" alt="Preview" class="preview-img d-none" id="preview">
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2">Save Category</button>
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