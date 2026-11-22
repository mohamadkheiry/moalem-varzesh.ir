(() => {
    const hero = document.querySelector('[data-hero]');
    if (!hero) return;

    const stage = hero.querySelector('.hero-stage');
    const slides = [...hero.querySelectorAll('.hero-slide')];
    const dots = [...hero.querySelectorAll('[data-hero-index]')];
    const controls = hero.querySelector('.hero-controls');
    const toggle = hero.querySelector('[data-hero-toggle]');
    const announcement = hero.querySelector('[data-hero-announcement]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let target = 0;
    let timer;
    let visible = false;
    let hovered = false;
    let paused = reduced.matches;
    let startedByToggle = false;
    let touch;

    function updateToggle() {
        toggle.dataset.paused = String(paused);
        toggle.setAttribute('aria-label', paused ? 'شروع حرکت خودکار' : 'توقف حرکت خودکار');
    }

    function schedule() {
        clearTimeout(timer);
        const focus = document.activeElement;
        const explicitStart = startedByToggle && focus === toggle;
        if (!paused && visible && !document.hidden
            && (explicitStart || (!hovered && !hero.contains(focus)))) {
            timer = setTimeout(() => void show(index + 1), 6500);
        }
    }

    function load(slide) {
        const img = slide.querySelector('img');
        if (img.dataset.src) {
            const source = slide.querySelector('source');
            source.srcset = source.dataset.srcset;
            delete source.dataset.srcset;
            img.src = img.dataset.src;
            delete img.dataset.src;
        }
        return img.decode();
    }

    async function show(requested, manual = false) {
        clearTimeout(timer);
        const next = (requested + slides.length) % slides.length;
        target = next;
        try {
            await load(slides[next]);
        } catch {
            if (next === target) schedule();
            return;
        }
        if (next !== target) return;
        slides[index].classList.remove('is-active');
        dots[index].setAttribute('aria-pressed', 'false');
        index = next;
        slides[index].classList.add('is-active');
        dots[index].setAttribute('aria-pressed', 'true');
        if (manual) announcement.textContent = dots[index].getAttribute('aria-label');
        schedule();
    }

    hero.querySelectorAll('[data-hero-step]').forEach(button => {
        button.addEventListener('click', () => void show(target + Number(button.dataset.heroStep), true));
    });
    dots.forEach(button => {
        button.addEventListener('click', () => void show(Number(button.dataset.heroIndex), true));
    });
    toggle.addEventListener('click', () => {
        paused = !paused;
        startedByToggle = !paused;
        updateToggle();
        schedule();
    });
    reduced.addEventListener('change', event => {
        paused = event.matches;
        startedByToggle = false;
        updateToggle();
        schedule();
    });

    hero.addEventListener('pointerenter', event => {
        if (event.pointerType === 'mouse') hovered = true;
        schedule();
    });
    hero.addEventListener('pointerleave', () => {
        hovered = false;
        schedule();
    });
    hero.addEventListener('focusin', schedule);
    hero.addEventListener('focusout', () => queueMicrotask(schedule));
    toggle.addEventListener('blur', () => { startedByToggle = false; });
    document.addEventListener('visibilitychange', schedule);
    stage.addEventListener('touchstart', event => {
        touch = event.touches.length === 1 ? event.touches[0] : undefined;
    }, { passive: true });
    stage.addEventListener('touchend', event => {
        if (!touch) return;
        const point = event.changedTouches[0];
        const dx = point.clientX - touch.clientX;
        const dy = point.clientY - touch.clientY;
        touch = undefined;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            void show(target + (dx > 0 ? 1 : -1), true);
        }
    }, { passive: true });
    stage.addEventListener('touchcancel', () => { touch = undefined; }, { passive: true });

    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        schedule();
    }, { threshold: .4 }).observe(hero);

    updateToggle();
    controls.hidden = false;
})();
