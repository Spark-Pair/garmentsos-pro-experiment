(() => {
    function initReportsPhysicalQuantity() {
        const config = window.__reportsPhysicalQuantity || {};
        const reportUrl = config.reportUrl || "";

        function getHiddenSelectValue(id) {
            return document.querySelector(`input[type="hidden"][data-for="${id}"]`)?.value || "";
        }

        function getInputValue(id) {
            return document.getElementById(id)?.value?.trim() || "";
        }

        function setSelectDisabledState(wrapperId, inputId, shouldDisable) {
            const wrapper = document.getElementById(wrapperId);
            const visibleInput = document.getElementById(inputId);
            const hiddenInput = document.querySelector(`input[type="hidden"][data-for="${inputId}"]`);

            if (wrapper) {
                wrapper.classList.toggle("hidden", shouldDisable);
            }

            if (visibleInput) {
                visibleInput.disabled = shouldDisable;
                if (shouldDisable) {
                    visibleInput.value = "";
                }
            }

            if (hiddenInput) {
                hiddenInput.disabled = shouldDisable;
                if (shouldDisable) {
                    hiddenInput.value = "";
                }
            }
        }

        function setInputDisabledState(wrapperId, inputId, shouldDisable) {
            const wrapper = document.getElementById(wrapperId);
            const input = document.getElementById(inputId);

            if (wrapper) {
                wrapper.classList.toggle("hidden", shouldDisable);
            }

            if (input) {
                input.disabled = shouldDisable;
                if (shouldDisable) {
                    input.value = "";
                }
            }
        }

        window.togglePhysicalQuantityMode = function togglePhysicalQuantityMode() {
            const mode = getHiddenSelectValue("mode") || "all_articles";

            setSelectDisabledState("articleFilterWrap", "article_id", mode !== "article_wise");
            setInputDisabledState("proceedByFilterWrap", "proceed_by", mode !== "proceed_by_wise");
        };

        function renderPreview(response) {
            const $responseHtml = $(response);
            const $previewInResponse = $responseHtml.find(".step2");

            if ($previewInResponse.length) {
                $(".step2").html($previewInResponse.html());
            }
        }

        function validateFilters() {
            const mode = getHiddenSelectValue("mode") || "all_articles";
            const articleId = getHiddenSelectValue("article_id");
            const proceedBy = getInputValue("proceed_by");

            if (mode === "article_wise" && !articleId) {
                alert("Please select an article.");
                return false;
            }

            if (mode === "proceed_by_wise" && !proceedBy) {
                alert("Please type a Proceed By value.");
                return false;
            }

            return true;
        }

        function fetchReportPreview() {
            const mode = getHiddenSelectValue("mode") || "all_articles";
            const articleId = getHiddenSelectValue("article_id");
            const proceedBy = getInputValue("proceed_by");

            $.ajax({
                url: reportUrl,
                type: "GET",
                data: {
                    withData: 1,
                    mode: mode,
                    article_id: articleId,
                    proceed_by: proceedBy,
                },
                success: function (response) {
                    renderPreview(response);
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching physical quantity report:", error);
                    alert("Failed to generate the report preview.");
                },
            });
        }

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-container");

            if (!preview) {
                alert("Preview not ready yet.");
                return;
            }

            const clone = preview.cloneNode(true);
            const oldIframe = document.getElementById("printIframe");

            if (oldIframe) {
                oldIframe.remove();
            }

            const printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0";
            printIframe.style.height = "0";
            printIframe.style.border = "0";
            printIframe.style.display = "none";
            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            printDocument.write(`
                <html>
                <head>
                    <title>Print Physical Quantity Report</title>
                    ${document.head.innerHTML}
                    <style>
                        @page {
                            size: A4;
                            margin: 0;
                        }

                        html, body {
                            width: 210mm;
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            overflow: visible !important;
                        }

                        body {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }

                        #preview-container {
                            display: block !important;
                            height: auto !important;
                            min-height: auto !important;
                            overflow: visible !important;
                            position: static !important;
                        }

                        .preview-page {
                            width: 210mm;
                            height: 297mm;
                            min-height: 297mm;
                            margin: 0 auto;
                            background: #fff;
                            page-break-after: always;
                            break-after: page;
                            box-shadow: none !important;
                            overflow: hidden;
                            position: relative !important;
                        }

                        .preview-page:last-child {
                            page-break-after: auto;
                            break-after: auto;
                        }

                        .preview,
                        .preview-document {
                            height: 100% !important;
                            min-height: 100% !important;
                            overflow: hidden !important;
                        }

                        #preview,
                        #preview-document,
                        #preview-body {
                            overflow: visible !important;
                        }

                        hr {
                            box-sizing: border-box;
                        }
                    </style>
                </head>
                <body>
                    ${clone.outerHTML}
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
            if (!validateFilters()) {
                return false;
            }

            fetchReportPreview();
            return true;
        };

        togglePhysicalQuantityMode();
    }

    window.initReportsPhysicalQuantity = initReportsPhysicalQuantity;

    function boot() {
        if (window.__reportsPhysicalQuantity) {
            initReportsPhysicalQuantity();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
