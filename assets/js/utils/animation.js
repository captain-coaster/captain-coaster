export function scaleUp(element, duration = 350) {
    element.style.animation = 'none';
    // Force reflow so clearing 'none' registers before re-applying
    void element.offsetHeight;
    element.style.animation = `popBounce ${duration}ms cubic-bezier(0.34, 1.56, 0.64, 1) both`;
    setTimeout(() => { element.style.animation = ''; }, duration);
}

/** Burst colored particles outward from an element's center (celebration). */
export function celebrationBurst(element, options = {}) {
    const {
        count = 10,
        colors = ['#22c55e', '#86efac', '#fbbf24', '#f472b6', '#60a5fa', '#a78bfa'],
        spread = 40,
        duration = 600,
    } = options;
    const rect = element.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    for (let i = 0; i < count; i++) {
        const p = document.createElement('span');
        const size = 5 + Math.random() * 4;
        p.style.cssText = `position:fixed;width:${size}px;height:${size}px;border-radius:50%;` +
            `background:${colors[i % colors.length]};left:${cx}px;top:${cy}px;` +
            `pointer-events:none;z-index:9999;transform:translate(-50%,-50%)`;
        document.body.appendChild(p);
        const angle = (i / count) * Math.PI * 2 + (Math.random() - 0.5) * 0.6;
        const dist = spread * (0.7 + Math.random() * 0.6);
        p.animate(
            [
                { transform: 'translate(-50%,-50%) scale(1)', opacity: 1 },
                {
                    transform: `translate(calc(-50% + ${Math.cos(angle) * dist}px), calc(-50% + ${Math.sin(angle) * dist}px)) scale(0)`,
                    opacity: 0,
                },
            ],
            { duration, easing: 'ease-out', fill: 'forwards' }
        ).onfinish = () => p.remove();
    }
}
