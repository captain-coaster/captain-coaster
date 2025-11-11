import { Controller } from '@hotwired/stimulus';

/**
 * Modern Top List Controller - SortableJS Implementation
 * 
 * Provides drag-and-drop reordering with auto-save functionality.
 * Uses SortableJS for robust touch and mouse support.
 * 
 * Features:
 * - SortableJS drag and drop with touch support
 * - Long-press to drag on mobile (500ms delay)
 * - Visual feedback during drag operations
 * - Auto-save with debouncing
 * - Position management and updates
 * 
 * Usage:
 * <ul data-controller="top-list" 
 *     data-top-list-auto-save-url-value="/tops/123/auto-save"
 *     data-top-list-save-delay-value="2000">
 *   <li data-top-list-target="item" data-coaster-id="456" data-position="1">
 *     <div class="drag-handle"><!-- handle icon --></div>
 *     <!-- coaster content -->
 *   </li>
 * </ul>
 */
export default class extends Controller {
    static targets = ['item'];
    static values = {
        autoSaveUrl: String,
        saveDelay: { type: Number, default: 2000 }
    };

    async connect() {
        console.log('TopList controller connected with SortableJS');
        
        // Initialize state
        this.saveTimeout = null;
        
        // Lazy load SortableJS only when this controller is used
        const { default: Sortable } = await import('sortablejs');
        
        // Initialize SortableJS
        this.sortable = Sortable.create(this.element, {
            animation: 150,              // Smooth animation duration in ms
            handle: '.drag-area',        // Only drag from drag area
            draggable: '[data-top-list-target="item"]', // Items that can be dragged
            delay: 200,                  // Long-press delay for touch (ms) - reduced for better UX
            delayOnTouchOnly: true,      // Only delay on touch devices
            touchStartThreshold: 5,      // Pixels to move before canceling (prevents accidental drags)
            
            // Callbacks
            onStart: (evt) => this.handleDragStart(evt),
            onEnd: (evt) => this.handleDragEnd(evt),
            onMove: (evt) => this.handleDragMove(evt),
            
            // Visual feedback classes
            ghostClass: 'sortable-ghost',   // Class for placeholder
            chosenClass: 'sortable-chosen', // Class for selected item
            dragClass: 'sortable-drag'      // Class for dragged item
        });
        
        // Update initial positions
        this.updatePositions();
    }

    disconnect() {
        // Clean up timers
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        // Destroy SortableJS instance
        if (this.sortable) {
            this.sortable.destroy();
        }
    }

    /**
     * Handle drag start event (SortableJS callback)
     */
    handleDragStart(evt) {
        console.log('Drag started');
        
        // Add visual feedback to container
        this.element.classList.add('drag-active');
    }

    /**
     * Handle drag end event (SortableJS callback)
     */
    handleDragEnd(evt) {
        console.log('Drag ended');
        
        // Remove visual feedback from container
        this.element.classList.remove('drag-active');
        
        // Check if position actually changed
        if (evt.oldIndex !== evt.newIndex) {
            console.log(`Item moved from position ${evt.oldIndex} to ${evt.newIndex}`);
            
            // Update positions in the UI
            this.updatePositions();
            
            // Trigger auto-save
            this.debouncedSave();
        }
    }

    /**
     * Handle drag move event (SortableJS callback)
     * Return false to cancel the move, true to allow it
     */
    handleDragMove(evt) {
        // Allow all moves by default
        // Can add custom logic here if needed
        return true;
    }

    /**
     * Update position numbers displayed in the UI
     */
    updatePositions() {
        this.itemTargets.forEach((item, index) => {
            const position = index + 1;
            
            // Update data attribute
            item.dataset.position = position.toString();
            
            // Update position number
            const positionNumber = item.querySelector('.position-number');
            if (positionNumber) {
                positionNumber.textContent = position.toString();
            }
        });
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
        
        // Update positions
        this.updatePositions();
        
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

    /**
     * Move a coaster to the top of the list (position 1)
     * Requirements: 6.1
     */
    moveToTop(event) {
        event.preventDefault();
        
        const item = event.target.closest('[data-top-list-target="item"]');
        if (!item) return;
        
        console.log('Moving coaster to top');
        
        // Move to first position
        this.element.insertBefore(item, this.element.firstElementChild);
        
        // Update positions
        this.updatePositions();
        
        // Trigger auto-save
        this.debouncedSave();
    }

    /**
     * Move a coaster to the bottom of the list (last position)
     * Requirements: 6.2
     */
    moveToBottom(event) {
        event.preventDefault();
        
        const item = event.target.closest('[data-top-list-target="item"]');
        if (!item) return;
        
        console.log('Moving coaster to bottom');
        
        // Move to last position
        this.element.appendChild(item);
        
        // Update positions
        this.updatePositions();
        
        // Trigger auto-save
        this.debouncedSave();
    }

    /**
     * Move a coaster to a specific position with user prompt
     * Requirements: 6.3, 6.4, 6.5
     */
    moveToPosition(event) {
        event.preventDefault();
        
        const item = event.target.closest('[data-top-list-target="item"]');
        if (!item) return;
        
        const currentPos = parseInt(item.dataset.position);
        const maxPos = this.itemTargets.length;
        
        // Prompt user for new position
        const newPosStr = prompt(`Enter position (1-${maxPos}):`, currentPos);
        
        // Validate input
        if (!newPosStr) {
            // User cancelled
            return;
        }
        
        const newPos = parseInt(newPosStr);
        
        if (isNaN(newPos) || newPos < 1 || newPos > maxPos) {
            alert(`Invalid position. Please enter a number between 1 and ${maxPos}.`);
            return;
        }
        
        // Don't do anything if position hasn't changed
        if (newPos === currentPos) {
            return;
        }
        
        console.log(`Moving coaster from position ${currentPos} to ${newPos}`);
        
        // Move item to new position
        if (newPos === 1) {
            // Move to first position
            this.element.insertBefore(item, this.element.firstElementChild);
        } else if (newPos === maxPos) {
            // Move to last position
            this.element.appendChild(item);
        } else {
            // Move to specific position
            const targetItem = this.itemTargets[newPos - 1];
            if (newPos > currentPos) {
                // Moving down - insert after target
                this.element.insertBefore(item, targetItem.nextElementSibling);
            } else {
                // Moving up - insert before target
                this.element.insertBefore(item, targetItem);
            }
        }
        
        // Update positions
        this.updatePositions();
        
        // Trigger auto-save
        this.debouncedSave();
    }
}