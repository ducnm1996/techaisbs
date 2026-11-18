document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-photo-input]').forEach(input => {
    const preview = input.closest('form').querySelector('[data-photo-preview]');
    let imageUrls = [];

    input.addEventListener('change', () => {
        imageUrls.forEach(url => URL.revokeObjectURL(url));
        imageUrls = [];
        preview.replaceChildren();
        input.setCustomValidity('');
        const files = Array.from(input.files);

        if (files.length > 4) {
            input.setCustomValidity('Mỗi bài nhật ký có tối đa 4 ảnh.');
        } else if (files.some(file => file.size > 2 * 1024 * 1024)) {
            input.setCustomValidity('Mỗi ảnh có dung lượng tối đa 2 MB.');
        }

        preview.hidden = files.length === 0;
        files.slice(0, 4).forEach(file => {
            const image = document.createElement('img');
            const url = URL.createObjectURL(file);
            imageUrls.push(url);
            image.src = url;
            image.alt = file.name;
            image.width = 240;
            image.height = 180;
            preview.append(image);
        });
        if (!input.checkValidity()) input.reportValidity();
    });
});
