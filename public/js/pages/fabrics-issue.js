(function () {
    const unitInoDom = document.getElementById('unit');
    const avalaibleStockInpDom = document.getElementById('avalaible_stock');
    const quantityErrorDom = document.getElementById('quantity-error');

    window.trackTagSelect = function trackTagSelect(elem) {
        const selectedTag = JSON.parse(
            elem.parentElement.parentElement.parentElement
                .querySelector('li.selected')
                ?.getAttribute('data-option') ?? '{}'
        );
        if (unitInoDom) unitInoDom.value = selectedTag.unit;
        if (avalaibleStockInpDom) avalaibleStockInpDom.value = selectedTag.avalaible_sock;
    };

    window.trackQuantity = function trackQuantity(elem) {
        const maxStock = parseInt(avalaibleStockInpDom?.value || '0');
        const currentVal = parseInt(elem.value || '0');

        if (currentVal > maxStock) {
            elem.value = maxStock;
            if (quantityErrorDom) {
                quantityErrorDom.textContent = 'Maximum';
                quantityErrorDom.classList.remove('hidden');
            }
        } else {
            elem.value = currentVal;
        }
    };
})();
