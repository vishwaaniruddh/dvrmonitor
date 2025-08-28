<?php
// form_master.php
session_start();

// Save form configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $_SESSION['form_config'] = $_POST;
    $success = "Form configuration saved successfully!";
}

// Clear form configuration
if (isset($_GET['clear'])) {
    unset($_SESSION['form_config']);
    header("Location: form_master.php");
    exit();
}

// Get saved configuration or initialize empty
$config = $_SESSION['form_config'] ?? [
    'form_title' => 'My Custom Form',
    'form_action' => '',
    'form_method' => 'post',
    'fields' => []
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Master Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .builder-container { background-color: #f8f9fa; border-radius: 10px; padding: 20px; }
        .preview-container { background-color: #fff; border-radius: 10px; padding: 20px; border: 1px solid #dee2e6; }
        .field-config { background-color: #e9ecef; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .form-field-preview { margin-bottom: 20px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="text-center mb-5">Form Master Builder</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="builder-container">
                    <h3>Form Configuration</h3>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Form Title</label>
                            <input type="text" class="form-control" name="form_title" value="<?= htmlspecialchars($config['form_title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Form Action URL</label>
                            <input type="text" class="form-control" name="form_action" value="<?= htmlspecialchars($config['form_action'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Form Method</label>
                            <select class="form-select" name="form_method">
                                <option value="post" <?= ($config['form_method'] ?? 'post') === 'post' ? 'selected' : '' ?>>POST</option>
                                <option value="get" <?= ($config['form_method'] ?? 'post') === 'get' ? 'selected' : '' ?>>GET</option>
                            </select>
                        </div>
                        
                        <hr>
                        
                        <h4>Form Fields</h4>
                        
                        <div id="fields-container">
                            <?php foreach ($config['fields'] ?? [] as $index => $field): ?>
                                <div class="field-config" data-index="<?= $index ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Field Type</label>
                                        <select class="form-select field-type" name="fields[<?= $index ?>][type]" required>
                                            <option value="text" <?= $field['type'] === 'text' ? 'selected' : '' ?>>Text Input</option>
                                            <option value="number" <?= $field['type'] === 'number' ? 'selected' : '' ?>>Number Input</option>
                                            <option value="email" <?= $field['type'] === 'email' ? 'selected' : '' ?>>Email Input</option>
                                            <option value="password" <?= $field['type'] === 'password' ? 'selected' : '' ?>>Password Input</option>
                                            <option value="textarea" <?= $field['type'] === 'textarea' ? 'selected' : '' ?>>Textarea</option>
                                            <option value="select" <?= $field['type'] === 'select' ? 'selected' : '' ?>>Dropdown Select</option>
                                            <option value="checkbox" <?= $field['type'] === 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                                            <option value="radio" <?= $field['type'] === 'radio' ? 'selected' : '' ?>>Radio Buttons</option>
                                            <option value="file" <?= $field['type'] === 'file' ? 'selected' : '' ?>>File Upload</option>
                                            <option value="date" <?= $field['type'] === 'date' ? 'selected' : '' ?>>Date Picker</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Field Name</label>
                                        <input type="text" class="form-control" name="fields[<?= $index ?>][name]" value="<?= htmlspecialchars($field['name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Field Label</label>
                                        <input type="text" class="form-control" name="fields[<?= $index ?>][label]" value="<?= htmlspecialchars($field['label'] ?? '') ?>">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="fields[<?= $index ?>][show_label]" value="1" <?= ($field['show_label'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label">Show Label</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Column Width</label>
                                        <select class="form-select" name="fields[<?= $index ?>][width]">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($field['width'] ?? 12) == $i ? 'selected' : '' ?>>
                                                    col-md-<?= $i ?> (<?= round($i/12*100) ?>%)
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="fields[<?= $index ?>][required]" value="1" <?= ($field['required'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label">Required Field</label>
                                        </div>
                                    </div>
                                    
                                    <!-- Options for select, radio, checkbox -->
                                    <div class="mb-3 field-options-container <?= !in_array($field['type'], ['select', 'radio', 'checkbox']) ? 'hidden' : '' ?>">
                                        <label class="form-label">Options (one per line)</label>
                                        <textarea class="form-control" name="fields[<?= $index ?>][options]" rows="3"><?= 
                                            isset($field['options']) ? implode("\n", $field['options']) : '' 
                                        ?></textarea>
                                        <small class="text-muted">Enter each option on a new line</small>
                                    </div>
                                    
                                    <!-- File upload specific options -->
                                    <div class="mb-3 file-options-container <?= $field['type'] !== 'file' ? 'hidden' : '' ?>">
                                        <label class="form-label">Accepted File Types</label>
                                        <input type="text" class="form-control" name="fields[<?= $index ?>][accept]" value="<?= htmlspecialchars($field['accept'] ?? '') ?>">
                                        <small class="text-muted">e.g. .pdf,.docx or image/*</small>
                                    </div>
                                    
                                    <button type="button" class="btn btn-danger btn-sm remove-field">Remove Field</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" id="add-field" class="btn btn-secondary mt-3">+ Add Field</button>
                        
                        <hr>
                        
                        <h4>Submit Button</h4>
                        
                        <div class="mb-3">
                            <label class="form-label">Button Text</label>
                            <input type="text" class="form-control" name="submit_text" value="<?= htmlspecialchars($config['submit_text'] ?? 'Submit') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Button Class</label>
                            <input type="text" class="form-control" name="submit_class" value="<?= htmlspecialchars($config['submit_class'] ?? 'btn btn-primary') ?>">
                            <small class="text-muted">Bootstrap classes: btn btn-primary, btn-success, etc.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Button ID</label>
                            <input type="text" class="form-control" name="submit_id" value="<?= htmlspecialchars($config['submit_id'] ?? '') ?>">
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="submit" name="save_form" class="btn btn-primary">Save Form</button>
                            <a href="form_master.php?clear=1" class="btn btn-outline-danger">Clear Form</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="preview-container">
                    <h3>Form Preview</h3>
                    <?php if (!empty($config['fields'])): ?>
                        <form action="<?= htmlspecialchars($config['form_action']) ?>" method="<?= htmlspecialchars($config['form_method']) ?>">
                            <h4><?= htmlspecialchars($config['form_title']) ?></h4>
                            
                            <div class="row">
                                <?php foreach ($config['fields'] as $field): ?>
                                    <div class="col-md-<?= $field['width'] ?? 12 ?> form-field-preview">
                                        <?php if (($field['show_label'] ?? true) && !empty($field['label'])): ?>
                                            <label class="form-label"><?= htmlspecialchars($field['label']) ?>
                                                <?php if ($field['required'] ?? false): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>
                                        <?php endif; ?>
                                        
                                        <?php switch ($field['type']):
                                            case 'text':
                                            case 'number':
                                            case 'email':
                                            case 'password':
                                            case 'date': ?>
                                                <input type="<?= $field['type'] ?>" 
                                                       class="form-control" 
                                                       name="<?= htmlspecialchars($field['name']) ?>" 
                                                       <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                                                <?php break; ?>
                                            
                                            <?php case 'textarea': ?>
                                                <textarea class="form-control" 
                                                          name="<?= htmlspecialchars($field['name']) ?>" 
                                                          <?= ($field['required'] ?? false) ? 'required' : '' ?>></textarea>
                                                <?php break; ?>
                                            
                                            <?php case 'select': ?>
                                                <select class="form-select" 
                                                        name="<?= htmlspecialchars($field['name']) ?>" 
                                                        <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                                                    <?php if (!empty($field['options'])): ?>
                                                        <?php foreach ($field['options'] as $option): ?>
                                                            <option value="<?= htmlspecialchars(trim($option)) ?>"><?= htmlspecialchars(trim($option)) ?></option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="">Select an option</option>
                                                    <?php endif; ?>
                                                </select>
                                                <?php break; ?>
                                            
                                            <?php case 'checkbox': ?>
                                                <?php if (!empty($field['options'])): ?>
                                                    <?php foreach ($field['options'] as $option): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="<?= htmlspecialchars($field['name']) ?>[]" 
                                                                   value="<?= htmlspecialchars(trim($option)) ?>"
                                                                   <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                                                            <label class="form-check-label"><?= htmlspecialchars(trim($option)) ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php break; ?>
                                            
                                            <?php case 'radio': ?>
                                                <?php if (!empty($field['options'])): ?>
                                                    <?php foreach ($field['options'] as $option): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" 
                                                                   type="radio" 
                                                                   name="<?= htmlspecialchars($field['name']) ?>" 
                                                                   value="<?= htmlspecialchars(trim($option)) ?>"
                                                                   <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                                                            <label class="form-check-label"><?= htmlspecialchars(trim($option)) ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php break; ?>
                                            
                                            <?php case 'file': ?>
                                                <input type="file" 
                                                       class="form-control" 
                                                       name="<?= htmlspecialchars($field['name']) ?>" 
                                                       <?= !empty($field['accept']) ? 'accept="' . htmlspecialchars($field['accept']) . '"' : '' ?>
                                                       <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                                                <?php break; ?>
                                        <?php endswitch; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" 
                                        class="<?= htmlspecialchars($config['submit_class'] ?? 'btn btn-primary') ?>"
                                        <?= !empty($config['submit_id']) ? 'id="' . htmlspecialchars($config['submit_id']) . '"' : '' ?>>
                                    <?= htmlspecialchars($config['submit_text'] ?? 'Submit') ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">No fields added yet. Configure your form on the left.</div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($config['fields'])): ?>
                    <div class="mt-4 preview-container">
                        <h4>Generated HTML Code</h4>
                        <pre><code class="html"><?= htmlspecialchars(generateFormHtml($config)) ?></code></pre>
                        <button class="btn btn-sm btn-outline-primary copy-code">Copy Code</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add new field
            document.getElementById('add-field').addEventListener('click', function() {
                const container = document.getElementById('fields-container');
                const index = container.children.length;
                
                const fieldHtml = `
                    <div class="field-config" data-index="${index}">
                        <div class="mb-3">
                            <label class="form-label">Field Type</label>
                            <select class="form-select field-type" name="fields[${index}][type]" required>
                                <option value="text">Text Input</option>
                                <option value="number">Number Input</option>
                                <option value="email">Email Input</option>
                                <option value="password">Password Input</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Dropdown Select</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="radio">Radio Buttons</option>
                                <option value="file">File Upload</option>
                                <option value="date">Date Picker</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Field Name</label>
                            <input type="text" class="form-control" name="fields[${index}][name]" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Field Label</label>
                            <input type="text" class="form-control" name="fields[${index}][label]">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="fields[${index}][show_label]" value="1" checked>
                                <label class="form-check-label">Show Label</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Column Width</label>
                            <select class="form-select" name="fields[${index}][width]">
                                ${Array.from({length: 12}, (_, i) => 
                                    `<option value="${i+1}" ${i+1 === 12 ? 'selected' : ''}>col-md-${i+1} (${Math.round((i+1)/12*100)}%)</option>`
                                ).join('')}
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fields[${index}][required]" value="1">
                                <label class="form-check-label">Required Field</label>
                            </div>
                        </div>
                        
                        <div class="mb-3 field-options-container hidden">
                            <label class="form-label">Options (one per line)</label>
                            <textarea class="form-control" name="fields[${index}][options]" rows="3"></textarea>
                            <small class="text-muted">Enter each option on a new line</small>
                        </div>
                        
                        <div class="mb-3 file-options-container hidden">
                            <label class="form-label">Accepted File Types</label>
                            <input type="text" class="form-control" name="fields[${index}][accept]">
                            <small class="text-muted">e.g. .pdf,.docx or image/*</small>
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-sm remove-field">Remove Field</button>
                    </div>
                `;
                
                container.insertAdjacentHTML('beforeend', fieldHtml);
            });
            
            // Remove field
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-field')) {
                    e.target.closest('.field-config').remove();
                }
            });
            
            // Show/hide options based on field type
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('field-type')) {
                    const fieldConfig = e.target.closest('.field-config');
                    const optionsContainer = fieldConfig.querySelector('.field-options-container');
                    const fileOptionsContainer = fieldConfig.querySelector('.file-options-container');
                    
                    const fieldType = e.target.value;
                    
                    // Show/hide options container
                    if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                        optionsContainer.classList.remove('hidden');
                    } else {
                        optionsContainer.classList.add('hidden');
                    }
                    
                    // Show/hide file options container
                    if (fieldType === 'file') {
                        fileOptionsContainer.classList.remove('hidden');
                    } else {
                        fileOptionsContainer.classList.add('hidden');
                    }
                }
            });
            
            // Copy code to clipboard
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('copy-code')) {
                    const code = e.target.previousElementSibling.textContent;
                    navigator.clipboard.writeText(code).then(() => {
                        const originalText = e.target.textContent;
                        e.target.textContent = 'Copied!';
                        setTimeout(() => {
                            e.target.textContent = originalText;
                        }, 2000);
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php
function generateFormHtml($config) {
    $html = '<form action="' . htmlspecialchars($config['form_action']) . '" method="' . htmlspecialchars($config['form_method']) . '">' . "\n";
    $html .= '    <h4>' . htmlspecialchars($config['form_title']) . '</h4>' . "\n\n";
    $html .= '    <div class="row">' . "\n";
    
    foreach ($config['fields'] as $field) {
        $html .= '        <div class="col-md-' . ($field['width'] ?? 12) . ' mb-3">' . "\n";
        
        if (($field['show_label'] ?? true) && !empty($field['label'])) {
            $html .= '            <label class="form-label">' . htmlspecialchars($field['label']);
            if ($field['required'] ?? false) {
                $html .= ' <span class="text-danger">*</span>';
            }
            $html .= '</label>' . "\n";
        }
        
        switch ($field['type']) {
            case 'text':
            case 'number':
            case 'email':
            case 'password':
            case 'date':
                $html .= '            <input type="' . $field['type'] . '" class="form-control" name="' . htmlspecialchars($field['name']) . '"';
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '>' . "\n";
                break;
                
            case 'textarea':
                $html .= '            <textarea class="form-control" name="' . htmlspecialchars($field['name']) . '"';
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '></textarea>' . "\n";
                break;
                
            case 'select':
                $html .= '            <select class="form-select" name="' . htmlspecialchars($field['name']) . '"';
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '>' . "\n";
                
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        $html .= '                <option value="' . htmlspecialchars(trim($option)) . '">' . htmlspecialchars(trim($option)) . '</option>' . "\n";
                    }
                } else {
                    $html .= '                <option value="">Select an option</option>' . "\n";
                }
                
                $html .= '            </select>' . "\n";
                break;
                
            case 'checkbox':
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        $html .= '            <div class="form-check">' . "\n";
                        $html .= '                <input class="form-check-input" type="checkbox" name="' . htmlspecialchars($field['name']) . '[]" value="' . htmlspecialchars(trim($option)) . '"';
                        if ($field['required'] ?? false) {
                            $html .= ' required';
                        }
                        $html .= '>' . "\n";
                        $html .= '                <label class="form-check-label">' . htmlspecialchars(trim($option)) . '</label>' . "\n";
                        $html .= '            </div>' . "\n";
                    }
                }
                break;
                
            case 'radio':
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        $html .= '            <div class="form-check">' . "\n";
                        $html .= '                <input class="form-check-input" type="radio" name="' . htmlspecialchars($field['name']) . '" value="' . htmlspecialchars(trim($option)) . '"';
                        if ($field['required'] ?? false) {
                            $html .= ' required';
                        }
                        $html .= '>' . "\n";
                        $html .= '                <label class="form-check-label">' . htmlspecialchars(trim($option)) . '</label>' . "\n";
                        $html .= '            </div>' . "\n";
                    }
                }
                break;
                
            case 'file':
                $html .= '            <input type="file" class="form-control" name="' . htmlspecialchars($field['name']) . '"';
                if (!empty($field['accept'])) {
                    $html .= ' accept="' . htmlspecialchars($field['accept']) . '"';
                }
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '>' . "\n";
                break;
        }
        
        $html .= '        </div>' . "\n\n";
    }
    
    $html .= '    </div>' . "\n\n";
    $html .= '    <button type="submit" class="' . htmlspecialchars($config['submit_class'] ?? 'btn btn-primary') . '"';
    if (!empty($config['submit_id'])) {
        $html .= ' id="' . htmlspecialchars($config['submit_id']) . '"';
    }
    $html .= '>' . htmlspecialchars($config['submit_text'] ?? 'Submit') . '</button>' . "\n";
    $html .= '</form>';
    
    return $html;
}
?>