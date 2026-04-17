(() => {
    function initStatementAdjustmentsCreate() {
        const config = window.__statementAdjustmentsCreate || {};

        function getNameLabel(category, item) {
            if (category === 'customer') {
                return `${item.customer_name} | ${item.city?.title ?? item.city?.short_title ?? '-'}`;
            }

            if (category === 'supplier') {
                return item.supplier_name ?? '-';
            }

            if (category === 'bank_account') {
                return `${item.account_title ?? '-'} | ${item.bank?.short_title ?? '-'}`;
            }

            return '-';
        }

        function fillAdjustableOptions(category, items) {
            const hiddenInput = document.querySelector('input.dbInput[data-for="adjustable_id"]');
            const visibleInput = document.getElementById('adjustable_id');
            const optionsDropdown = document.querySelector('ul[data-for="adjustable_id"]');

            if (!hiddenInput || !visibleInput || !optionsDropdown) return;

            let optionsHtml = '<li data-for="adjustable_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">-- Select Name --</li>';

            items.forEach(item => {
                optionsHtml += `<li data-for="adjustable_id" data-value="${item.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${getNameLabel(category, item)}</li>`;
            });

            optionsDropdown.innerHTML = optionsHtml;
            visibleInput.disabled = items.length === 0;
            visibleInput.placeholder = items.length === 0 ? '-- No options available --' : '-- Select Name --';

            const selectedId = String(config.oldAdjustableId || '');
            if (selectedId) {
                const selectedOption = optionsDropdown.querySelector(`li[data-value="${selectedId}"]`);
                if (selectedOption) {
                    selectThisOption(selectedOption);
                    config.oldAdjustableId = '';
                    return;
                }
            }

            const defaultOption = optionsDropdown.querySelector('li[data-value=""]');
            if (defaultOption) {
                selectThisOption(defaultOption);
            }
        }

        window.onStatementAdjustmentCategoryChange = function onStatementAdjustmentCategoryChange(selectElement) {
            const category = selectElement.value;
            const visibleInput = document.getElementById('adjustable_id');
            const hiddenInput = document.querySelector('input.dbInput[data-for="adjustable_id"]');
            const optionsDropdown = document.querySelector('ul[data-for="adjustable_id"]');

            if (!category) {
                if (visibleInput) visibleInput.disabled = true;
                if (hiddenInput) hiddenInput.value = '';
                if (optionsDropdown) {
                    optionsDropdown.innerHTML = '<li data-for="adjustable_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">-- Select Name --</li>';
                }
                return;
            }

            $.ajax({
                url: config.namesUrl,
                type: 'POST',
                data: {
                    _token: config.csrfToken,
                    category: category,
                },
                success: function (response) {
                    fillAdjustableOptions(category, response || []);
                },
                error: function (xhr) {
                    console.error('Failed to load names:', xhr);
                },
            });
        };

        if (config.oldCategory) {
            const categoryInput = document.querySelector('input.dbInput[data-for="category"]');
            if (categoryInput) {
                const currentOption = document.querySelector(`ul[data-for="category"] li[data-value="${config.oldCategory}"]`);
                if (currentOption) {
                    selectThisOption(currentOption);
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.__statementAdjustmentsCreate) {
                initStatementAdjustmentsCreate();
            }
        });
    } else if (window.__statementAdjustmentsCreate) {
        initStatementAdjustmentsCreate();
    }
})();
