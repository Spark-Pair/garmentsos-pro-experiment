(() => {
    function initAttendancesManageSalary() {
        let addedTypes = [];
        let selectedEmployee = {};
        let finalArears = 0;
        let finalSalary = 0;
        let finalDeduction = 0;

        window.trackEmployeeState = function trackEmployeeState(elem) {
            selectedEmployee = JSON.parse(
                elem.closest(".selectParent").querySelector("li.selected").dataset.option || "{}"
            );
            finalArears = selectedEmployee.balance || 0;
            finalSalary = selectedEmployee.salary || 0;
            renderFinals();
        };

        function renderFinals() {
            calculateDeduction();
            document.getElementById("finalArears").value = finalArears;
            document.getElementById("finalSalary").value = finalSalary;
            document.getElementById("finalDeduction").value = finalDeduction;
            document.getElementById("finalBalance").value =
                finalArears + finalSalary - finalDeduction;
            validateAllInputs();
        }
        renderFinals();

        window.addTypeWithCount = function addTypeWithCount() {
            let typeSelectDom = document.getElementById("type");
            let countInpDom = document.getElementById("count");

            if (
                typeSelectDom.value !== "" &&
                typeSelectDom.value !== "-- Select Attendance Type --" &&
                countInpDom.value > 0
            ) {
                addedTypes = addedTypes.filter((t) => t.type !== typeSelectDom.value);
                let type = {};

                type.type = typeSelectDom.value;
                type.count = countInpDom.value;

                addedTypes.push(type);

                renderTypes();
                renderFinals();

                selectThisOption(typeSelectDom.closest(".selectParent").querySelector("ul li"));
                countInpDom.value = "";
            }
        };

        function renderTypes() {
            let typeListDom = document.getElementById("type-list");

            if (addedTypes.length > 0) {
                typeListDom.innerHTML = "";
                addedTypes.forEach((type, index) => {
                    typeListDom.innerHTML += `
                        <div class="grid grid-cols-4 border-t border-gray-600 py-3 px-4">
                            <div>${index + 1}</div>
                            <div class="capitalize" >${type.type}</div>
                            <div>${type.count}</div>
                            <div class="text-center">
                                <button onclick="deselectThisType(${index})" type="button" class="text-[var(--danger-color)] cursor-pointer text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                typeListDom.innerHTML = `
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Type Added</div>
                `;
            }
            document.getElementById("types").value = JSON.stringify(addedTypes);
        }
        renderTypes();

        window.deselectThisType = function deselectThisType(index) {
            addedTypes.splice(index, 1);
            renderTypes();
            renderFinals();
        };

        function calculateDeduction() {
            finalDeduction = 0;
            addedTypes.forEach((type) => {
                if (type.type == "Absent") {
                    finalDeduction += (finalSalary / 30) * type.count;
                } else if (type.type == "Late") {
                    const equivalentAbsentDays = Math.floor(type.count / 4);
                    finalDeduction += (finalSalary / 30) * equivalentAbsentDays;
                }
            });
        }
    }

    window.initAttendancesManageSalary = initAttendancesManageSalary;

    function boot() {
        initAttendancesManageSalary();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
