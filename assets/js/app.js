document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        var text = form.getAttribute('data-confirm') || 'Подтвердите действие';
        if (!window.confirm(text)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }
        var button = form.querySelector('[type="submit"]');
        if (!button) {
            return;
        }
        window.setTimeout(function () {
            button.disabled = true;
        }, 0);
    });
});

document.querySelectorAll('[data-full]').forEach(function (button) {
    button.addEventListener('click', function () {
        var image = document.getElementById('gallery-main');
        if (image) {
            image.src = button.getAttribute('data-full');
        }
        document.querySelectorAll('[data-full]').forEach(function (item) {
            item.classList.remove('is-active');
        });
        button.classList.add('is-active');
    });
});

var cardInput = document.querySelector('[data-card]');
var cardPreview = document.querySelector('[data-card-preview]');
if (cardInput && cardPreview) {
    cardInput.addEventListener('input', function () {
        var digits = cardInput.value.replace(/\D/g, '').slice(0, 19);
        cardInput.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        cardPreview.textContent = digits.length >= 4 ? '•••• ' + digits.slice(-4) : 'номер не сохранится целиком';
    });
}
