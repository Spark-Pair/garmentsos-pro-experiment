(() => {
    function initExpensesEdit() {
        const config = window.__expensesEdit || {};
        const selectedExpense = config.selectedExpense || "";
        const supplierData = config.supplierData || null;

        window.supplierSelected = function supplierSelected(supplier) {
            const expenseSelect = document.getElementById("expense");
            const selectedSupplierData = typeof supplier === "string" ? JSON.parse(supplier) : supplier;

            const supplierCategories = selectedSupplierData.categories;
            let expenseOptions = `
                <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- Select Expense --</li>
            `;

            supplierCategories.forEach(category => {
                expenseOptions += `
                    <li data-for="expense" data-value="${category.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${category.title}</li>
                `;
            });
            if (config.adjustmentId) {
                expenseOptions += `
                    <li data-for="expense" data-value="${config.adjustmentId}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
                `;
            }

            const expenseScope = expenseSelect.closest(".selectParent");
            const expenseDropdown = expenseScope?.querySelector(".optionsDropdown");
            if (expenseDropdown) {
                expenseDropdown.innerHTML = expenseOptions;
            }
            expenseSelect.disabled = false;
        };

        if (supplierData) {
            supplierSelected(supplierData);
        }

        const expenseOption = document
            .getElementById("expense")
            ?.closest(".selectParent")
            ?.querySelector(`.optionsDropdown li[data-value="${selectedExpense}"]`);
        if (expenseOption) {
            selectThisOption(expenseOption);
        }
    }

    window.initExpensesEdit = initExpensesEdit;

    function boot() {
        if (window.__expensesEdit) initExpensesEdit();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
