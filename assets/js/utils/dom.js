export function show(element) {
    element.classList.remove('hidden');
}

export function hide(element) {
    element.classList.add('hidden');
}

export function toggle(element) {
    element.classList.toggle('hidden');
}

export function lockScroll() {
    document.body.classList.add('overflow-hidden');
}

export function unlockScroll() {
    document.body.classList.remove('overflow-hidden');
}
