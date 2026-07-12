(() => {
    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function money(value) {
        const number = Number(String(value ?? "").replace(/,/g, ""));
        if (!Number.isFinite(number)) return "-";
        return number.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function listValue(value) {
        if (!value) return "-";
        if (Array.isArray(value)) {
            return value.map((item) => {
                if (typeof item === "string") return item;
                if (item?.tag) return `${item.tag}${item.quantity ? ` (${item.quantity})` : ""}`;
                if (item?.title) return `${item.title}${item.quantity ? ` (${item.quantity} ${item.unit || ""})` : ""}`;
                return JSON.stringify(item);
            }).join(", ");
        }
        return String(value);
    }

    function normalizeTicket(raw) {
        const data = raw?.data || raw || {};
        return {
            ticket: data.ticket || raw?.ticket || "-",
            issueDate: data.issue_date || raw?.issue_date || "-",
            receiveDate: data.receive_date || raw?.receive_date || "",
            article: data.article?.article_no || data.article_no || raw?.article_no || "-",
            work: data.work?.title || raw?.work?.title || raw?.worker_name?.split("|")?.[1]?.trim() || "-",
            worker: data.worker?.employee_name || raw?.worker?.employee_name || raw?.worker_name?.split("|")?.[0]?.trim() || "-",
            quantity: data.quantity || raw?.quantity || "-",
            rate: data.rate || raw?.rate || "",
            amount: data.amount || raw?.amount || "",
            title: data.title || raw?.title || "",
            parts: data.parts || raw?.parts || [],
            materials: data.materials || raw?.materials || [],
            tags: data.tags || raw?.tags || [],
            creator: data.creator || raw?.creator || "",
        };
    }

    function ticketHtml(raw) {
        const ticket = normalizeTicket(raw);
        const company = raw?.branch_branding || raw?.data?.branch_branding || window.__productionTicketPrint?.company || {};
        const companyName = company.name || "GarmentsOS PRO";
        const logo = company.logo_url || company.logo || "";

        return `
            <div class="gos-ticket">
                <div class="ticket-head">
                    <div class="brand">
                        ${logo ? `<img src="${escapeHtml(logo)}" alt="">` : ""}
                        <div>
                            <h1>${escapeHtml(companyName)}</h1>
                            <p>${escapeHtml(company.address || "")}</p>
                            <p>${escapeHtml(company.phone || "")}</p>
                        </div>
                    </div>
                    <div class="meta">
                        <h2>Production Ticket</h2>
                        <p><strong>${escapeHtml(ticket.ticket)}</strong></p>
                        <p>Issue: ${escapeHtml(ticket.issueDate)}</p>
                        ${ticket.receiveDate ? `<p>Receive: ${escapeHtml(ticket.receiveDate)}</p>` : ""}
                    </div>
                </div>
                <div class="ticket-grid">
                    <div>
                        <span>Article</span>
                        <strong>${escapeHtml(ticket.article)}</strong>
                    </div>
                    <div>
                        <span>Work</span>
                        <strong>${escapeHtml(ticket.work)}</strong>
                    </div>
                    <div>
                        <span>Worker</span>
                        <strong>${escapeHtml(ticket.worker)}</strong>
                    </div>
                    <div>
                        <span>Quantity</span>
                        <strong>${escapeHtml(ticket.quantity)}</strong>
                    </div>
                </div>
                <table>
                    <tbody>
                        <tr><th>Title / Remarks</th><td>${escapeHtml(ticket.title || "-")}</td></tr>
                        <tr><th>Parts</th><td>${escapeHtml(listValue(ticket.parts))}</td></tr>
                        <tr><th>Materials</th><td>${escapeHtml(listValue(ticket.materials))}</td></tr>
                        <tr><th>Tags</th><td>${escapeHtml(listValue(ticket.tags))}</td></tr>
                        <tr><th>Rate</th><td>${ticket.rate ? money(ticket.rate) : "-"}</td></tr>
                        <tr><th>Amount</th><td>${ticket.amount ? money(ticket.amount) : "-"}</td></tr>
                    </tbody>
                </table>
                <div class="signatures">
                    <div>Issued By<br><strong>${escapeHtml(ticket.creator || "")}</strong></div>
                    <div>Worker Signature</div>
                    <div>Checked By</div>
                </div>
                <div class="footer">Generated by GarmentsOS PRO</div>
            </div>
        `;
    }

    function documentHtml(raw) {
        return `
            <html>
                <head>
                    <title>Production Ticket</title>
                    <style>
                        @page { size: A5; margin: 8mm; }
                        * { box-sizing: border-box; }
                        body { margin: 0; font-family: Arial, sans-serif; color: #111827; background: #f3f4f6; }
                        .gos-ticket { width: 148mm; min-height: 210mm; margin: 0 auto; background: #fff; padding: 9mm; border: 1px solid #d1d5db; }
                        .ticket-head { display: flex; justify-content: space-between; gap: 10mm; border-bottom: 2px solid #111827; padding-bottom: 5mm; }
                        .brand { display: flex; align-items: flex-start; gap: 3mm; }
                        .brand img { width: 16mm; height: 16mm; object-fit: contain; }
                        h1 { font-size: 16pt; margin: 0 0 2mm; }
                        h2 { font-size: 13pt; margin: 0 0 2mm; text-align: right; }
                        p { margin: 0 0 1mm; font-size: 8.5pt; color: #4b5563; }
                        .meta p { text-align: right; color: #111827; }
                        .ticket-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; margin: 6mm 0; }
                        .ticket-grid div { border: 1px solid #d1d5db; padding: 3mm; border-radius: 2mm; }
                        span { display: block; font-size: 7.5pt; text-transform: uppercase; color: #6b7280; margin-bottom: 1mm; }
                        strong { font-size: 10pt; }
                        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
                        th, td { border: 1px solid #d1d5db; padding: 2.5mm; vertical-align: top; }
                        th { width: 32%; text-align: left; background: #f9fafb; }
                        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; margin-top: 14mm; }
                        .signatures div { border-top: 1px solid #111827; padding-top: 2mm; text-align: center; font-size: 8.5pt; min-height: 13mm; }
                        .footer { margin-top: 6mm; text-align: center; color: #6b7280; font-size: 8pt; }
                        .print-actions { width: 148mm; margin: 0 auto 10px; text-align: right; }
                        .print-actions button { border: 0; background: #2563eb; color: #fff; padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; }
                        @media screen { body { padding: 16px; } }
                        @media print { body { background: #fff; } .gos-ticket { border: 0; } .print-actions { display: none; } }
                    </style>
                </head>
                <body><div class="print-actions"><button onclick="window.print()">Print Ticket</button></div>${ticketHtml(raw)}</body>
            </html>
        `;
    }

    window.previewProductionTicket = function previewProductionTicket(raw, autoPrint = false) {
        const win = window.open("", "_blank", "width=760,height=900");
        if (!win) return;
        win.document.open();
        win.document.write(documentHtml(raw));
        win.document.close();
        if (autoPrint) {
            win.onload = () => {
                win.focus();
                win.print();
            };
        }
    };

    window.printProductionTicket = function printProductionTicket(raw) {
        window.previewProductionTicket(raw, true);
    };
})();
