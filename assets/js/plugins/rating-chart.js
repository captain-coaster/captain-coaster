/**
 * Simple ApexCharts replacement for rating distribution bars
 * Replaces heavy ApexCharts library with lightweight CSS-based solution
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
    
    // Rating colors (1-5 stars)
    const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
    
    let html = '<div style="width:100%;height:100px;display:flex;flex-direction:column;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';
    
    // Progress bar
    html += '<div style="width:100%;height:8px;display:flex;border-radius:4px;overflow:hidden;background:#f1f5f9;box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);">';
    
    series.forEach((item, i) => {
        const value = item.data ? item.data[0] : 0;
        const percentage = (value / total) * 100;
        
        if (percentage > 0) {
            const stars = '★'.repeat(item.name);
            html += `<div style="
                width: ${percentage}%;
                background: ${colors[item.name - 1] || colors[4]};
                height: 100%;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
            " 
            title="${stars} ${percentage.toFixed(1)}% (${value} ratings)"
            onmouseover="this.style.transform='scaleY(1.5)';this.style.zIndex='10'"
            onmouseout="this.style.transform='scaleY(1)';this.style.zIndex='1'"></div>`;
        }
    });
    
    html += '</div>';
    
    // Legend
    html += '<div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:#64748b;">';
    html += '<span>1★</span><span>2★</span><span>3★</span><span>4★</span><span>5★</span>';
    html += '</div>';
    
    html += '</div>';
    this.element.innerHTML = html;
    
    // Dispatch event to notify that chart is ready
    window.dispatchEvent(new CustomEvent('apexcharts-ready'));
};

window.ApexCharts.prototype.destroy = function() {
    this.element.innerHTML = '';
};

// Make it available as default export too
export default window.ApexCharts;