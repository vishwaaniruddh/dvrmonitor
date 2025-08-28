document.addEventListener('DOMContentLoaded', function() {
    // Add new field
    document.getElementById('add-field').addEventListener('click', function() {
        const container = document.getElementById('form-fields-container');
        const fieldId = Date.now();
        
        const fieldHtml = `
            <div class="card mb-3 field-item" data-id="${fieldId}">
                <div class="card-header d-flex justify-content-between">
                    <span>New Field</span>
                    <button type="button" class="btn btn-sm btn-danger remove-field">×</button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Field Type</label>
                        <select class="form-select field-type" name="fields[${fieldId}][type]">
                            <option value="text">Text Input</option>
                            <option value="email">Email Input</option>
                            <option value="number">Number Input</option>
                            <option value="select">Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="radio">Radio Buttons</option>
                            <option value="textarea">Text Area</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Field Label</label>
                        <input type="text" class="form-control" name="fields[${fieldId}][label]">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[${fieldId}][required]">
                            <label class="form-check-label">Required Field</label>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', fieldHtml);
    });
    
    // Remove field
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-field')) {
            e.target.closest('.field-item').remove();
        }
    });
});