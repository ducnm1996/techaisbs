(() => {
    const root = document.querySelector('[data-baby-growth]');
    if (!root) return;
    const scene = root.querySelector('.baby-scene');
    const slider = root.querySelector('.baby-range');
    const title = root.querySelector('h2');
    const date = root.querySelector('[data-baby-date]');
    const description = root.querySelector('[data-baby-description]');
    const play = root.querySelector('[data-baby-play]');
    const motion = root.querySelector('[data-baby-motion]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const dayMilliseconds = 86400000;
    const start = Date.parse(`${root.dataset.start}T00:00:00Z`);
    let timer = null;
    let preview = false;
    let motionPaused = reduced.matches;
    const vietnamDate = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit',
    });
    function currentDays() {
        const parts = Object.fromEntries(vietnamDate.formatToParts(new Date()).map(part => [part.type, part.value]));
        return Math.max(0, Math.round((Date.UTC(+parts.year, +parts.month - 1, +parts.day) - start) / dayMilliseconds));
    }
    function render(days) {
        const illustrated = Math.min(294, days);
        slider.value = illustrated;
        slider.setAttribute('aria-valuetext', `${Math.floor(illustrated / 7)} tuần ${illustrated % 7} ngày`);
        title.textContent = `${Math.floor(days / 7)} tuần ${days % 7} ngày`;
        const selectedDate = new Date(start + days * dayMilliseconds);
        date.textContent = `${preview ? 'Đang xem minh họa' : 'Hôm nay'} · ${selectedDate.toLocaleDateString('vi-VN', { timeZone: 'UTC' })}`;
        const stage = illustrated < 56 ? 'early' : illustrated < 98 ? '0' : illustrated < 168 ? '1' : illustrated < 238 ? '2' : '3';
        scene.dataset.stage = stage;
        // Relative visual scale only; these are not fetal measurements.
        const bounds = { early: [0, 56], 0: [56, 98], 1: [98, 168], 2: [168, 238], 3: [238, 294] }[stage];
        const progress = (illustrated - bounds[0]) / (bounds[1] - bounds[0]);
        scene.style.setProperty('--baby-scale', String(0.76 + progress * 0.24));
        description.textContent = stage === 'early'
            ? 'Những tuần đầu của hành trình. Ánh sáng nhỏ tượng trưng cho sự khởi đầu của bé.'
            : stage === '0' ? 'Một hình hài nhỏ xíu, khép mình trong không gian ấm áp.'
            : stage === '1' ? 'Tay chân và đường nét của bé hiện rõ hơn trong hình minh họa.'
            : stage === '2' ? 'Bé lớn dần, cuộn mình với đôi tay và đôi chân nhỏ.'
            : 'Hành trình đến những tuần cuối, hình bé tròn trịa hơn trong vòng ôm của mẹ.';
        scene.setAttribute('aria-label', `Bé Mùi: hình minh họa ${Math.floor(illustrated / 7)} tuần ${illustrated % 7} ngày`);
    }
    function stop() {
        window.clearInterval(timer);
        timer = null;
        play.textContent = '▶ Xem bé lớn lên';
        play.setAttribute('aria-pressed', 'false');
    }
    function updateMotion() {
        root.classList.toggle('baby-paused', motionPaused);
        motion.textContent = motionPaused ? 'Bật chuyển động' : 'Dừng chuyển động';
        motion.setAttribute('aria-pressed', String(motionPaused));
    }
    slider.addEventListener('input', () => { stop(); preview = true; render(+slider.value); });
    root.querySelector('[data-baby-today]').addEventListener('click', () => { stop(); preview = false; render(currentDays()); });
    play.addEventListener('click', () => {
        if (timer) { stop(); return; }
        preview = true;
        if (+slider.value >= 294) render(0);
        play.textContent = 'Ⅱ Tạm dừng';
        play.setAttribute('aria-pressed', 'true');
        timer = window.setInterval(() => {
            const next = Math.min(294, +slider.value + 1);
            render(next);
            if (next === 294) stop();
        }, reduced.matches ? 500 : 100);
    });
    motion.addEventListener('click', () => { motionPaused = !motionPaused; updateMotion(); });
    reduced.addEventListener('change', () => { stop(); motionPaused = reduced.matches; updateMotion(); });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        if (!preview) render(currentDays());
    });
    // Recompute by calendar date in Vietnam, including a tab left open overnight.
    window.setInterval(() => { if (!preview && !document.hidden) render(currentDays()); }, 30000);
    updateMotion();
    render(currentDays());
})();
