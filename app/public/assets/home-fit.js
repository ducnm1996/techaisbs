(() => {
    const stage = document.querySelector('[data-home-stage]');
    const canvas = document.querySelector('[data-home-canvas]');
    if (!stage || !canvas) return;
    let frame;
    function fit() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const width = Math.max(360, Math.min(1100, stage.clientWidth));
            canvas.style.width = `${width}px`;
            const scale = Math.min(1, stage.clientWidth / width, stage.clientHeight / canvas.offsetHeight);
            canvas.style.transform = `translateX(-50%) scale(${Math.max(0.01, scale)})`;
        });
    }
    const observer = new ResizeObserver(fit);
    observer.observe(stage);
    observer.observe(canvas);
    document.fonts.ready.then(fit);
    window.visualViewport?.addEventListener('resize', fit);
    fit();
})();
