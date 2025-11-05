import { Controller } from '@hotwired/stimulus';

/**
 * Modern Top List Controller - Native HTML5 Drag & Drop Implementation
 * 
 * Replaces legacy jQuery sortable with modern web standards.
 * Provides drag-and-drop reordering with auto-save functionality.
 * 
 * Features:
 * - Native HTML5 drag and drop API
 * - Mobile touch support
 * - Visual feedback during drag operations
 * - Auto-save with debouncing
 * - Exactly one drop zone between cards
 * - Position management and updates
 * 
 * Usage:
 * <ul data-controller="top-list" 
 *     data-top-list-auto-save-url-value="/tops/123/auto-save"
 *     data-top-list-save-delay-value="2000">
 *   <li data-top-list-target="item" draggable="true" data-coaster-id="456" data-position="1">
 *     <!-- coaster content -->
 *   </li>
 * </ul>
 */
export default class extends Controller {
    static targets = ['list', 'item'];
    static values = {
        autoSaveUrl: String,
        saveDelay: { type: Number, default: 2000 }
    };

    connect() {
        console.log('TopList controller connected');
        
        // Initialize state
        this.draggedElement = null;
        this.draggedIndex = -1;
        this.dropZones = [];
        this.saveTimeout = null;
        this.isDragging = false;
        
        // Set up drag and drop
        this.setupDragAndDrop();
        
        // Create drop zones
        this.createDropZones();
        
        // Update initial positions
        this.updatePositions();
    }

    disconnect() {
        // Clean up timers
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        // Remove drop zones
        this.removeDropZones();
    }

    /**
     * Set up drag and drop event listeners on all items
     */
    setupDragAndDrop() {
        this.itemTargets.forEach((item, index) => {
            // Make items draggable
            item.draggable = true;
            item.dataset.originalIndex = index.toString();
            
            // Add drag event listeners
            item.addEventListener('dragstart', this.handleDragStart.bind(this));
            item.addEventListener('dragend', this.handleDragEnd.bind(this));
        });
    }

    /**
     * Create drop zones between each item
     * There MUST be exactly ONE drop zone between cards
     */
    createDropZones() {
        this.removeDropZones(); // Clean up existing zones first
        
        const items = this.itemTargets;
        
        // Create drop zone before first item
        const firstDropZone = this.createDropZone(0);
        if (items.length > 0) {
            items[0].parentNode.insertBefore(firstDropZone, items[0]);
        } else {
            this.listTarget.appendChild(firstDropZone);
        }
        
        // Create drop zones between items
        items.forEach((item, index) => {
            if (index < items.length - 1) {
                const dropZone = this.createDropZone(index + 1);
                item.parentNode.insertBefore(dropZone, items[index + 1]);
            }
        });
        
        // Create drop zone after last item
        const lastDropZone = this.createDropZone(items.length);
        this.listTarget.appendChild(lastDropZone);
    }

    /**
     * Create a single drop zone element
     */
    createDropZone(position) {
        const dropZone = document.createElement('li');
        dropZone.className = 'drop-zone';
        dropZone.dataset.position = position.toString();
        dropZone.innerHTML = '<div class="drop-zone-indicator"></div>';
        
        // Add drop zone event listeners
        dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
        dropZone.addEventListener('drop', this.handleDrop.bind(this));
        dropZone.addEventListener('dragenter', this.handleDragEnter.bind(this));
        dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
        
        this.dropZones.push(dropZone);
        return dropZone;
    }

    /**
     * Remove all drop zones
     */
    removeDropZones() {
        this.dropZones.forEach(zone => {
            if (zone.parentNode) {
                zone.parentNode.removeChild(zone);
            }
        });
        this.dropZones = [];
    }

    /**
     * Handle drag start event
     */
    handleDragStart(event) {
        this.isDragging = true;
        this.draggedElement = event.target;
        this.draggedIndex = parseInt(event.target.dataset.originalIndex);
        
        // Set drag data
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/html', event.target.outerHTML);
        
        // Add visual feedback
        event.target.classList.add('dragging');
        this.element.classList.add('drag-active');
        
        // Show drop zones
        this.showDropZones();
        
        // Create custom drag image (ghost)
        this.setDragImage(event);
        
        console.log('Drag started for item at index:', this.draggedIndex);
    }

