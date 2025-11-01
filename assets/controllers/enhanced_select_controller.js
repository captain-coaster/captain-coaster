import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        multiple: Boolean,
        placeholder: String,
        searchable: Boolean,
        maximumSelectionLength: Number
    };

    connect() {
        // Only enhance if it's a select element
        if (this.element.tagName !== 'SELECT') {
            return;
        }

        this.originalSelect = this.element;
        this.selectedValues = new Set();
        this.options = [];
        this.isOpen = false;

        // Initialize from existing select options
        this.initializeOptions();
        this.createCustomDropdown();
        this.setupEventListeners();
        this.updateDisplay();
    }

    disconnect() {
        if (this.customDropdown) {
            this.customDropdown.remove();
        }
        if (this.originalSelect) {
            this.originalSelect.style.display = '';
        }
        document.removeEventListener('click', this.closeOnOutsideClick);
    }

    initializeOptions() {
        this.options = Array.from(this.originalSelect.options).map(option => ({
            value: option.value,
            text: option.textContent.trim(),
            selected: option.selected
        }));

        // Set initial selected values
        this.options.forEach(option => {
            if (option.selected) {
                this.selectedValues.add(option.value);
            }
        });
    }

    createCustomDropdown() {
        // Hide original select
        this.originalSelect.style.display = 'none';

        // Create custom dropdown structure
        this.customDropdown = document.createElement('div');
        this.customDropdown.className = 'dropdown-container';
        
        // Create selection display
        this.selectionDisplay = document.createElement('div');
        this.selectionDisplay.className = 'dropdown-selection';
        this.selectionDisplay.addEventListener('click', () => this.toggle());
        
        // Create dropdown menu
        this.dropdownMenu = document.createElement('div');
        this.dropdownMenu.className = 'dropdown-menu';
        
        // Add search if searchable
        if (this.searchableValue) {
            this.searchContainer = document.createElement('div');
            this.searchContainer.className = 'dropdown-search';
            
            this.searchInput = document.createElement('input');
            this.searchInput.type = 'text';
            this.searchInput.placeholder = 'Search...';
            this.searchInput.addEventListener('input', (e) => this.filterOptions(e.target.value));
            
            this.searchContainer.appendChild(this.searchInput);
            this.dropdownMenu.appendChild(this.searchContainer);
        }
        
        // Create options container
        this.optionsContainer = document.createElement('div');
        this.optionsContainer.className = 'dropdown-options';
        
        // Add options
        this.options.forEach(option => {
            if (option.value === '') return; // Skip empty options
            
            const optionElement = document.createElement('div');
            optionElement.className = 'dropdown-option';
            optionElement.textContent = option.text;
            optionElement.dataset.value = option.value;
            optionElement.addEventListener('click', (e) => {
                if (!optionElement.classList.contains('disabled')) {
                    this.selectOption(option.value, option.text);
                }
            });
            
            if (option.selected) {
                optionElement.classList.add('selected');
            }
            
            this.optionsContainer.appendChild(optionElement);
        });
        
        this.dropdownMenu.appendChild(this.optionsContainer);
        
        // Assemble dropdown
        this.customDropdown.appendChild(this.selectionDisplay);
        this.customDropdown.appendChild(this.dropdownMenu);
        
        // Insert after original select
        this.originalSelect.parentNode.insertBefore(this.customDropdown, this.originalSelect.nextSibling);
    }

    setupEventListeners() {
        // Close dropdown when clicking outside (but not on Bootstrap dropdowns)
        this.closeOnOutsideClick = (event) => {
            // Completely ignore any clicks in navbar or Bootstrap dropdown areas
            if (event.target.closest('.navbar') ||
                event.target.closest('.dropdown') ||
                event.target.closest('[data-toggle]') ||
                event.target.hasAttribute('data-toggle') ||
                event.target.closest('[class*="dropdown"]')) {
                return;
            }
            
            if (this.customDropdown && !this.customDropdown.contains(event.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this.closeOnOutsideClick);
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.dropdownMenu.classList.add('show');
        this.customDropdown.classList.add('open');
        
        if (this.searchInput) {
            this.searchInput.focus();
        }
    }

    close() {
        this.isOpen = false;
        this.dropdownMenu.classList.remove('show');
        this.customDropdown.classList.remove('open');
        
        if (this.searchInput) {
            this.searchInput.value = '';
            this.filterOptions('');
        }
    }

    selectOption(value, text) {
        if (this.multipleValue) {
            if (this.selectedValues.has(value)) {
                this.selectedValues.delete(value);
            } else {
                // Check maximum selection limit
                if (this.hasMaximumSelectionLengthValue && 
                    this.selectedValues.size >= this.maximumSelectionLengthValue) {
                    this.showMaximumSelectionMessage();
                    return;
                }
                this.selectedValues.add(value);
            }
        } else {
            // Single select
            this.selectedValues.clear();
            this.selectedValues.add(value);
            this.close();
        }

        this.updateOptionStates();
        this.updateDisplay();
        this.updateOriginalSelect();
        this.dispatchChangeEvent();
    }

    removeTag(value) {
        this.selectedValues.delete(value);
        this.updateOptionStates();
        this.updateDisplay();
        this.updateOriginalSelect();
        this.dispatchChangeEvent();
    }

    updateOptionStates() {
        const optionElements = this.optionsContainer.querySelectorAll('.dropdown-option');
        const isMaxReached = this.hasMaximumSelectionLengthValue && 
                            this.selectedValues.size >= this.maximumSelectionLengthValue;
        
        optionElements.forEach(optionEl => {
            const value = optionEl.dataset.value;
            const isSelected = this.selectedValues.has(value);
            
            if (isSelected) {
                optionEl.classList.add('selected');
                optionEl.classList.remove('disabled');
            } else {
                optionEl.classList.remove('selected');
                
                // Disable unselected options if maximum is reached
                if (isMaxReached && this.multipleValue) {
                    optionEl.classList.add('disabled');
                } else {
                    optionEl.classList.remove('disabled');
                }
            }
        });
    }

    showMaximumSelectionMessage() {
        // Create temporary message
        if (!this.maxMessage) {
            this.maxMessage = document.createElement('div');
            this.maxMessage.className = 'dropdown-max-message';
            this.maxMessage.textContent = `Maximum ${this.maximumSelectionLengthValue} selections allowed`;
            this.dropdownMenu.appendChild(this.maxMessage);
        }
        
        this.maxMessage.style.display = 'block';
        
        // Hide message after 2 seconds
        setTimeout(() => {
            if (this.maxMessage) {
                this.maxMessage.style.display = 'none';
            }
        }, 2000);
    }

    filterOptions(query) {
        const lowerQuery = query.toLowerCase();
        const optionElements = this.optionsContainer.querySelectorAll('.dropdown-option');
        
        optionElements.forEach(optionEl => {
            const text = optionEl.textContent.toLowerCase();
            const matches = text.includes(lowerQuery);
            optionEl.style.display = matches ? 'block' : 'none';
        });
    }

    updateDisplay() {
        if (this.selectedValues.size === 0) {
            // Show placeholder
            this.selectionDisplay.innerHTML = `<span class="placeholder">${this.placeholderValue || 'Select...'}</span>`;
        } else if (this.multipleValue) {
            // Show tags
            const tags = Array.from(this.selectedValues).map(value => {
                const option = this.options.find(opt => opt.value === value);
                const text = option ? option.text : value;
                
                return `
                    <span class="tag">
                        ${text}
                        <button type="button" class="tag-remove" data-value="${value}">×</button>
                    </span>
                `;
            }).join('');
            
            this.selectionDisplay.innerHTML = tags;
            
            // Add event listeners to remove buttons
            this.selectionDisplay.querySelectorAll('.tag-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.removeTag(btn.dataset.value);
                });
            });
        } else {
            // Show single selected value
            const value = Array.from(this.selectedValues)[0];
            const option = this.options.find(opt => opt.value === value);
            const text = option ? option.text : value;
            
            this.selectionDisplay.innerHTML = `<span class="selected-text">${text}</span>`;
        }
    }

    updateOriginalSelect() {
        // Update original select element
        Array.from(this.originalSelect.options).forEach(option => {
            option.selected = this.selectedValues.has(option.value);
        });
    }

    dispatchChangeEvent() {
        // Dispatch change event on original select
        this.originalSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
}