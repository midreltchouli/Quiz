document.querySelectorAll('select[data-language-picker]').forEach((select) => {
    select.addEventListener('change', () => {
        if (select.form) select.form.requestSubmit();
    });
});
