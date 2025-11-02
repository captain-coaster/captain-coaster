/**
 * Pure CSS Rating Chart - No dependencies!
 * Replaces 500KB ApexCharts with 2KB of CSS/JS for simple rating bars
 */

// Simple ApexCharts replacement - just for rating distribution bars
window.ApexCharts = function(element, options) {
    this.element = element;
    this.options = options;
};

window.ApexCharts.prototype.render = function() {
    const series = this.options.series || [];
    const total = series.reduce((sum, item) => sum + (item.data ? item.data[0] : 0), 0);
    
    if (total === 0) {
        this.element.innerHTML = '<div style="text-align:center;color:#999;padding:40px;font-size:14px;">No ratings yet</div>';
        return;
    }
    
    // Modern rating colors with better contrast
    const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
    
    // Add CSS animation keyframes if not already added
    if (!document.querySelector('#rating-chart-animations')) {
        const style = document.createElement('style');
        style.id = 'rating-chart-animations';
        style.textContent = `
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes fillBar {
                from { width: 0%; }
                to { width: var(--target-width); }
            }
        `;
        document.head.appendChild(style);
    }

    let html = '<div style="width:100%;height:100px;display:flex;flex-direction:column;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;animation:fadeInUp 0.6s ease-out;">';
    
    // Modern progress bar with subtle shadow
    html += '<div style="width:100%;height:8px;display:flex;border-radius:4px;overflow:hidden;background:#f1f5f9;box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);">';
    
    series.forEach((item, i) => {
        const value = item.data ? item.data[0] : 0;
        const percentage = (value / total) * 100;
        
        if (percentage > 0) {
            const stars = '★'.repeat(i + 1);
            html += `<div style="
                --target-width: ${percentage}%;
                width: ${percentage}%;
                background: linear-gradient(135deg, ${colors[i]}, ${colors[i]}dd);
                height: 100%;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                animation: fillBar 0.8s ease-out ${i * 0.1}s both;
            " 
            class="rating-segment"
            title="${stars} ${percentage.toFixed(1)}% (${value} ratings)"
            onmouseover="this.style.transform='scaleY(1.5)';this.style.zIndex='10'"
            onmouseout="this.style.transform='scaleY(1)';this.style.zIndex='1'"></div>`;
        }
    });
    
    html += '</div>';
    
    // Add subtle legend below
    html += '<div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:#64748b;">';
    html += '<span>1★</span><span>2★</span><span>3★</span><span>4★</span><span>5★</span>';
    html += '</div>';
    
    html += '</div>';
    this.element.innerHTML = html;
};

window.ApexCharts.prototype.destroy = function() {
    this.element.innerHTML = '';
};

// Make it available as default export too
export default window.ApexCharts;