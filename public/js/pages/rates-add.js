(() => {
    function initRatesAdd() {
        const config = window.__ratesAdd || {};
        const articleDetails = config.articleDetails || {};

        window.trackTypeStatus = function trackTypeStatus(elem) {
            const effectiveDate = document.querySelector("#effective_date");
            if (elem.value != "") {
                if (effectiveDate) effectiveDate.disabled = false;

                const step2 = document.querySelector(".step2 .inputsWrapper");
                const selectedText = elem
                    .closest(".selectParent")
                    ?.querySelector('ul[data-for="type"] li.selected')
                    ?.textContent?.trim();

                if (step2 && selectedText == "Cutting") {
                    step2.innerHTML = config.cuttingFieldsHtml || "";
                }
            } else if (effectiveDate) {
                effectiveDate.disabled = true;
            }
        };

        window.trackEffectiveDateState = function trackEffectiveDateState() {
            gotoStep(2);
        };

        window.generateSelectCredentialsModal = function generateSelectCredentialsModal(type) {
            const typesArray = Object.entries(articleDetails[type] || {});
            const cardData = [];

            if (typesArray.length > 0) {
                typesArray.forEach(([key, value]) => {
                    cardData.push({
                        id: key,
                        name: value.text,
                        checkbox: true,
                        checked: value.selected || false,
                        data: { key, value },
                        onclick: `selectThisCard(this, \"${type}\")`,
                    });
                });
            }

            const credentialsModalData = {
                id: "credentialsModalForm",
                class: "h-[60%] w-full",
                cards: {
                    name: `Select ${type}`,
                    count: 4,
                    data: cardData,
                },
            };

            createModal(credentialsModalData);
        };

        window.selectThisCard = function selectThisCard(elem, type) {
            const selecttypeInp = document.getElementById(`select_${type}`);
            const selecttypeInpDB = document.querySelector(`input[name="${type}"]`);
            const selectedtype = JSON.parse(elem.dataset.json);
            const selectedtypeId = selectedtype.id;
            const selectedtypeInDetails = articleDetails[type][selectedtypeId];
            const checkbox = elem.querySelector("input[type='checkbox']");
            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                selectedtypeInDetails.selected = true;
            } else {
                selectedtypeInDetails.selected = false;
            }

            const selectedTypes = Object.entries(articleDetails[type]).filter(
                ([, value]) => value.selected === true
            );
            const selectedTexts = selectedTypes.map(([, value]) => value.text).join(" | ");

            if (selecttypeInp) selecttypeInp.value = selectedTexts;
            if (selecttypeInpDB) {
                selecttypeInpDB.value = JSON.stringify(selectedTypes.map(([key]) => key)) || "";
            }
        };

        window.validateForNextStep = function validateForNextStep() {
            return true;
        };
    }

    window.initRatesAdd = initRatesAdd;

    function boot() {
        if (window.__ratesAdd) {
            initRatesAdd();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
