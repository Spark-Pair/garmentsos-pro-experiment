function checkMax(input) {
    input.value = input.value.replace(/\D/g, '');

    let errorElem = document.getElementById(input.id + '-error');

    const max = parseInt(input.max, 10);
    if (parseInt(input.value, 10) > max) {
        errorElem.textContent = `Value cannot exceed ${max}.`;
        if (errorElem.classList.contains('hidden')) {
            errorElem.classList.remove('hidden');
        }

        input.value = max;
    } else {
        errorElem.textContent = '';
        if (!errorElem.classList.contains('hidden')) {
            errorElem.classList.add('hidden');
        }
    }
}
