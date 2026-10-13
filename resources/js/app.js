const menuButton = document.querySelector('.menu-button');
const nav = document.querySelector('.nav');
menuButton?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    menuButton.setAttribute('aria-expanded', String(open));
});

document.querySelector('[data-track-toggle]')?.addEventListener('click', () => {
    const form = document.querySelector('[data-track-form]');
    form.hidden = !form.hidden;
});

const toast = document.querySelector('[data-toast]');
document.querySelectorAll('[data-cart-add]').forEach((button) => button.addEventListener('click', () => {
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 2200);
}));

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealElements = document.querySelectorAll('[data-reveal]');
const animateCounter = (element) => {
    if (element.dataset.counted) return;
    element.dataset.counted = 'true';
    const target = Number(element.dataset.counter);
    const duration = reducedMotion ? 0 : 1100;
    const start = performance.now();
    const tick = (now) => {
        const progress = duration === 0 ? 1 : Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.round(target * eased).toLocaleString('id-ID');
        if (element.firstChild?.nodeType === Node.TEXT_NODE) element.firstChild.nodeValue = `${value} `;
        else element.textContent = value;
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
};

if ('IntersectionObserver' in window && !reducedMotion) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        entry.target.querySelectorAll?.('[data-counter]').forEach(animateCounter);
        if (entry.target.matches('[data-counter]')) animateCounter(entry.target);
        observer.unobserve(entry.target);
    }), { threshold: 0.12, rootMargin: '0px 0px -40px' });
    revealElements.forEach((element) => observer.observe(element));
    document.querySelectorAll('[data-counter]').forEach((element) => observer.observe(element));
} else {
    revealElements.forEach((element) => element.classList.add('is-visible'));
    document.querySelectorAll('[data-counter]').forEach(animateCounter);
}

if (!reducedMotion && window.matchMedia('(pointer: fine)').matches) {
    document.querySelectorAll('[data-tilt]').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const rotateY = ((event.clientX - rect.left) / rect.width - 0.5) * 5;
            const rotateX = (0.5 - (event.clientY - rect.top) / rect.height) * 5;
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
        card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });

    document.querySelector('[data-hero]')?.addEventListener('pointermove', (event) => {
        const x = (event.clientX / window.innerWidth - 0.5) * 18;
        const y = (event.clientY / window.innerHeight - 0.5) * 18;
        document.querySelector('.aurora-one')?.style.setProperty('translate', `${x}px ${y}px`);
        document.querySelector('.aurora-two')?.style.setProperty('translate', `${-x}px ${-y}px`);
    });
}

document.querySelector('[data-copy]')?.addEventListener('click', async (event) => {
    await navigator.clipboard.writeText(event.currentTarget.dataset.copy);
    event.currentTarget.textContent = 'Tersalin ✓';
});
