<?php
require_once 'config/database.php';
require_once 'includes/form-functions.php';

// Handle form saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    saveForm($pdo, $_POST);
}

// Get all saved forms
$forms = getForms($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Master Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <!-- Form List -->
                <div class="card">
                    <div class="card-header">
                        <h5>Saved Forms</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($forms as $form): ?>
                                <li class="list-group-item">
                                    <a href="form_view.php?id=<?= $form['id'] ?>"><?= htmlspecialchars($form['title']) ?></a>
                                    <span class="float-end">
                                        <a href="form_master.php?edit=<?= $form['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Form Builder -->
                <div class="card">
                    <div class="card-header">
                        <h5>Form Builder</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-builder" method="post">
                            <div class="mb-3">
                                <label for="form-title" class="form-label">Form Title</label>
                                <input type="text" class="form-control" id="form-title" name="title" required>
                            </div>
                            
                            <!-- Form fields will be added here via JavaScript -->
                            <div id="form-fields-container"></div>
                            
                            <button type="button" id="add-field" class="btn btn-secondary">Add Field</button>
                            <button type="submit" name="save_form" class="btn btn-primary">Save Form</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>