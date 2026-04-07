(() => {
function initDailyLedgerIndex() {
    let totalDepositAmount = 0;
    let totalUseAmount = 0;
    let authLayout = 'table';

    window.createRow = function(data) {
        return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-5 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.date}</span>
                <span>${data.description}</span>
                <span>${formatNumbersWithDigits(data.deposit, 1, 1)}</span>
                <span>${formatNumbersWithDigits(data.use, 1, 1)}</span>
                <span>${formatNumbersWithDigits(data.balance, 1, 1)}</span>
            </div>
        `;
    }

    let allDataArray = [];
    let openingBalanceDom = document.querySelector('#calc-bottom >.opening-balance .text-right');
    let totalDepositDom = document.querySelector('#calc-bottom >.total-Deposit .text-right');
    let totalUseDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
    let balanceDom = document.querySelector('#calc-bottom >.balance .text-right');
    let closingBalanceDom = document.querySelector('#calc-bottom >.closing-balance .text-right');
    let infoDom = document.getElementById('info').querySelector('span');

    function renderCalculation(data) {
        openingBalanceDom.innerText = formatNumbersWithDigits(data.opening_balance, 1, 1);
        totalDepositDom.innerText = formatNumbersWithDigits(data.total_deposit, 1, 1);
        totalUseDom.innerText = formatNumbersWithDigits(data.total_use, 1, 1);
        balanceDom.innerText = formatNumbersWithDigits(data.total_deposit - data.total_use, 1, 1);
        closingBalanceDom.innerText = formatNumbersWithDigits(data.closing_balance, 1, 1);
    }

    window.renderCalculation = renderCalculation;

    window.onFilter = function() {
        if (visibleData.length === 0) {
            infoDom.textContent = `Showing 0 of ${allDataArray.length} records.`;

            if (allDataArray.length > 0) {
                let fullDeposit = allDataArray.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
                let fullUse = allDataArray.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);
                let fullBalance = fullDeposit - fullUse;

                openingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
                totalDepositDom.innerText = "0.00";
                totalUseDom.innerText = "0.00";
                balanceDom.innerText = "0.00";
                closingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
            } else {
                openingBalanceDom.innerText = "0.00";
                totalDepositDom.innerText = "0.00";
                totalUseDom.innerText = "0.00";
                balanceDom.innerText = "0.00";
                closingBalanceDom.innerText = "0.00";
            }
            return;
        }

        let sortedVisibleData = [...visibleData].sort((a, b) => {
            let dateCompare = new Date(a.date) - new Date(b.date);
            if (dateCompare !== 0) return dateCompare;
            return new Date(a.created_at) - new Date(b.created_at);
        });

        let oldestVisible = sortedVisibleData[0];

        let beforeRecords = allDataArray.filter(d => {
            let dDate = new Date(d.date);
            let oldestDate = new Date(oldestVisible.date);

            if (dDate < oldestDate) return true;
            if (dDate.getTime() === oldestDate.getTime()) {
                return new Date(d.created_at) < new Date(oldestVisible.created_at);
            }
            return false;
        });

        let openingDeposit = beforeRecords.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
        let openingUse = beforeRecords.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);
        let openingBalance = openingDeposit - openingUse;

        let visibleDeposit = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
        let visibleUse = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);

        let runningBalance = openingBalance;
        sortedVisibleData.forEach(row => {
            runningBalance += parseFloat(row.deposit || 0);
            runningBalance -= parseFloat(row.use || 0);
            row.balance = runningBalance;
        });

        let closingBalance = runningBalance;

        infoDom.textContent = `Showing ${visibleData.length} of ${allDataArray.length} records.`;
        openingBalanceDom.innerText = formatNumbersWithDigits(openingBalance, 1, 1);
        totalDepositDom.innerText = formatNumbersWithDigits(visibleDeposit, 1, 1);
        totalUseDom.innerText = formatNumbersWithDigits(visibleUse, 1, 1);
        balanceDom.innerText = formatNumbersWithDigits(visibleDeposit - visibleUse, 1, 1);
        closingBalanceDom.innerText = formatNumbersWithDigits(closingBalance, 1, 1);
    }
}

window.initDailyLedgerIndex = initDailyLedgerIndex;

function boot() {
    initDailyLedgerIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
