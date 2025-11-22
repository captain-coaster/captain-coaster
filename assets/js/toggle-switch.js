/**
 * Toggle Switch - Minimal JavaScript for Symfony form compatibility
 * Handles the visual state of toggle switches based on checkbox state
 */

document.addEventListener('DOMContentLoaded', function () {
    // Find all toggle switch form groups
    const toggleFormGroups = document.querySelectorAll(
        '.toggle-switch-form-group'
    );

    toggleFormGroups.forEach(function (formGroup) {
        const checkbox = formGroup.querySelector('input[type="checkbox"]');

        if (!checkbox) return;

        // Set initial state
        updateToggleState(formGroup, checkbox);

        // Listen for changes
        checkbox.addEventListener('change', function () {
            updateToggleState(formGroup, checkbox);
        });

        // Listen for focus/blur
        checkbox.addEventListener('focus', function () {
            formGroup.classList.add('focused');
        });

        checkbox.addEventListener('blur', function () {
            formGroup.classList.remove('focused');
        });
    });

    function updateToggleState(formGroup, checkbox) {
        if (checkbox.checked) {
            formGroup.classList.add('checked');
        } else {
            formGroup.classList.remove('checked');
        }
    }
});
