<?php
require_once 'config/database.php';
require_once 'includes/form-functions.php';

if (!isset($_GET['id']) || !$form = getForm($pdo, $_GET['id'])) {
    header("Location: form_master.php");
    exit();
}

$structure = json_decode($form['structure'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($form['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1><?= htmlspecialchars($form['title']) ?></h1>
        <?php if (!empty($form['description'])): ?>
            <p class="lead"><?= htmlspecialchars($form['description']) ?></p>
        <?php endif; ?>
        
        <form method="post" action="form_submit.php">
            <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
            
            <?php foreach ($structure['fields'] as $id => $field): ?>
                <div class="mb-3">
                    <label class="form-label"><?= htmlspecialchars($field['label'] ?? '') ?></label>
                    <?php if ($field['type'] === 'text' || $field['type'] === 'email' || $field['type'] === 'number'): ?>
                        <input type="<?= $field['type'] ?>" class="form-control" name="<?= $id ?>">
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea class="form-control" name="<?= $id ?>"></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</body>
</html>