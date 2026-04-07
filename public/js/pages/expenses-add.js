(() => {
    function initExpensesAdd() {
        const expenseSelect = document.getElementById("expense");
        const balanceInput = document.getElementById("balance");

        window.supplierSelected = function supplierSelected(supplierElem) {
            const selectedOptionDataset =
                supplierElem.parentElement.parentElement.parentElement?.querySelector("ul li.selected").dataset.option;
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
                expenseOptions += `
                    <li data-for="expense" data-value="adjustment" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
                `;

                expenseSelect.parentElement.parentElement.parentElement.querySelector("ul").innerHTML = expenseOptions;
                expenseSelect.disabled = false;
            } else {
                expenseSelect.innerHTML = '<option value="">-- No options available --</option>';
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
