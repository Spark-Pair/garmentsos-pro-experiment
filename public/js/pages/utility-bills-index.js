(() => {
function initUtilityBillsIndex() {
    const config = window.__utilityBillsIndex || {};
    const csrfToken = config.csrfToken;
    let authLayout = 'table';
    let today = formatDate(new Date(), false, true);

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid grid-cols-9 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="capitalize">${data.bill_type}</span>
            <span class="capitalize">${data.location}</span>
            <span class="capitalize">${data.account_title}</span>
            <span class="capitalize">${data.account_no}</span>
            <span class="capitalize">${data.month}</span>
            <span class="capitalize">${data.units}</span>
            <span class="capitalize">${formatMoney(data.amount)}</span>
            <span class="capitalize">${data.due_date}</span>
            <span class="capitalize">${data.status}</span>
        </div>`;
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [],
            onlyThisActions: true,
        };

        if (!data.is_paid) {
            contextMenuData.actions.push({id: 'mark-paid', text: 'Mark Paid', onclick: `markThisPaid(${data.id})`})
            createContextMenu(contextMenuData);
        }
    }

    window.markThisPaid = function(id) {
        $.ajax({
            url: `/utility-bills/${id}/mark-paid`,
            type: "PUT",
            data: {
                _token: csrfToken,
                _method: "put",
            },
            success: function (response) {
                if (response?.success) {
                    location.reload();
                    return;
                }

                alert(response?.message || "Failed to mark paid.");
            },
            error: function (xhr) {
                alert(xhr?.responseJSON?.message || "Failed to mark paid.");
            }
        });
    }
}

window.initUtilityBillsIndex = initUtilityBillsIndex;

function boot() {
    if (window.__utilityBillsIndex) initUtilityBillsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
