(() => {
    function initEmployeesCreate() {
        const config = window.__employeesCreate || {};
        const allTypes = config.allTypes || {};

        function formatPhoneNo(input) {
            let value = input.value.replace(/\D/g, "");

            if (value.length > 4) {
                value = value.slice(0, 4) + "-" + value.slice(4, 11);
            }

            input.value = value;
        }

        document.getElementById("phone_number")?.addEventListener("input", function () {
            formatPhoneNo(this);
        });

        function formatCnicNo(input) {
            let value = input.value.replace(/\D/g, "");

            if (value.length > 5 && value.length <= 12) {
                value = value.slice(0, 5) + "-" + value.slice(5);
            }
            if (value.length > 12) {
                value =
                    value.slice(0, 5) +
                    "-" +
                    value.slice(5, 12) +
                    "-" +
                    value.slice(12, 13);
            }

            input.value = value;
        }

        document.getElementById("cnic_no")?.addEventListener("input", function () {
            formatCnicNo(this);
        });

        const categorySelectDom = document.getElementById("category");
        const typeSelectDom = document.getElementById("type");
        const salaryInpDom = document.getElementById("salary");
        const salaryLabelDom = document.querySelector(`label[for="${salaryInpDom?.id}"]`);

        window.trackCategoryChange = function trackCategoryChange() {
            let clutter = "";
            if (categorySelectDom.value == "Staff") {
                const typeArray = allTypes.staff_type;

                if (typeArray.length > 0) {
                    clutter = `
                        <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                            -- Select Type --
                        </li>
                    `;
                    typeSelectDom.disabled = false;
                }

                salaryInpDom.disabled = false;
                salaryInpDom.required = true;
                salaryLabelDom.textContent = "Salary";

                typeArray.forEach((type) => {
                    clutter += `
                        <li data-for="type" data-value="${type.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                            ${type.title}
                        </li>
                    `;
                });
            } else if (categorySelectDom.value == "Worker") {
                const typeArray = allTypes.worker_type;

                if (typeArray.length > 0) {
                    clutter = `
                        <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                            -- Select Type --
                        </li>
                    `;
                    typeSelectDom.disabled = false;
                }

                salaryInpDom.disabled = true;
                salaryInpDom.required = false;
                salaryLabelDom.textContent = "Salary";

                typeArray.forEach((type) => {
                    clutter += `
                        <li data-for="type" data-value="${type.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                            ${type.title}
                        </li>
                    `;
                });
            } else {
                salaryInpDom.disabled = true;
                salaryInpDom.required = false;
                salaryLabelDom.textContent = "Salary";
                clutter = `
                    <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                        -- No options available --
                    </li>
                `;
                typeSelectDom.disabled = true;
            }

            const ul = typeSelectDom.parentElement.parentElement.parentElement.querySelector("ul");
            ul.innerHTML = clutter;
            selectThisOption(ul.querySelector("li"));
        };

        window.validateForNextStep = function validateForNextStep() {
            formatAmountInput(document.querySelector("#salary"));
            return true;
        };
    }

    window.initEmployeesCreate = initEmployeesCreate;

    function boot() {
        if (window.__employeesCreate) {
            initEmployeesCreate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
