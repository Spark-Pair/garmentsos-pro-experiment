(function () {
    let invoices = [];
    let companyData = null;

    const invoiceContainer = document.getElementById('invoice-container');

    function chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    function renderInvoices() {
        if (!invoiceContainer) return;
        invoices.forEach(invoice => {
            const articlePages = chunkArray(invoice.shipment.articles, 21);
            const previewDom = document.createElement('div');
            previewDom.classList = 'invoice';

            const customerData = invoice.customer;
            let totalPcs = 0;
            let totalPackets = 0;
            let totalAmount = 0;
            const cottonCount = invoice.cotton_count;

            if (invoice.shipment.articles.length > 0) {
                previewDom.innerHTML = `
                        <div class="preview-container w-[210mm] h-[302mm] mx-auto overflow-hidden relative">
                            ${articlePages
                                .map((page, pageIndex) => {
                                    return `
                                    <div id="preview" class="preview flex flex-col h-full">
                                        <div id="invoice" class="invoice flex flex-col h-full">
                                            <div id="invoice-banner" class="invoice-banner w-full flex justify-between items-center pl-5 pr-8">
                                                <div class="left">
                                                    <div class="invoice-logo">
                                                        <img src="${window.__invoicesPrint.companyLogoBase}/${companyData.logo}" alt="garmentsos-pro"
                                                            class="w-[12rem]" />
                                                        <div class='mt-1'>${companyData.phone_number}</div>
                                                    </div>
                                                </div>
                                                <div class="left">
                                                    <div class="invoice-logo">
                                                        <h1 class="text-2xl font-medium text-[var(--h-primary-color)] pr-2">Sales Invoice</h1>
                                                        <div class="mt-1 text-right pr-2">Cotton: ${cottonCount}</div>
                                                        <div class="text-right leading-none">Shipment No.: ${invoice.shipment_no}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr class="w-full my-3 border-black">
                                            <div id="invoice-header" class="invoice-header w-full flex justify-between px-5">
                                                <div class="left w-50 space-y-1">
                                                    <div class="invoice-customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${customerData.customer_name}</div>
                                                    <div class="invoice-person text-md text-lg leading-none">${customerData.urdu_title}</div>
                                                    <div class="invoice-address text-md leading-none">${customerData.address}, ${customerData.city.title}</div>
                                                    <div class="invoice-phone text-md leading-none">${customerData.phone_number}</div>
                                                </div>
                                                <div class="right my-auto pr-3 text-sm text-black space-y-1.5">
                                                    <div class="invoice-date leading-none">Date: ${formatDate(invoice.date)}</div>
                                                    <div class="invoice-number leading-none capitalize font-medium">Invoice No.: ${invoice.invoice_no}</div>
                                                    <div class="invoice-copy leading-none">Invoice Copy: Customer</div>
                                                    <div class="invoice-copy leading-none">Document: Sales Invoice</div>
                                                </div>
                                            </div>
                                            <hr class="w-full my-3 border-black">
                                            <div id="invoice-body" class="invoice-body w-[95%] grow mx-auto">
                                                <div class="invoice-table w-full">
                                                    <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                                        <div class="thead w-full">
                                                            <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                                <div class="th text-sm font-medium w-[7%]">S.No</div>
                                                                <div class="th text-sm font-medium w-[10%]">Article</div>
                                                                <div class="th text-sm font-medium w-[10%]">Packets</div>
                                                                <div class="th text-sm font-medium w-[10%]">Pcs.</div>
                                                                <div class="th text-sm font-medium grow">Description</div>
                                                                <div class="th text-sm font-medium w-[10%]">Pcs/Pkt.</div>
                                                                <div class="th text-sm font-medium w-[11%]">Rate/Pc.</div>
                                                                <div class="th text-sm font-medium w-[11%]">Amount</div>
                                                            </div>
                                                        </div>
                                                        <div id="tbody" class="tbody w-full">
                                                            ${page
                                                                .map((fetchedArticle, articleIndex) => {
                                                                    const article = fetchedArticle.article;
                                                                    const hrClass = articleIndex === 0 ? 'mb-2.5' : 'my-2.5';
                                                                    const quantity = fetchedArticle.shipment_pcs * cottonCount;
                                                                    const packets = Math.floor(quantity / article.pcs_per_packet);

                                                                    totalPcs += quantity;
                                                                    totalPackets += packets;
                                                                    totalAmount += parseInt(article.sales_rate) * quantity;

                                                                    const serialNumber = pageIndex * 21 + articleIndex + 1;

                                                                    return `
                                                                    <div>
                                                                        <hr class="w-full ${hrClass} border-black">
                                                                        <div class="tr flex justify-between w-full px-4">
                                                                            <div class="td text-sm font-semibold w-[7%]">${serialNumber}.</div>
                                                                            <div class="td text-sm font-semibold w-[10%]">${article.article_no}</div>
                                                                            <div class="td text-sm font-semibold w-[10%]">${packets}</div>
                                                                            <div class="td text-sm font-semibold w-[10%]">${quantity}</div>
                                                                            <div class="td text-sm font-semibold grow">${article.description}</div>
                                                                            <div class="td text-sm font-semibold w-[10%]">${formatNumbersDigitLess(article.pcs_per_packet)}</div>
                                                                            <div class="td text-sm font-semibold w-[11%]">${formatNumbersWithDigits(article.sales_rate, 2, 2)}</div>
                                                                            <div class="td text-sm font-semibold w-[11%]">${formatNumbersWithDigits(parseInt(article.sales_rate) * quantity, 1, 1)}</div>
                                                                        </div>
                                                                    </div>
                                                                `;
                                                                })
                                                                .join('')}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                })
                                .join('')}
                        </div>
                    `;
            }

            invoiceContainer.appendChild(previewDom);
        });
    }

    function initInvoicesPrint(data) {
        invoices = data?.invoices || [];
        companyData = data?.companyData || null;
        renderInvoices();
    }

    window.initInvoicesPrint = initInvoicesPrint;

    function boot() {
        if (window.__invoicesPrint) {
            initInvoicesPrint(window.__invoicesPrint);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
