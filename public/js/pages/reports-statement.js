(() => {
    function initReportsStatement() {
        const config = window.__reportsStatement || {};
        let btnTypeGlobal = config.statementType || "general";

        window.setVoucherType = function setVoucherType(btn, btnType) {
            doHide = true;
            if (btnTypeGlobal == btnType) {
                return;
            }

            $.ajax({
                url: config.setTypeUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    statement_type: btnType,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    alert("Failed to update statement type.");
                    $(btn).prop("disabled", false);
                },
            });

            moveHighlight(btn, btnType);
        };

        function moveHighlight(btn, btnType) {
            const highlight = document.getElementById("highlight");
            const rect = btn.getBoundingClientRect();
            const parentRect = btn.parentElement.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;
            btnTypeGlobal = btnType;
        }

        const type = config.statementType || "general";
        const activeBtn =
            type === "general"
                ? document.querySelector("#generalBtn")
                : type === "summarized"
                    ? document.querySelector("#summarizedBtn")
                    : document.querySelector("#detailedBtn");
        if (activeBtn) {
            moveHighlight(activeBtn, type);
        }

        const today = new Date();
        const nameSelect = document.getElementById("nameSelect");
        const nameSelectDropDown = nameSelect.parentElement.parentElement.parentElement.querySelector(
            "ul.optionsDropdown"
        );
        const rangeSelect = document.getElementById("range");
        const rangeSelectDropDown = rangeSelect.parentElement.parentElement.parentElement.querySelector(
            "ul.optionsDropdown"
        );

        const dateFrom = document.getElementById("date_from");
        const dateTo = document.getElementById("date_to");
        let regDate;

        window.fetchNames = function fetchNames(category) {
            if (!category) return;

            $.ajax({
                url: config.getNamesUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    category: category,
                },
                success: function (response) {
                    if (response.length > 0) {
                        let clutter = `
                            <li data-for="nameSelect" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                -- Select Name --
                            </li>
                        `;

                        response.forEach(function (item) {
                            let displayText = "";
                            if (category === "customer") {
                                displayText = item.customer_name + " | " + item.city?.short_title;
                            } else if (category === "supplier") {
                                displayText = item.supplier_name || "";
                            } else if (category === "bank_account") {
                                displayText = item.account_title || "";
                            }

                            clutter += `
                                <li data-for="nameSelect" data-value="${item.id}" data-reg-date="${item.date}"
                                    onmousedown="selectThisOption(this)"
                                    class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                                    ${displayText}
                                </li>
                            `;
                        });

                        nameSelectDropDown.innerHTML = clutter;
                        nameSelect.disabled = false;
                    } else {
                        const option = document.createElement("option");
                        option.value = "";
                        option.textContent = `
                            <li data-for="nameSelect" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                -- No Names Found --
                            </li>
                        `;
                        nameSelectDropDown.appendChild(option);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching names:", error);
                },
            });
        };

        window.nameChanged = function nameChanged(nameSelectDbInput) {
            if (nameSelectDbInput.value) {
                const selectedName = nameSelectDbInput.nextElementSibling.querySelector(
                    `li[data-value="${nameSelectDbInput.value}"]`
                );
                if (!selectedName) return;

                const rawRegDate = new Date(selectedName.dataset.regDate);
                const d = new Date(rawRegDate);
                regDate = d.toISOString().split("T")[0];
                dateFrom.min = regDate;
                const todayLocal = new Date();

                function monthDiff(d1, d2) {
                    let months = (d2.getFullYear() - d1.getFullYear()) * 12;
                    months -= d1.getMonth();
                    months += d2.getMonth();
                    return months <= 0 ? 0 : months;
                }

                const monthsSinceReg = monthDiff(rawRegDate, todayLocal);
                const ranges = [];

                if (monthsSinceReg >= 0) ranges.push({ value: "current_month", label: "Current Month" });
                if (monthsSinceReg >= 1) ranges.push({ value: "last_month", label: "Last Month" });
                if (monthsSinceReg >= 3)
                    ranges.push({ value: "last_three_months", label: "Last Three Months" });
                if (monthsSinceReg >= 6) ranges.push({ value: "last_six_months", label: "Last Six Months" });
                ranges.push({ value: "custom", label: "Custom" });

                const clutter = ranges
                    .map(
                        r => `
                    <li data-for="range" data-value="${r.value}"
                        onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                        ${r.label}
                    </li>`
                    )
                    .join("");

                rangeSelectDropDown.innerHTML = clutter;
                rangeSelect.disabled = false;
            } else {
                rangeSelect.value = "";
                rangeSelect.disabled = true;
            }
        };

        window.updateDateConstraints = function updateDateConstraints() {
            if (dateFrom.value) {
                dateTo.min = dateFrom.value;
            }
            if (dateTo.value) {
                dateFrom.max = dateTo.value;
            }
        };

        const formatDateLocal = d => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, "0");
            const day = String(d.getDate()).padStart(2, "0");
            return `${y}-${m}-${day}`;
        };

        function isLastDayOfMonth(date) {
            return date.getDate() === new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        }

        window.applyRange = function applyRange(rangeValue) {
            let from = null,
                to = null;

            switch (rangeValue) {
                case "custom":
                    dateFrom.value = regDate;
                    dateTo.value = new Date().toISOString().split("T")[0];
                    dateFrom.disabled = false;
                    dateTo.disabled = false;
                    return;

                case "current_month":
                    from = new Date(today.getFullYear(), today.getMonth(), 1);
                    to = today;
                    break;

                case "last_month":
                    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    to = today;
                    break;

                case "last_three_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 2, 1);
                    to = today;
                    break;

                case "last_six_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    to = today;
                    break;

                default:
                    dateFrom.value = "";
                    dateTo.value = "";
                    dateFrom.disabled = true;
                    dateTo.disabled = true;
                    return;
            }

            dateFrom.value = formatDateLocal(from);
            dateTo.value = formatDateLocal(to);
            dateFrom.disabled = true;
            dateTo.disabled = true;
        };

        window.getStatement = function getStatement() {
            const category = document
                .querySelector('ul[data-for="category"] li.selected')
                .textContent.trim()
                .toLowerCase();
            const id = document.querySelector('input[data-for="nameSelect"]').value;
            const dateFromVal = document.getElementById("date_from").value;
            const dateToVal = document.getElementById("date_to").value;

            $.ajax({
                url: config.statementUrl,
                type: "GET",
                data: {
                    _token: config.csrfToken,
                    withData: false,
                    type: config.statementType,
                    category: category,
                    id: id,
                    date_from: dateFromVal !== "" ? dateFromVal : regDate,
                    date_to: dateToVal !== "" ? dateToVal : today.toISOString().split("T")[0],
                },
                success: function (response) {
                    renderStatement(response);
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching statement:", error);
                },
            });
        };

        function renderStatement(response) {
            const $responseHtml = $(response);
            const $previewInResponse = $responseHtml.find(".step2");

            if ($previewInResponse.length) {
                $(".step2").html($previewInResponse.html());
            } else {
                console.warn(".step2 not found in response HTML.");
            }
        }

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-container");
            const clone = preview.cloneNode(true);

            clone.querySelectorAll(":scope > hr").forEach(hr => hr.remove());

            const oldIframe = document.getElementById("printIframe");
            if (oldIframe) {
                oldIframe.remove();
            }

            const printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";

            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                    <head>
                        <title>Print Statement</title>
                        ${headContent}
                        <style>
                            @page {
                                size: A4;
                                margin: 0;
                            }

                            body {
                                margin: 0;
                                padding: 0;
                                background: #fff;
                            }
                        </style>
                    </head>
                    <body>
                        ${clone.innerHTML}
                    </body>
                </html>
            `);

            printDocument.close();

            printIframe.onload = () => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            };
        };

        window.validateForNextStep = function validateForNextStep() {
            getStatement();
            return true;
        };
    }

    window.initReportsStatement = initReportsStatement;

    function boot() {
        if (window.__reportsStatement) {
            initReportsStatement();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
