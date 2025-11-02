/**
 * Ultra-simple plugins - everything in one place
 * No separate files, no complex imports, just what we need
 */

// 1. Simple Switchery replacement (CSS + JS)
document.addEventListener('DOMContentLoaded', () => {
    // Add CSS for toggles - smaller and right-aligned like original
    const style = document.createElement('style');
    style.textContent = `
        .simple-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 16px;
            background: #dfdfdf;
            border-radius: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .simple-toggle:before {
            content: '';
            position: absolute;
            top: 1px;
            left: 1px;
            width: 14px;
            height: 14px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .simple-toggle.on {
            background: #26B99A;
        }
        .simple-toggle.on:before {
            transform: translateX(16px);
        }
        .switchery { display: none !important; }
        
        /* Ensure parent label has relative positioning */
        .checkbox-switchery label {
            position: relative;
            padding-right: 50px;
        }
    `;
    document.head.appendChild(style);

    // Initialize toggles
    document.querySelectorAll('.switchery').forEach(checkbox => {
        if (checkbox.dataset.toggleInit) return;
        checkbox.dataset.toggleInit = 'true';

        const toggle = document.createElement('span');
        toggle.className = 'simple-toggle' + (checkbox.checked ? ' on' : '');
        
        // Insert toggle into the label (for right positioning)
        const label = checkbox.closest('label');
        if (label) {
            label.appendChild(toggle);
        } else {
            checkbox.parentNode.insertBefore(toggle, checkbox.nextSibling);
        }

        toggle.onclick = (e) => {
            e.preventDefault();
            checkbox.checked = !checkbox.checked;
            toggle.classList.toggle('on');
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        };
    });

    // 2. Simple touch support for sortable
    document.querySelectorAll('.ui-sortable').forEach(element => {
        let touchItem = null;
        
        element.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                const touch = e.touches[0];
                touchItem = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('li');
                if (touchItem) {
                    touchItem.dispatchEvent(new MouseEvent('mousedown', {
                        clientX: touch.clientX,
                        clientY: touch.clientY,
                        bubbles: true
                    }));
                }
            }
        }, { passive: false });
        
        element.addEventListener('touchmove', (e) => {
            if (touchItem && e.touches.length === 1) {
                e.preventDefault();
                const touch = e.touches[0];
                document.dispatchEvent(new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY,
                    bubbles: true
                }));
            }
        }, { passive: false });
        
        element.addEventListener('touchend', () => {
            if (touchItem) {
                document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                touchItem = null;
            }
        });
    });

    // 3. Map functionality is now handled by separate component
    // See assets/js/components/map.js
});

// Legacy compatibility
window.Switchery = function() {};

// Legacy loaders (keep minimal)
export const loadHtmlSortable = () => {
    const script = document.createElement('script');
    script.src = '/build/js/plugins/html.sortable.min.js';
    document.head.appendChild(script);
    return Promise.resolve();
};

export const loadTypeahead = () =>
    import(/* webpackChunkName: "typeahead" */ './typeahead.bundle.min');
