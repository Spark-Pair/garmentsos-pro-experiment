function initArticlesEdit() {
    const placeholderIcon = document.querySelector('.placeholder_icon');
    if (placeholderIcon) {
        placeholderIcon.classList.remove('w-16', 'h-16');
        placeholderIcon.classList.add('rounded-md', 'w-full', 'h-auto');
    }

    let titleDom = document.getElementById('title');
    let rateDom = document.getElementById('rate');
    let calcBottom = document.querySelector('#calc-bottom');
    let ratesArrayDom = document.getElementById('rates_array');
    let rateCount = 0;

    let totalRate = 0.00;

    let ratesArray = [];
    if (ratesArrayDom && ratesArrayDom.value) {
        try {
            const parsed = JSON.parse(ratesArrayDom.value);
            if (Array.isArray(parsed)) {
                ratesArray = parsed;
            }
        } catch (_) {
            ratesArray = [];
        }
    }

    if (ratesArray.length > 0) {
        ratesArray.forEach(rate => {
            rateCount++;
            totalRate += parseFloat(rate.rate);
        });
    }

    function addRate() {
        let title = titleDom.value;
        let rate = rateDom.value;

        if (title && rate && ratesArray.filter(rate => rate.title === title).length === 0) {
            let rateList = document.querySelector('#rate-list');

            if (rateCount === 0) {
                rateList.innerHTML = '';
            }

            rateCount++;
            let rateRow = document.createElement('div');
            rateRow.classList.add('flex', 'justify-between', 'items-center', 'bg-[var(--h-bg-color)]', 'rounded-lg', 'py-2', 'px-4');
            rateRow.innerHTML = `
                <div class="grow ml-5">${title}</div>
                <div class="w-1/4">${parseFloat(rate).toFixed(2)}</div>
                <div class="w-[10%] text-center">
                    <button onclick="deleteRate(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            rateList.insertBefore(rateRow, rateList.firstChild);

            titleDom.value = '';
            rateDom.value = '';
            titleDom.focus();

            totalRate += parseFloat(rate);

            ratesArray.push({
                title: title,
                rate: rate
            });

            updateRates();
        }
    }

    window.deleteRate = function(element) {
        element.parentElement.parentElement.remove();
        rateCount--;
        if (rateCount === 0) {
            let rateList = document.querySelector('#rate-list');
            rateList.innerHTML = `
                <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>
            `;
        }

        titleDom.focus();

        let rate = parseFloat(element.parentElement.previousElementSibling.innerText);
        totalRate -= rate;

        let title = element.parentElement.previousElementSibling.previousElementSibling.innerText;
        ratesArray = ratesArray.filter(rate => rate.title !== title);

        updateRates();
    }

    function updateRates() {
        calcBottom.innerHTML = `
            <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                <div>Total - Rs.</div>
                <div class="text-right">${totalRate.toFixed(2)}</div>
            </div>
            <div class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full">
                <label for="sales_rate" class="text-nowrap grow">Sales Rate - Rs.</label>
                <input type="text" required name="sales_rate" id="sales_rate" value="${totalRate.toFixed(2)}"
                    class="text-right bg-transparent outline-none border-none w-[50%]" />
            </div>
        `;

        ratesArrayDom.value = JSON.stringify(ratesArray);
    }

    rateDom.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            addRate();
        }
    });

    window.validateForNextStep = function() {
        return true;
    }
}

function bootArticlesEdit() {
    initArticlesEdit();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootArticlesEdit);
} else {
    bootArticlesEdit();
}
