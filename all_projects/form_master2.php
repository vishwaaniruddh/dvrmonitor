<?php session_start();
// form_master2.php
require_once 'config.php'; // Database connection

// Initialize database (run once)
initializeDatabase();

// Save form configuration to database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    saveFormToDatabase();
}

// Load form from database
if (isset($_GET['load_form']) && is_numeric($_GET['load_form'])) {
    $config = loadFormFromDatabase($_GET['load_form']);
    $_SESSION['form_config'] = $config;
}

// Clear form configuration
if (isset($_GET['clear'])) {
    unset($_SESSION['form_config']);
    header("Location: form_master2.php");
    exit();
}

// Delete form from database
if (isset($_GET['delete_form']) && is_numeric($_GET['delete_form'])) {
    deleteFormFromDatabase($_GET['delete_form']);
    header("Location: form_master2.php");
    exit();
}

// Get saved configuration or initialize empty
$config = $_SESSION['form_config'] ?? [
    'form_title' => 'My Custom Form',
    'form_action' => '',
    'form_method' => 'post',
    'fields' => []
];

// Get list of saved forms
$savedForms = getSavedForms();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Previous head content remains the same -->
    <!-- ... -->
</head>
<body>
    <div class="container py-5">
        <h1 class="text-center mb-5">Form Master Builder</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="builder-container">
                    <h3>Form Configuration</h3>
                    
                    <!-- Saved Forms Dropdown -->
                    <div class="mb-4">
                        <label class="form-label">Load Saved Form</label>
                        <div class="input-group">
                            <select class="form-select" id="saved-forms">
                                <option value="">Select a saved form...</option>
                                <?php foreach ($savedForms as $form): ?>
                                    <option value="<?= $form['id'] ?>"><?= htmlspecialchars($form['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="button" id="load-form-btn">Load</button>
                            <button class="btn btn-outline-danger" type="button" id="delete-form-btn">Delete</button>
                        </div>
                    </div>
                    
                    <form method="post">
                        <!-- Previous form configuration fields remain the same -->
                        <!-- ... -->
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="submit" name="save_form" class="btn btn-primary">Save Form</button>
                            <a href="form_master2.php?clear=1" class="btn btn-outline-danger">Clear Form</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Preview container remains the same -->
                <!-- ... -->
            </div>
        </div>
    </div>

    <!-- JavaScript remains the same -->
    <!-- ... -->
    
    <script>
        // Add event listeners for load/delete buttons
        document.getElementById('load-form-btn').addEventListener('click', function() {
            const formId = document.getElementById('saved-forms').value;
            if (formId) {
                window.location.href = `form_master2.php?load_form=${formId}`;
            }
        });
        
        document.getElementById('delete-form-btn').addEventListener('click', function() {
            const formId = document.getElementById('saved-forms').value;
            if (formId && confirm('Are you sure you want to delete this form?')) {
                window.location.href = `form_master2.php?delete_form=${formId}`;
            }
        });
    </script>
</body>
</html>

<?php
// Database functions
function initializeDatabase() {
    global $pdo;
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `forms` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(100) NOT NULL,
                `action_url` VARCHAR(255),
                `method` ENUM('post', 'get') NOT NULL DEFAULT 'post',
                `submit_text` VARCHAR(50) NOT NULL DEFAULT 'Submit',
                `submit_class` VARCHAR(100) NOT NULL DEFAULT 'btn btn-primary',
                `submit_id` VARCHAR(50),
                `config_data` TEXT NOT NULL COMMENT 'JSON encoded form config',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (PDOException $e) {
        die("Error initializing database: " . $e->getMessage());
    }
}

function saveFormToDatabase() {
    global $pdo;
    
    $config = [
        'form_title' => $_POST['form_title'],
        'form_action' => $_POST['form_action'],
        'form_method' => $_POST['form_method'],
        'submit_text' => $_POST['submit_text'],
        'submit_class' => $_POST['submit_class'],
        'submit_id' => $_POST['submit_id'],
        'fields' => []
    ];
    
    // Process fields
    foreach ($_POST['fields'] as $field) {
        $fieldConfig = [
            'type' => $field['type'],
            'name' => $field['name'],
            'label' => $field['label'],
            'show_label' => isset($field['show_label']),
            'width' => $field['width'],
            'required' => isset($field['required']),
        ];
        
        // Add type-specific options
        if (in_array($field['type'], ['select', 'radio', 'checkbox']) && !empty($field['options'])) {
            $fieldConfig['options'] = array_map('trim', explode("\n", $field['options']));
        }
        
        if ($field['type'] === 'file' && !empty($field['accept'])) {
            $fieldConfig['accept'] = $field['accept'];
        }
        
        $config['fields'][] = $fieldConfig;
    }
    
    // Save to database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `forms` (`title`, `action_url`, `method`, `submit_text`, `submit_class`, `submit_id`, `config_data`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                action_url = VALUES(action_url),
                method = VALUES(method),
                submit_text = VALUES(submit_text),
                submit_class = VALUES(submit_class),
                submit_id = VALUES(submit_id),
                config_data = VALUES(config_data),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $config['form_title'],
            $config['form_action'],
            $config['form_method'],
            $config['submit_text'],
            $config['submit_class'],
            $config['submit_id'],
            json_encode($config)
        ]);
        
        $_SESSION['success_message'] = "Form saved successfully!";
        $_SESSION['form_config'] = $config;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error saving form: " . $e->getMessage();
    }
    
    header("Location: form_master2.php");
    exit();
}

function loadFormFromDatabase($formId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT config_data FROM `forms` WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch();
        
        if ($form) {
            return json_decode($form['config_data'], true);
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error loading form: " . $e->getMessage();
    }
    
    return null;
}

function deleteFormFromDatabase($formId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM `forms` WHERE id = ?");
        $stmt->execute([$formId]);
        $_SESSION['success_message'] = "Form deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting form: " . $e->getMessage();
    }
}

function getSavedForms() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, title FROM `forms` ORDER BY updated_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>