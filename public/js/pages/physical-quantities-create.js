(() => {
    function initPhysicalQuantitiesCreate() {
        const config = window.__physicalQuantitiesCreate || {};
        const articles = config.articles || [];

        const articleSelectInputDOM = document.getElementById("article");
        const articleIdInputDOM = document.getElementById("article_id");
        const articleImageShowDOM = document.getElementById("img-article");

        const pcsPerPacketDom = document.getElementById("pcs_per_packet");
        const processedByDom = document.getElementById("processed_by");
        const packetsDom = document.getElementById("packets");
        const categoryDom = document.getElementById("category");

        const totalPhysicalQuantityDom = document.getElementById("currentPhysicalQuantity");
        const finalOrderedQuantityDom = document.getElementById("finalOrderedQuantity");
        const remainingqQuantityDom = document.getElementById("remainingquantity");
        const finalOrderAmountDom = document.getElementById("finalOrderAmount");

        let totalQuantity = 0;
        let totalAmount = 0;
        let cardData = [];
        let selectedArticle = null;

        if (!articleSelectInputDOM || !articleIdInputDOM) return;

        window.basicSearch = function basicSearch(searchValue) {
            let modalData = {
                id: "modalForm",
                cards: {
                    data: cardData.filter((item) =>
                        item.name.toLowerCase().includes(searchValue.toLowerCase())
                    ),
                },
            };
            renderCardsInModal(modalData);
        };

        articleSelectInputDOM.addEventListener("click", () => {
            generateArticlesModal();
        });

        function generateArticlesModal() {
            cardData = [];
            let data = Object.values(articles);

            if (data.length > 0) {
                cardData.push(
                    ...data.map((item) => {
                        return {
                            id: item.id,
                            name: item.article_no,
                            image:
                                item.image == "no_image_icon.png"
                                    ? "/images/no_image_icon.png"
                                    : `/storage/uploads/images/${item.image}`,
                            details: {
                                Category: item.category,
                                Season: item.season,
                                Size: item.size,
                            },
                            data: item,
                            onclick: "selectThisArticle(this)",
                        };
                    })
                );
            }

            let modalData = {
                id: "modalForm",
                class: "h-[80%] w-full",
                basicSearch: true,
                onBasicSearch: "basicSearch(this.value)",
                cards: { name: "Articles", count: 3, data: cardData },
            };

            createModal(modalData);
        }

        window.selectThisArticle = function selectThisArticle(articleElem) {
            selectedArticle = JSON.parse(articleElem.getAttribute("data-json")).data;

            articleIdInputDOM.value = selectedArticle.id;
            let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                selectedArticle.quantity
            )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
            articleSelectInputDOM.value = value;

            articleImageShowDOM.classList.remove("opacity-0");
            articleImageShowDOM.src = articleElem.querySelector("img").src;

            closeModal("modalForm");
            trackFieldsDisability();
            calculateTotal();

            totalPhysicalQuantityDom.innerText = selectedArticle.physical_quantity;

            function formatArticleDate(inputDate) {
                let [day, month, yearWithDay] = inputDate.replace(",", "").split("-");
                let [year] = yearWithDay.split(" ");

                const monthMap = {
                    Jan: "01",
                    Feb: "02",
                    Mar: "03",
                    Apr: "04",
                    May: "05",
                    Jun: "06",
                    Jul: "07",
                    Aug: "08",
                    Sep: "09",
                    Oct: "10",
                    Nov: "11",
                    Dec: "12",
                };

                return `${year}-${monthMap[month]}-${day.padStart(2, "0")}`;
            }

            document.getElementById("date").min = formatArticleDate(selectedArticle.date);

            const hasPcs = Number(selectedArticle.pcs_per_packet || 0) > 0;
            const hasProcessedBy = !!(selectedArticle.processed_by && String(selectedArticle.processed_by).trim());

            if (hasPcs) {
                pcsPerPacketDom.readOnly = true;
                pcsPerPacketDom.classList.remove("bg-[var(--h-bg-color)]");
                pcsPerPacketDom.classList.add("bg-transparent");
                pcsPerPacketDom.classList.add("cursor-not-allowed");
                pcsPerPacketDom.value = selectedArticle.pcs_per_packet;
            } else {
                pcsPerPacketDom.readOnly = false;
                pcsPerPacketDom.classList.add("bg-[var(--h-bg-color)]");
                pcsPerPacketDom.classList.remove("bg-transparent");
                pcsPerPacketDom.classList.remove("cursor-not-allowed");
                pcsPerPacketDom.value = "";
            }

            if (hasProcessedBy) {
                processedByDom.readOnly = true;
                processedByDom.classList.remove("bg-[var(--h-bg-color)]");
                processedByDom.classList.add("bg-transparent");
                processedByDom.classList.add("cursor-not-allowed");
                processedByDom.value = selectedArticle.processed_by;
            } else {
                processedByDom.readOnly = false;
                processedByDom.classList.add("bg-[var(--h-bg-color)]");
                processedByDom.classList.remove("bg-transparent");
                processedByDom.classList.remove("cursor-not-allowed");
                processedByDom.value = "";
            }

            remainingqQuantityDom.innerText = new Intl.NumberFormat("en-US").format(
                pcsPerPacketDom.value > 0 && parseInt(totalPhysicalQuantityDom.textContent) > 0
                    ? selectedArticle.quantity - parseInt(totalPhysicalQuantityDom.textContent)
                    : selectedArticle.quantity
            );
        };

        document.getElementById("pcs_per_packet").addEventListener("input", () => {
            calculateTotal();
            trackArticleQuantity();
        });

        document.getElementById("packets").addEventListener("input", () => {
            calculateTotal();
            trackArticleQuantity();
        });

        function trackFieldsDisability() {
            if (!selectedArticle) {
                pcsPerPacketDom.disabled = true;
                packetsDom.disabled = true;
                categoryDom.disabled = true;
            } else {
                pcsPerPacketDom.disabled = false;
                packetsDom.disabled = false;
                categoryDom.disabled = false;
            }
        }
        trackFieldsDisability();

        function calculateTotal() {
            if (selectedArticle) {
                let pcsPerPacket = pcsPerPacketDom.value;
                let packets = packetsDom.value;

                totalQuantity = pcsPerPacket * packets;
                totalAmount = totalQuantity * parseInt(selectedArticle.sales_rate);

                finalOrderedQuantityDom.textContent = new Intl.NumberFormat("en-US").format(
                    totalQuantity
                );

                finalOrderAmountDom.innerText = new Intl.NumberFormat("en-US", {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                }).format(totalAmount);
            }
        }

        const totalQtyDom = document.querySelector(".total-qty");
        const totalQtyErrorDom = document.getElementById("total-qty-error");

        function trackArticleQuantity() {
            if (
                selectedArticle &&
                totalQuantity + parseInt(totalPhysicalQuantityDom.textContent) > selectedArticle.quantity
            ) {
                totalQtyDom.classList.add("border-[var(--border-error)]");
                totalQtyErrorDom.innerText = `Quantity exceeds the available stock (${selectedArticle.quantity} pcs)`;
                totalQtyErrorDom.classList.remove("hidden");
            } else {
                totalQtyDom.classList.remove("border-[var(--border-error)]");
                totalQtyDom.classList.add("border-gray-600");
                totalQtyErrorDom.classList.add("hidden");
                totalQtyErrorDom.innerText = "";
            }
        }

        window.validateForNextStep = function validateForNextStep() {
            return true;
        };
    }

    window.initPhysicalQuantitiesCreate = initPhysicalQuantitiesCreate;

    function boot() {
        if (window.__physicalQuantitiesCreate) {
            initPhysicalQuantitiesCreate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
