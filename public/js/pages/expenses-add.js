(() => {
    function initExpensesAdd() {
        const expenseSelect = document.getElementById("expense");
        const balanceInput = document.getElementById("balance");
        const config = window.__expensesAdd || {};

        window.supplierSelected = function supplierSelected(supplierElem) {
            const forId = supplierElem?.dataset?.for || "supplier_id";
            const scope = supplierElem.closest("form") || document;
            const selectedOptionDataset = scope.querySelector(`.optionsDropdown li[data-for="${forId}"].selected`)?.dataset?.option;
            if (selectedOptionDataset) {
                const selectedSupplierData = JSON.parse(selectedOptionDataset);
                balanceInput.value = selectedSupplierData.balance || "0.00";
                const supplierCategories = selectedSupplierData.categories;

                let expenseOptions = `
                    <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- Select Expense --</li>
                `;

                supplierCategories.forEach(category => {
                    expenseOptions += `
                        <li data-for="expense" data-value="${category.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${category.title}</li>
                    `;
                });
                if (config.adjustmentId) {
                    expenseOptions += `
                        <li data-for="expense" data-value="${config.adjustmentId}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
                    `;
                }

                const expenseScope = expenseSelect.closest(".selectParent");
                const expenseDropdown = expenseScope?.querySelector(".optionsDropdown");
                const expenseDbInput = expenseScope?.querySelector('.dbInput[data-for="expense"]');
                if (expenseDropdown) {
                    expenseDropdown.innerHTML = expenseOptions;
                }
                if (expenseDbInput) {
                    expenseDbInput.value = "";
                }
                if (expenseScope) {
                    expenseScope.querySelectorAll('.optionsDropdown li[data-for="expense"]').forEach(li => li.classList.remove('selected'));
                }
                expenseSelect.value = "";
                expenseSelect.disabled = false;
            } else {
                const expenseScope = expenseSelect.closest(".selectParent");
                const expenseDropdown = expenseScope?.querySelector(".optionsDropdown");
                if (expenseDropdown) {
                    expenseDropdown.innerHTML = `
                        <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- No options available --</li>
                    `;
                }
                expenseSelect.disabled = true;
                balanceInput.value = "Balance";
            }
        };
    }

    window.initExpensesAdd = initExpensesAdd;

    function boot() {
        initExpensesAdd();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
