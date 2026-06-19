(function () {
    let order = null;
    let maxLimitOfArticles = 500;
    let limitOfArticles = 500;
    let selectedArticles = [];
    let totalOrderedQuantity = 0;
    let totalOrderAmount = 0;
    let netAmount = 0;
    let articles = [];

    let customerData;
    const customerSelectDom = document.getElementById('customer_id');
    const generateOrderBtn = document.getElementById('generateOrderBtn');

    let totalQuantityDOM;
    let totalAmountDOM;

    function isArticleAlreadySelected(articleId) {
        return selectedArticles.some(a => a.id == articleId);
    }

    window.trackCustomerState = function trackCustomerState(elem) {
        if (elem.value != '') {
            customerData = order.customer;
            selectedArticles = order.articles.map(a => ({ ...a, ...(a.article || {}) }));
            totalOrderedQuantity =
                selectedArticles.reduce((sum, article) => sum + (article.ordered_pcs || 0), 0) || 0;
            totalOrderAmount =
                selectedArticles.reduce((sum, article) => sum + (article.ordered_pcs * article.article.sales_rate || 0), 0) || 0;
            netAmount = order.netAmount;
            renderList();
            generateOrder();
            renderFinals();
        }
    };

    let cardData = [];

    window.basicSearch = function basicSearch(searchValue) {
        const modalData = {
            id: 'modalForm',
            cards: { data: cardData.filter(item => item.name.toLowerCase().includes(searchValue.toLowerCase())) },
        };
        renderCardsInModal(modalData);
    };

    if (generateOrderBtn) {
        generateOrderBtn.disabled = true;
        generateOrderBtn.addEventListener('click', () => {
            generateArticlesModal();
        });
    }

    window.generateArticlesModal = function generateArticlesModal() {
        const data = articles || [];

        if (data.length > 0 && cardData.length == 0) {
            cardData.push(
                ...data.map(item => {
                    return {
                        id: item.id,
                        name: item.article_no,
                        image:
                            item.image == 'no_image_icon.png'
                                ? '/images/no_image_icon.png'
                                : `/storage/uploads/images/${item.image}`,
                        details: {
                            Category: item.category,
                            Season: item.season,
                            Size: item.size,
                        },
                        data: item,
                        onclick: 'generateQuantityModal(this)',
                    };
                })
            );
        }

        const modalData = {
            id: 'modalForm',
            class: 'h-[80%] w-full',
            cards: { name: 'Articles', count: 3, data: cardData },
            basicSearch: true,
            onBasicSearch: 'basicSearch(this.value)',
            info: `Selected: ${selectedArticles.length}/${maxLimitOfArticles}`,
            flex_col: true,
            calcBottom: [
                { label: 'Total Quantity - Pcs', name: 'totalOrderQty', value: '0', disabled: true },
                { label: 'Total Amount - Rs.', name: 'totalOrderAmount', value: '0.0', disabled: true },
            ],
        };

        createModal(modalData);

        totalQuantityDOM = document.querySelector('#modalForm #totalOrderQty');
        totalAmountDOM = document.querySelector('#modalForm #totalOrderAmount');

        document.querySelectorAll('.card .quantity-label').forEach(previousQuantityLabel => {
            previousQuantityLabel.remove();
        });

        if (selectedArticles.length > 0) {
            selectedArticles.forEach(selectedArticle => {
                const card = document.getElementById(selectedArticle.id);
                const quantityLabelDom = card?.querySelector('.quantity-label');
                if (card && !quantityLabelDom) {
                    card.innerHTML += `
                            <div
                                class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${selectedArticle.ordered_pcs} Pcs
                            </div>
                        `;
                } else if (quantityLabelDom) {
                    quantityLabelDom.textContent = `${selectedArticle.ordered_pcs} Pcs`;
                }
            });
        }

        calculateTotalOrderedQuantity();
        calculateTotalOrderAmount();
        calculateNetAmount();
        renderTotals();
        generateDescription();
        renderList();
        generateOrder();
        renderFinals();
    };

    window.generateQuantityModal = function generateQuantityModal(elem) {
        const data = JSON.parse(elem.dataset.json).data;
        const alreadySelected = isArticleAlreadySelected(data.id);
        const selectedArticle = selectedArticles.find(article => article.id == data.id);
        const maxOrderQuantity = Number(data.orderable_quantity || 0);

        if (limitOfArticles > 0 || alreadySelected) {
            const modalData = {
                id: 'QuantityModalForm',
                name: 'Enter Quantity',
                class: 'h-auto',
                fields: [
                    {
                        category: 'input',
                        value: `${data.article_no} | ${data.season} | ${data.size} | ${data.category} | ${data.fabric_type} | ${data.quantity} | ${formatMoney(data.sales_rate)} - Rs.`,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Orderable Quantity',
                        value: `${formatNumbersDigitLess(maxOrderQuantity)} Pcs | ${formatNumbersWithDigits(data.orderable_quantity_packets)} Pkts`,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Invoiceable Quantity (Current Stock)',
                        value: `${formatNumbersDigitLess(data.current_stock)} Pcs | ${formatNumbersWithDigits(data.current_stock_packets)} Pkts`,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Unit',
                        value: `${formatNumbersDigitLess(data.pcs_per_packet)} Pcs per Packet`,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        name: 'quantity',
                        id: 'quantity',
                        type: 'number',
                        label: 'Quantity - Pcs.',
                        placeholder: 'Enter quantity in pcs.',
                        max: maxOrderQuantity,
                        required: true,
                        oninput: 'checkMax(this)',
                    },
                ],
                fieldsGridCount: '1',
                bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})` }],
            };

            createModal(modalData);

            const quantityLabel = document.getElementById(data.id)?.querySelector('.quantity-label');

            if (quantityLabel) {
                document.getElementById('quantity').value = parseInt(quantityLabel.textContent.replace(/\D/g, ''));
            }

            document.getElementById('quantity').focus();
            document.getElementById('quantity').addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    document.getElementById('setQuantityBtn-in-modal').click();
                }
            });
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__ordersEdit?.maxArticlesAlertHtml || '';
            messageBoxAnimation();
        }
    };

    window.setQuantity = function setQuantity(cardId) {
        const targetCard = document.getElementById(cardId);
        if (!targetCard) return;
        const cardData = JSON.parse(targetCard.dataset.json).data;
        const alreadySelected = isArticleAlreadySelected(cardData.id);

        if (limitOfArticles > 0 || alreadySelected) {
            closeModal('QuantityModalForm');

            const alreadySelectedArticle = selectedArticles.filter(c => c.id == cardData.id);
            const quantityInputDOM = document.getElementById('quantity');
            const quantity = quantityInputDOM.value;
            const quantityLabel = targetCard.querySelector('.quantity-label');

            if (quantity > 0) {
                if (quantityLabel) {
                    quantityLabel.textContent = `${quantity} Pcs`;
                } else {
                    targetCard.innerHTML += `
                            <div class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${quantity} Pcs
                            </div>
                        `;
                }

                cardData.ordered_pcs = parseInt(quantity);

                if (alreadySelectedArticle.length > 0) {
                    alreadySelectedArticle[0].ordered_pcs = parseInt(quantity);
                } else {
                    selectedArticles.push(cardData);
                }
            } else if (quantityLabel) {
                quantityLabel.remove();
                const index = selectedArticles.findIndex(c => c.id === cardData.id);
                deselectArticleAtIndex(index);
            }

            generateDescription();
            calculateTotalOrderedQuantity();
            calculateTotalOrderAmount();
            calculateNetAmount();
            renderTotals();
            renderList();
            renderFinals();
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__ordersEdit?.maxArticlesAlertHtml || '';
            messageBoxAnimation();
        }
    };

    function updateInfo() {
        const infoDom = document.querySelector('.modalFormInfo span');
        if (!infoDom) return;

        infoDom.textContent = `Selected ${selectedArticles.length}/${maxLimitOfArticles}`;
    }

    function deselectArticleAtIndex(index) {
        if (index !== -1) {
            selectedArticles.splice(index, 1);
        }
    }

    window.deselectThisArticle = function deselectThisArticle(index) {
        deselectArticleAtIndex(index);

        renderList();
        generateOrder();

        calculateTotalOrderedQuantity();
        calculateTotalOrderAmount();
        calculateNetAmount();

        renderFinals();
        renderTotals();
    };

    const finalOrderedQuantity = document.getElementById('finalOrderedQuantity');
    const finalOrderAmount = document.getElementById('finalOrderAmount');
    const discountDOM = document.getElementById('discount');
    const finalNetAmount = document.getElementById('finalNetAmount');

    function calculateTotalOrderedQuantity() {
        totalOrderedQuantity = 0;

        selectedArticles.forEach(selectedArticle => {
            totalOrderedQuantity += selectedArticle.ordered_pcs;
        });

        totalOrderedQuantity = new Intl.NumberFormat('en-US').format(totalOrderedQuantity);
    }

    function calculateTotalOrderAmount() {
        totalOrderAmount = 0;

        selectedArticles.forEach(selectedArticle => {
            totalOrderAmount += selectedArticle.ordered_pcs * selectedArticle.sales_rate;
        });
    }

    function generateDescription() {
        selectedArticles.forEach(selectedArticle => {
            selectedArticle.description = `${selectedArticle.size} | ${selectedArticle.category.replaceAll('_', ' ')} | ${selectedArticle.season}`;
        });
    }

    function calculateNetAmount() {
        const totalAmount = totalOrderAmount;
        const discount = document.getElementById('discount').value;
        const discountAmount = totalAmount - totalAmount * (discount / 100);
        netAmount = discountAmount;
        renderFinals();
    }

    if (discountDOM) {
        discountDOM.addEventListener('input', calculateNetAmount);
        discountDOM.addEventListener('focus', e => {
            e.target.select();
        });
    }

    function renderTotals() {
        if (!totalQuantityDOM || !totalAmountDOM) return;
        totalQuantityDOM.value = totalOrderedQuantity;
        totalAmountDOM.value = formatNumbersWithDigits(totalOrderAmount, 1, 1);
    }

    const orderListDOM = document.getElementById('order-list');

    function renderList() {
        if (!orderListDOM) return;
        if (selectedArticles.length > 0) {
            let clutter = '';
            selectedArticles.forEach((selectedArticle, index) => {
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.ordered_pcs} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}</div>
                            <div class="w-1/5">${formatNumbersWithDigits(selectedArticle.sales_rate * selectedArticle.ordered_pcs, 1, 1)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deselectThisArticle(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
            });

            orderListDOM.innerHTML = clutter;
        } else {
            orderListDOM.innerHTML =
                '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Articles Yet</div>';
        }
        updateInputOrderedArticles();
        limitOfArticles = maxLimitOfArticles - selectedArticles.length;
        updateInfo();
    }

    function renderFinals() {
        if (!finalOrderedQuantity || !finalOrderAmount || !finalNetAmount) return;
        finalOrderedQuantity.textContent = formatNumbersWithDigits(totalOrderedQuantity, 1, 1);
        finalOrderAmount.textContent = formatNumbersWithDigits(totalOrderAmount, 1, 1);
        finalNetAmount.value = formatNumbersWithDigits(netAmount, 1, 1);
    }

    function updateInputOrderedArticles() {
        const inputOrderedArticles = document.getElementById('articles');
        const finalArticlesArray = selectedArticles.map(article => {
            return {
                id: article.id,
                description: article.description,
                ordered_pcs: article.ordered_pcs,
            };
        });
        if (inputOrderedArticles) {
            inputOrderedArticles.value = JSON.stringify(finalArticlesArray);
        }
    }

    let companyData;
    let orderDate;
    const previewDom = document.getElementById('preview');

    function generateOrder() {
        orderDate = order?.date;

        if (!previewDom) return;
        if (selectedArticles.length > 0) {
            previewDom.innerHTML = `
                    <div id="order" class="order flex flex-col h-full">
                        <div id="order-banner" class="order-banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="order-logo">
                                    <img src="${window.__ordersEdit.companyLogoBase}/${companyData.logo}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                    <div class='mt-1'>${companyData.phone_number}</div>
                                </div>
                            </div>
                            <h1 class="text-2xl font-medium text-[var(--primary-color)]">Sales Order</h1>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="order-header" class="order-header w-full flex justify-between px-5">
                            <div class="left w-50 space-y-1">
                                <div class="order-customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${customerData.customer_name}</div>
                                <div class="order-person text-md text-lg leading-none">${customerData.urdu_title}</div>
                                <div class="order-address text-md leading-none">${customerData.address}, ${customerData.city.title}</div>
                                <div class="order-phone text-md leading-none">${customerData.phone_number}</div>
                            </div>
                            <div class="right w-50 my-auto text-right text-sm text-gray-600 space-y-1.5">
                                <div class="order-date leading-none">Date: ${orderDate}</div>
                                <div class="order-number leading-none capitalize font-medium">Order No.: ${order.order_no}</div>
                                <input type="hidden" name="order_no" value="${order.order_no}" />
                                <div class="order-copy leading-none">Order Copy: Customer</div>
                                <div class="order-copy leading-none">Document: Sales Order</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="order-body" class="order-body w-[95%] grow mx-auto">
                            <div class="order-table w-full">
                                <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            <div class="th text-sm font-medium w-[7%]">S.No</div>
                                            <div class="th text-sm font-medium w-[13%]">Article</div>
                                            <div class="th text-sm font-medium grow">Description</div>
                                            <div class="th text-sm font-medium w-[10%]">Pcs.</div>
                                            <div class="th text-sm font-medium w-[10%]">Packets</div>
                                            <div class="th text-sm font-medium w-[10%]">Rate</div>
                                            <div class="th text-sm font-medium w-[10%]">Amount</div>
                                            <div class="th text-sm font-medium text-center w-[8%]">Dispatch</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${selectedArticles
                                            .map((article, index) => {
                                                const hrClass = index === 0 ? 'mb-2.5' : 'my-2.5';
                                                return `
                                                    <div>
                                                        <hr class="w-full ${hrClass} border-gray-600">
                                                        <div class="tr flex justify-between w-full px-4">
                                                            <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                                            <div class="td text-sm font-semibold w-[13%]">${article.article_no}</div>
                                                            <div class="td text-sm font-semibold grow">${article.description}</div>
                                                            <div class="td text-sm font-semibold w-[10%]">${article.ordered_pcs}</div>
                                                            <div class="td text-sm font-semibold w-[10%]">${article?.pcs_per_packet ? Math.floor(article.ordered_pcs / article.pcs_per_packet) : 0}</div>
                                                            <div class="td text-sm font-semibold w-[10%]">
                                                                ${formatMoney(article.sales_rate)}
                                                            </div>
                                                            <div class="td text-sm font-semibold w-[10%]">
                                                                ${formatMoney(parseFormattedNumber(article.sales_rate) * article.ordered_pcs)}
                                                            </div>
                                                            <div class="td text-sm font-semibold text-center w-[8%]"></div>
                                                        </div>
                                                    </div>
                                                    `;
                                            })
                                            .join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="flex flex-col space-y-2">
                            <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Total Quantity</div>
                                    <div class="w-1/4 text-right grow">${totalQuantityDOM?.value || 0}</div>
                                </div>
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Total Amount</div>
                                    <div class="w-1/4 text-right grow">${totalAmountDOM?.value || 0}</div>
                                </div>
                            </div>
                            <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Discount - %</div>
                                    <div class="w-1/4 text-right grow">${discountDOM?.value || 0}</div>
                                </div>
                                <div
                                    class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Net Amount</div>
                                    <div class="w-1/4 text-right grow">${finalNetAmount?.value || 0}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-600">
                            <P class="leading-none">Powered by SparkPair</P>
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                `;
        } else {
            previewDom.innerHTML =
                '<h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>';
        }
    }

    window.getDataByDate = function getDataByDate() {
        $.ajax({
            url: window.__ordersEdit?.ordersCreateUrl || '',
            method: 'GET',
            data: {
                date: order?.date,
                exclude_order_id: order?.id,
            },
            success: function (response) {
                articles = response.articles || [];
                if (generateOrderBtn) generateOrderBtn.disabled = false;
            },
            error: function () {
                alert('Error fetching data');
            },
        });
    };

    function populateOptions(customers_options) {
        const customerSelectDomLocal = document.getElementById('customer_id');
        if (!customerSelectDomLocal) return;
        customerSelectDomLocal.disabled = false;
        customerSelectDomLocal.value = '-- Select Customer --';
        const dropdownUl = customerSelectDomLocal.parentElement.parentElement.parentElement.querySelector('ul');
        dropdownUl.innerHTML = '';
        let optionsHTML =
            '<li data-for="customer_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden selected">-- Select Customer --</li>';
        customers_options.forEach(customer => {
            optionsHTML += `
                    <li data-for="customer_id" data-value="${customer.data_option.id}" onmousedown="selectThisOption(this)" data-option='${jsonAttr(customer.data_option)}' class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${customer.text}</li>
                `;
        });
        dropdownUl.innerHTML = optionsHTML;
    }

    window.validateForNextStep = function validateForNextStep() {
        generateOrder();
        return true;
    };

    window.reRenderSelectedState = function reRenderSelectedState() {
        const selectedIds = selectedArticles.map(card => card.id);

        document.querySelectorAll('.card_container .card').forEach(card => {
            const cardData = JSON.parse(card.getAttribute('data-json'));

            if (selectedIds.includes(cardData.id)) {
                const selectedCard = selectedArticles.find(item => item.id === cardData.id);

                card.innerHTML += `
                        <div class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                            ${selectedCard.ordered_pcs} Pcs
                        </div>
                    `;
            }
        });
    };

    window.reRenderSelectedStateTotal = function reRenderSelectedStateTotal() {
        totalQuantityDOM = document.getElementById('totalOrderedQty');
        totalAmountDOM = document.getElementById('totalOrderAmount');
        renderTotals();
    };

    function initOrdersEdit(data) {
        order = data?.order || null;
        companyData = data?.companyData || null;
        customerData = order?.customer || null;

        if (order?.articles?.length) {
            selectedArticles = order.articles.map(item => ({
                ...item,
                ...(item.article || {}),
                id: item.article_id ?? item.id,
                ordered_pcs: Number(item.ordered_pcs || 0),
                dispatched_pcs: Number(item.dispatched_pcs || 0),
                sales_rate: parseFormattedNumber(item.article?.sales_rate ?? item.sales_rate),
                pcs_per_packet: Number(item.article?.pcs_per_packet ?? item.pcs_per_packet ?? 0),
                article_no: item.article?.article_no ?? item.article_no ?? '-',
            }));

            generateDescription();
            calculateTotalOrderedQuantity();
            calculateTotalOrderAmount();
            netAmount = Number(order.netAmount || 0);
            renderList();
            renderFinals();
            generateOrder();
        }

        getDataByDate();
        if (!selectedArticles.length) {
            renderList();
        }
    }

    window.initOrdersEdit = initOrdersEdit;

    function boot() {
        if (window.__ordersEdit) {
            initOrdersEdit(window.__ordersEdit);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
