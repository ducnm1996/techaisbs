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
    const gender = root.querySelector('#baby-gender');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const dayMilliseconds = 86400000;
    const start = Date.parse(`${root.dataset.start}T00:00:00Z`);
    const birth = Date.parse(`${root.dataset.birth}T00:00:00Z`);
    const birthOffset = Math.round((birth - start) / dayMilliseconds);
    const maxDays = +slider.max;
    let timer = null;
    let preview = false;
    let selectedDays = 0;
    let motionPaused = reduced.matches;
    const vietnamDate = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit',
    });
    function currentDays() {
        const parts = Object.fromEntries(vietnamDate.formatToParts(new Date()).map(part => [part.type, part.value]));
        return Math.max(0, Math.round((Date.UTC(+parts.year, +parts.month - 1, +parts.day) - start) / dayMilliseconds));
    }
    // Calendar anniversaries clamp February 29 to February 28 in non-leap years.
    function anniversary(years, months = 0) {
        const b = new Date(birth);
        const first = new Date(Date.UTC(b.getUTCFullYear() + years, b.getUTCMonth() + months, 1));
        const lastDay = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
        first.setUTCDate(Math.min(b.getUTCDate(), lastDay));
        return +first;
    }
    function ageAt(timestamp) {
        const b = new Date(birth);
        const d = new Date(timestamp);
        let years = d.getUTCFullYear() - b.getUTCFullYear();
        if (timestamp < anniversary(years)) years--;
        let totalMonths = years * 12;
        while (anniversary(0, totalMonths + 1) <= timestamp) totalMonths++;
        const months = totalMonths - years * 12;
        const days = Math.round((timestamp - anniversary(0, totalMonths)) / dayMilliseconds);
        return { years, months, days };
    }
    function render(days) {
        const illustrated = Math.min(maxDays, Math.max(0, days));
        selectedDays = illustrated;
        slider.value = illustrated;
        const timestamp = start + illustrated * dayMilliseconds;
        const child = illustrated >= birthOffset;
        let label;
        if (child) {
            const age = ageAt(timestamp);
            label = age.years ? `${age.years} tuổi ${age.months} tháng` : `${age.months} tháng ${age.days} ngày`;
            const milestones = [0, 1, 3, 6, 12, 18];
            let stage = 0;
            while (stage < 5 && timestamp >= anniversary(milestones[stage + 1])) stage++;
            scene.dataset.stage = 'child';
            scene.style.setProperty('--child-x', `${stage * 20}%`);
            scene.style.setProperty('--child-y', gender.value === 'girl' ? '100%' : '0%');
            const from = anniversary(milestones[stage]);
            const to = anniversary(milestones[Math.min(stage + 1, 5)]);
            const progress = to === from ? 1 : (timestamp - from) / (to - from);
            scene.style.setProperty('--baby-scale', String(0.86 + progress * 0.14));
            description.textContent = `${gender.value === 'girl' ? 'Bé gái' : 'Bé trai'} · ${['Những ngày đầu trong vòng tay mẹ.', 'Chặng đường khám phá thế giới.', 'Tuổi thơ đầy tò mò và niềm vui.', 'Những năm tháng đến trường.', 'Hành trình tuổi thiếu niên.', 'Chào tuổi 18, một chặng đường mới!'][stage]}`;
        } else {
            label = `${Math.floor(illustrated / 7)} tuần ${illustrated % 7} ngày`;
            const stage = illustrated < 56 ? 'early' : illustrated < 98 ? '0' : illustrated < 168 ? '1' : illustrated < 238 ? '2' : '3';
            scene.dataset.stage = stage;
            const bounds = { early: [0, 56], 0: [56, 98], 1: [98, 168], 2: [168, 238], 3: [238, Math.max(239, birthOffset)] }[stage];
            const progress = Math.min(1, (illustrated - bounds[0]) / (bounds[1] - bounds[0]));
            scene.style.setProperty('--baby-scale', String(0.76 + progress * 0.24));
            description.textContent = stage === 'early'
                ? 'Những tuần đầu: ánh sáng nhỏ tượng trưng cho sự khởi đầu của bé.'
                : 'Bé cuộn mình trong không gian ấm áp, cùng mẹ đi qua từng tuần thai.';
        }
        title.textContent = label;
        slider.setAttribute('aria-valuetext', label);
        const selectedDate = new Date(start + days * dayMilliseconds);
        const basis = root.dataset.estimated === 'true' && child ? ' · Theo ngày sinh dự kiến' : '';
        date.textContent = `${preview ? 'Đang xem minh họa' : 'Hôm nay'} · ${selectedDate.toLocaleDateString('vi-VN', { timeZone: 'UTC' })}${basis}${days > maxDays ? ' · Hình dừng ở 18 tuổi' : ''}`;
        scene.setAttribute('aria-label', `Bé Mùi: hình minh họa ${label}`);
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
    gender.addEventListener('change', () => render(preview ? selectedDays : currentDays()));
    root.querySelectorAll('[data-baby-offset]').forEach(button => button.addEventListener('click', () => {
        stop(); preview = true; render(Math.min(maxDays, +button.dataset.babyOffset));
    }));
    root.querySelector('[data-baby-today]').addEventListener('click', () => { stop(); preview = false; render(currentDays()); });
    play.addEventListener('click', () => {
        if (timer) { stop(); return; }
        preview = true;
        if (+slider.value >= maxDays) render(0);
        play.textContent = 'Ⅱ Tạm dừng';
        play.setAttribute('aria-pressed', 'true');
        timer = window.setInterval(() => {
            // Accelerate childhood while retaining every-day selection on the slider.
            const step = selectedDays < birthOffset ? 3 : selectedDays < birthOffset + 365 ? 7 : 30;
            const next = Math.min(maxDays, selectedDays + step);
            render(next);
            if (next === maxDays) stop();
        }, reduced.matches ? 500 : 120);
    });
    motion.addEventListener('click', () => { motionPaused = !motionPaused; updateMotion(); });
    reduced.addEventListener('change', () => { stop(); motionPaused = reduced.matches; updateMotion(); });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        if (!preview) render(currentDays());
    });
    window.setInterval(() => { if (!preview && !document.hidden) render(currentDays()); }, 30000);
    updateMotion();
    render(currentDays());
})();
