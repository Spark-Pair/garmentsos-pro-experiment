(() => {
function initUtilityBillsAdd() {
    let billTypeSelectDom = document.getElementById('bill_type');
    let locationSelectDom = document.getElementById('location');
    let accountSelectDom = document.getElementById('account');
    let monthInpDom = document.getElementById('month');
    let unitsInpDom = document.getElementById('units');
    let amountInpDom = document.getElementById('amount');
    let dueDateInpDom = document.getElementById('due_date');

    let selectedBillTypeId = 0;
    let selectedLocationId = 0;

    window.trackBillType = function(elem) {
        selectedBillTypeId = 0;
        if (elem.value != '') {
            locationSelectDom.disabled = false;
            selectedBillTypeId = elem.closest('.selectParent').querySelector('ul li.selected').dataset.value;
        } else {
            locationSelectDom.disabled = true;
            selectThisOption(locationSelectDom.closest('.selectParent').querySelector('ul li'));
        }
    }

    window.trackLocation = function(elem) {
        selectedLocationId = 0;
        if (elem.value != '' && billTypeSelectDom.value != '') {
            accountSelectDom.disabled = false;
            selectedLocationId = elem.closest('.selectParent').querySelector('ul li.selected').dataset.value;
            getUtilityAccounts();
        } else {
            accountSelectDom.disabled = true;
            selectThisOption(accountSelectDom.closest('.selectParent').querySelector('ul li'));
        }
    }

    window.trackAccount = function(elem) {
        if (elem.value != '') {
            monthInpDom.disabled = false;
            unitsInpDom.disabled = false;
            amountInpDom.disabled = false;
            dueDateInpDom.disabled = false;
        } else {
            monthInpDom.disabled = true;
            unitsInpDom.disabled = true;
            amountInpDom.disabled = true;
            dueDateInpDom.disabled = true;
        }
    }

    function getUtilityAccounts() {
        $.ajax({
                url: '/get-utility-accounts',
                type: 'POST',
                data: {
                    bill_type_id: selectedBillTypeId,
                    location_id: selectedLocationId,
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status == 'success') {
                        let ul = accountSelectDom.closest('.selectParent').querySelector('ul');
                        ul.innerHTML = `
                            <li data-for="account" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                -- Select Account --
                            </li>
                        `;

                        response.data.forEach(account => {
                            ul.innerHTML += `
                                <li data-for="account" data-value="${account.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap">
                                    ${account.account_title} | ${account.account_no}
                                </li>
                            `;
                        })

                        selectThisOption(accountSelectDom.closest('.selectParent').querySelector('ul li'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
    }
}

window.initUtilityBillsAdd = initUtilityBillsAdd;

function boot() {
    initUtilityBillsAdd();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