    /**
     * Handle drag end event
     */
    handleDragEnd(event) {
        this.isDragging = false;
        
        // Remove visual feedback
        event.target.classList.remove('dragging');
        this.element.classList.remove('drag-active');
        
        // Clear all drag states
        this.clearDragOverStates();
        
        // Hide drop zones
        this.hideDropZones();
        
        // Clean up
        this.draggedElement = null;
        this.draggedIndex = -1;
        
        console.log('Drag ended');
    }

    /**
     * Handle drag over event (required for drop to work)
     */
    handleDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    /**
     * Handle drag enter event
     */
    handleDragEnter(event) {
        event.preventDefault();
        const dropZone = event.currentTarget;
        if (dropZone.classList.contains('drop-zone')) {
            // Clear any existing drag-over states first
            this.clearDragOverStates();
            dropZone.classList.add('drag-over');
        }
    }

    /**
     * Handle drag leave event
     */
    handleDragLeave(event) {
        event.preventDefault();
        const dropZone = event.currentTarget;
        if (dropZone.classList.contains('drop-zone')) {
            // Only remove if we're actually leaving the drop zone completely
            const rect = dropZone.getBoundingClientRect();
            const x = event.clientX;
            const y = event.clientY;
            
            // Check if mouse is still within the drop zone bounds
            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                dropZone.classList.remove('drag-over');
            }
        }
    }

    /**
     * Clear all drag-over states
     */
    clearDragOverStates() {
        this.dropZones.forEach(zone => {
            zone.classList.remove('drag-over');
        });
    }

    /**
     * Handle drop event
     */
    handleDrop(event) {
        event.preventDefault();
        
        if (!this.draggedElement) {
            console.log('No dragged element found');
            return;
        }
        
        // Find the drop zone (could be the target or a parent)
        let dropZone = event.target;
        if (!dropZone.classList.contains('drop-zone')) {
            dropZone = dropZone.closest('.drop-zone');
        }
        
        if (!dropZone) {
            console.log('Drop target is not a drop zone');
            return;
        }
        
        const dropPosition = parseInt(dropZone.dataset.position);
        const originalPosition = this.draggedIndex;
        
        console.log(`Dropping item from position ${originalPosition} to position ${dropPosition}`);
        
        // Remove drag over styling
        dropZone.classList.remove('drag-over');
        
        // Only move if position actually changed
        if (originalPosition !== dropPosition && originalPosition !== dropPosition - 1) {
            // Perform the move
            this.moveItem(originalPosition, dropPosition);
            
            // Update positions and save
            this.updatePositions();
            this.debouncedSave();
        } else {
            console.log('Item dropped in same position, no move needed');
        }
    }

    /**
     * Move an item from one position to another
     */
    moveItem(fromIndex, toIndex) {
        const items = Array.from(this.itemTargets);
        const draggedItem = items[fromIndex];
        
        if (!draggedItem) {
            console.error('Could not find dragged item at index:', fromIndex);
            return;
        }
        
        console.log(`Moving item from index ${fromIndex} to index ${toIndex}`);
        
        // Remove the dragged item from the DOM temporarily
        const parent = draggedItem.parentNode;
        draggedItem.remove();
        
        // Get updated list of items (without the dragged item)
        const remainingItems = Array.from(this.itemTargets);
        
        // Calculate where to insert the item
        if (toIndex === 0) {
            // Insert at the beginning
            if (remainingItems.length > 0) {
                parent.insertBefore(draggedItem, remainingItems[0]);
            } else {
                parent.appendChild(draggedItem);
            }
        } else if (toIndex >= remainingItems.length) {
            // Insert at the end
            parent.appendChild(draggedItem);
        } else {
            // Insert before the item at the target position
            parent.insertBefore(draggedItem, remainingItems[toIndex]);
        }
        
        // Recreate drop zones with new layout
        this.createDropZones();
        
        // Update original indices
        this.updateOriginalIndices();
        
        console.log('Item moved successfully');
    }

    /**
     * Update original indices after reordering
     */
    updateOriginalIndices() {
        this.itemTargets.forEach((item, index) => {
            item.dataset.originalIndex = index.toString();
        });
    }

    /**
     * Update position numbers displayed in the UI
     */
    updatePositions() {
        this.itemTargets.forEach((item, index) => {
            const position = index + 1;
            
            // Update data attribute
            item.dataset.position = position.toString();
            
            // Update position badge
            const positionBadge = item.querySelector('.position-badge');
            if (positionBadge) {
                positionBadge.textContent = position.toString();
            }
        });
    }

    /**
     * Show drop zones during drag operation
     */
    showDropZones() {
        this.dropZones.forEach(zone => {
            zone.classList.add('visible');
        });
    }

    /**
     * Hide drop zones after drag operation
     */
    hideDropZones() {
        this.dropZones.forEach(zone => {
            zone.classList.remove('visible', 'drag-over');
        });
    }

    /**
     * Set custom drag image for better visual feedback
     */
    setDragImage(event) {
        try {
            // Create a ghost image that looks like the dragged item
            const dragImage = event.target.cloneNode(true);
            dragImage.style.opacity = '0.8';
            dragImage.style.position = 'absolute';
            dragImage.style.top = '-1000px';
            dragImage.style.left = '-1000px';
            dragImage.style.width = event.target.offsetWidth + 'px';
            
            document.body.appendChild(dragImage);
            
            // Set the custom drag image
            event.dataTransfer.setDragImage(dragImage, event.offsetX, event.offsetY);
            
            // Clean up the temporary element after a short delay
            setTimeout(() => {
                if (dragImage.parentNode) {
                    dragImage.parentNode.removeChild(dragImage);
                }
            }, 100);
        } catch (error) {
            // Fallback to default drag image if custom one fails
            console.warn('Could not set custom drag image:', error);
        }
    }

    /**
     * Auto-save with debouncing
     */
    debouncedSave() {
        // Clear existing timeout
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        // Set new timeout
        this.saveTimeout = setTimeout(() => {
            this.autoSave();
        }, this.saveDelayValue);
    }

    /**
     * Perform auto-save of current positions
     */
    async autoSave() {
        if (!this.autoSaveUrlValue) {
            console.warn('No auto-save URL configured');
            return;
        }
        
        try {
            // Collect current positions
            const positions = {};
            this.itemTargets.forEach((item, index) => {
                const coasterId = item.dataset.coasterId;
                if (coasterId) {
                    positions[coasterId] = index + 1;
                }
            });
            
            console.log('Auto-saving positions:', positions);
            
            // Send AJAX request
            const response = await fetch(this.autoSaveUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ positions })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('Auto-save successful');
                this.showSaveStatus('saved');
            } else {
                throw new Error(data.message || 'Auto-save failed');
            }
            
        } catch (error) {
            console.error('Auto-save error:', error);
            this.showSaveStatus('error');
            
            // Retry after delay
            setTimeout(() => {
                this.autoSave();
            }, 5000);
        }
    }

    /**
     * Show save status to user
     */
    showSaveStatus(status) {
        // Remove existing status indicators
        const existingStatus = this.element.querySelector('.save-status');
        if (existingStatus) {
            existingStatus.remove();
        }
        
        // Create status indicator
        const statusElement = document.createElement('div');
        statusElement.className = `save-status save-status-${status}`;
        
        switch (status) {
            case 'saving':
                statusElement.innerHTML = '<i class="icon-spinner2 spinner"></i> Saving...';
                break;
            case 'saved':
                statusElement.innerHTML = '<i class="icon-checkmark3"></i> Saved';
                break;
            case 'error':
                statusElement.innerHTML = '<i class="icon-warning2"></i> Save failed';
                break;
        }
        
        // Add to DOM
        this.element.appendChild(statusElement);
        
        // Auto-remove after delay (except for errors)
        if (status !== 'error') {
            setTimeout(() => {
                if (statusElement.parentNode) {
                    statusElement.remove();
                }
            }, 2000);
        }
    }

    /**
     * Remove a coaster from the list
     */
    removeCoaster(event) {
        const item = event.target.closest('[data-top-list-target="item"]');
        if (!item) return;
        
        // Remove the item
        item.remove();
        
        // Update positions and drop zones
        this.updatePositions();
        this.createDropZones();
        this.updateOriginalIndices();
        
        // Auto-save the changes
        this.debouncedSave();
    }

    /**
     * Add a new coaster to the list (for future use)
     */
    addCoaster(coasterId, position = null) {
        // This method will be implemented in future tasks
        console.log('Add coaster functionality will be implemented in task 4.1');
    }

    /**
     * Get current positions data for external use
     */
    getPositionsData() {
        const positions = {};
        this.itemTargets.forEach((item, index) => {
            const coasterId = item.dataset.coasterId;
            if (coasterId) {
                positions[coasterId] = index + 1;
            }
        });
        return positions;
    }
}