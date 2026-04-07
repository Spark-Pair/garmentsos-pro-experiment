(() => {
    function initReportsPendingPayments() {
        const config = window.__reportsPendingPayments || {};
        const pendingUrl = config.pendingUrl || "";
        const csrfToken = config.csrfToken || "";

        window.getPendingPayments = function getPendingPayments() {
            const date = document.getElementById("date").value;

            $.ajax({
                url: pendingUrl,
                type: "GET",
                data: {
                    _token: csrfToken,
                    date: date,
                },
                success: function (response) {
                    renderPendingPayments(response);
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching statement:", error);
                },
            });
        };

        function renderPendingPayments(response) {
            const $responseHtml = $(response);
            const $previewInResponse = $responseHtml.find(".step2");

            if ($previewInResponse.length) {
                $(".step2").html($previewInResponse.html());
            } else {
                console.warn(".step2 not found in response HTML.");
            }
        }

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-page");

            let clone = preview.cloneNode(true);

            clone.querySelectorAll(":scope > hr").forEach((hr) => hr.remove());

            let oldIframe = document.getElementById("printIframe");
            if (oldIframe) {
                oldIframe.remove();
            }

            let printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";

            document.body.appendChild(printIframe);

            let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                <head>
                    <title>Print Pending Payments</title>
                    ${headContent}
                    <style>
                    @page {
                        size: A4;
                        margin: 0.19in;
                    }

                    body {
                        margin: 0;
                        padding: 0;
                        background: #fff;
                    }

                    @media print {
                        .slip {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }

                        .slip + hr {
                            page-break-after: auto;
                        }

                        #preview-page {
                            overflow: visible !important;
                        }
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
            getPendingPayments();
            return true;
        };
    }

    window.initReportsPendingPayments = initReportsPendingPayments;

    function boot() {
        if (window.__reportsPendingPayments) initReportsPendingPayments();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
