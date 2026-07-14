document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form.matches('form[data-product-update]')) {
        return;
    }

    event.preventDefault();

    const url = form.getAttribute('action');
    const formData = new FormData(form);
    const feedback = form.querySelector('.update-feedback');

    fetch(url, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'fetch' },
    })
        .then((response) => response.json())
        .then((data) => {
            if (feedback) {
                feedback.textContent = data.message || 'Atualizado.';
            }
        })
        .catch(() => {
            if (feedback) {
                feedback.textContent = 'Erro ao atualizar.';
            }
        });
});
