(function () {
    const workerSelectDom = document.getElementById('worker');
    const dateDom = document.getElementById('date');
    const remainingStockInpDom = document.getElementById('remaining_stock');
    const quantityErrorDom = document.getElementById('quantity-error');

    window.trackWorkerState = function trackWorkerState(elem) {
        if (!dateDom) return;
        if (elem.value === '') {
            dateDom.disabled = true;
            dateDom.value = '';
        } else {
            dateDom.disabled = false;
        }
    };

    window.trackDateState = function trackDateState() {
        if (!workerSelectDom || !dateDom) return;
        let selectedWorkerId = workerSelectDom.closest('.selectParent')?.querySelector('.dbInput[data-for="worker"]')?.value;

        if (!selectedWorkerId && workerSelectDom.value) {
            const workerOption = workerSelectDom
                .closest('.selectParent')
                ?.querySelector(`ul li[data-value][data-for="worker"]`);
            if (workerOption) {
                const matches = Array.from(workerSelectDom.closest('.selectParent').querySelectorAll('ul li[data-for="worker"]'))
                    .find(li => li.textContent.trim() === workerSelectDom.value.trim());
                if (matches) {
                    selectThisOption(matches);
                    selectedWorkerId = matches.dataset.value;
                }
            }
        }

        if (!selectedWorkerId) {
            return;
        }

        $.ajax({
            url: window.__fabricsReturn?.returnUrl || '',
            method: 'GET',
            data: {
                worker_id: selectedWorkerId,
                date: dateDom.value,
            },
            success: function (response) {
                $('#tag').closest('.selectParent').html($(response).find('#tag').closest('.selectParent').html());
                if (typeof bootSelectDefaults === 'function') {
                    bootSelectDefaults();
                }
            },
            error: function (xhr) {
                console.error(xhr.responseText);
            },
        });
    };

    window.trackTagSelect = function trackTagSelect(elem) {
        const selectedTag = JSON.parse(
            elem.closest('.selectParent')?.querySelector('li.selected')?.getAttribute('data-option') ?? '{}'
        );

        if (remainingStockInpDom) {
            remainingStockInpDom.value = selectedTag.remaining;
        }
    };

    window.trackQuantity = function trackQuantity(elem) {
        const maxStock = parseInt(remainingStockInpDom?.value || '0');
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

    function initFabricsReturn() {}

    window.initFabricsReturn = initFabricsReturn;

    function boot() {
        if (window.__fabricsReturn) {
            initFabricsReturn(window.__fabricsReturn);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
