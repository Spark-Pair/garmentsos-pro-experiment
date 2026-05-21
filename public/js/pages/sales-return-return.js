(() => {
    function initSalesReturnReturn() {
        const config = window.__salesReturnReturn || {};
        let selectedInvoice = {};
        let selectedArticleId = 0;

        window.onCustomerSelect = function onCustomerSelect(selectElement) {
            const selectedCustomerId = selectElement.value;
            if (selectedCustomerId) {
                $.ajax({
                    url: config.detailsUrl,
                    type: "POST",
                    data: {
                        customer_id: selectedCustomerId,
                        getArticles: true,
                        _token: config.csrfToken,
                    },
                    success: function (response) {
                        const articleSelect = document.getElementById("article");
                        articleSelect.disabled = false;

                        const articleSelectDropdown = articleSelect
                            .parentElement.parentElement.parentElement.querySelector(".optionsDropdown");
                        articleSelectDropdown.innerHTML = "";
                        let clutter =
                            '<li data-for="article" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]" >-- Select Article --</li>';
                        response.forEach(article => {
                            clutter += `<li data-for="article" data-value="${article.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden hidden">${article.article_no}</li>`;
                        });
                        articleSelectDropdown.innerHTML = clutter;

                        const firstOption = articleSelectDropdown.querySelector("li");
                        if (firstOption) {
                            selectThisOption(firstOption);
                        }
                    },
                    error: function (xhr) {
                        console.error("Error fetching details:", xhr);
                    },
                });
            } else {
                const articleSelect = document.getElementById("article");
                articleSelect.disabled = true;
            }
        };

        window.onArticleSelect = function onArticleSelect(selectElement) {
            selectedArticleId = parseInt(selectElement.value);
            const customerId = document.querySelector('.dbInput[data-for="customer"]').value;

            if (selectedArticleId && customerId) {
                $.ajax({
                    url: config.detailsUrl,
                    type: "POST",
                    data: {
                        customer_id: customerId,
                        article_id: selectedArticleId,
                        getInvoices: true,
                        _token: config.csrfToken,
                    },
                    success: function (response) {
                        const invoiceSelect = document.getElementById("invoice");
                        invoiceSelect.disabled = false;

                        const invoiceSelectDropdown = invoiceSelect
                            .parentElement.parentElement.parentElement.querySelector(".optionsDropdown");
                        invoiceSelectDropdown.innerHTML = "";
                        let clutter =
                            '<li data-for="invoice" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]" >-- Select Invoice --</li>';
                        response.forEach(invoice => {
                            clutter += `<li data-for="invoice" data-invoice-data='${JSON.stringify(
                                invoice
                            )}' data-value="${invoice.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden hidden">${invoice.invoice_no} | ${invoice.articles_in_invoice[0].invoice_quantity} - PCs | ${invoice.discount}% | Rs. ${formatMoney(invoice.sales_rate)}</li>`;
                        });
                        invoiceSelectDropdown.innerHTML = clutter;

                        const firstOption = invoiceSelectDropdown.querySelector("li");
                        if (firstOption) {
                            selectThisOption(firstOption);
                        }
                    },
                    error: function (xhr) {
                        console.error("Error fetching details:", xhr);
                    },
                });
            } else {
                const invoiceSelect = document.getElementById("invoice");
                invoiceSelect.disabled = true;
            }
        };

        window.onInvoiceSelect = function onInvoiceSelect(selectElement) {
            if (selectElement.value) {
                const invoiceData = JSON.parse(
                    selectElement.parentElement.querySelector(`.optionsDropdown li.selected`).dataset.invoiceData
                );
                selectedInvoice = invoiceData;

                const invoiceDate = invoiceData.date;
                const dateInput = document.getElementById("date");
                dateInput.min = invoiceDate.split("T")[0];
                dateInput.disabled = false;
                dateInput.value = new Date().toISOString().split("T")[0];

                const quantityInput = document.getElementById("quantity");
                quantityInput.disabled = false;
            } else {
                document.getElementById("date").value = "";
                document.getElementById("date").disabled = true;

                document.getElementById("quantity").value = "";
                document.getElementById("quantity").disabled = true;
            }
        };

        window.onQuantityInput = function onQuantityInput(quantityInput) {
            selectedInvoice.articles_in_invoice.forEach(article => {
                if (article.id === selectedArticleId) {
                    document.getElementById("amount").value =
                        quantityInput.value *
                        article.sales_rate *
                        (1 - selectedInvoice.discount / 100);
                }
            });
        };
    }

    window.initSalesReturnReturn = initSalesReturnReturn;

    function boot() {
        if (window.__salesReturnReturn) {
            initSalesReturnReturn();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
