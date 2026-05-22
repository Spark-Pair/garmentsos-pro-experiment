(() => {
    function initEmployeesEdit() {
        const config = window.__employeesEdit || {};
        const employee = config.employee || null;
        const hasProfileImage = !!config.hasProfileImage;

        if (employee?.type_id) {
            const option = document.querySelector(`li[data-value="${employee.type_id}"]`);
            if (option) {
                selectThisOption(option);
            }
        }

        if (hasProfileImage) {
            const placeholderIcon = document.querySelector(".placeholder_icon");
            if (placeholderIcon) {
                placeholderIcon.classList.remove("w-16", "h-16");
                placeholderIcon.classList.add("rounded-md", "w-full", "h-auto");
            }
        }

        window.formatPhoneNo = function formatPhoneNo(input) {
            let value = input.value.replace(/\D/g, "");

            if (value.length > 4) {
                value = value.slice(0, 4) + "-" + value.slice(4, 11);
            }

            input.value = value;
        };

        window.formatCnicNo = function formatCnicNo(input) {
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
        };

        window.validateForNextStep = function validateForNextStep() {
            return true;
        };
    }

    window.initEmployeesEdit = initEmployeesEdit;

    function boot() {
        if (window.__employeesEdit) {
            initEmployeesEdit();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
