function initSuppliersCreate(config) {
    let categoriesArray = [];
    window.usernames = config?.usernames || [];

    let addCategoryBtnDom = document.getElementById('addCategoryBtn');
    let categorySelectDom = document.getElementById('category_select');
    let chipsDom = document.getElementById('chips');
    let categoriesArrayInput = document.getElementById('categories_array');
    let categoryErrorDom = document.getElementById('category-error');

    window.trackStateOfCategoryBtn = function(elem) {
        if (elem.value != "") {
            addCategoryBtnDom.disabled = false;
        } else {
            addCategoryBtnDom.disabled = true;
        }
    }

    if (addCategoryBtnDom) {
        addCategoryBtnDom.addEventListener('click', () => {
            addCategory();
        });
    }

    function addCategory() {
        if (categoriesArray.length <= 0) {
            chipsDom.innerHTML = '';
        }

        let selectedCategoryId = categorySelectDom.closest('.selectParent').querySelector('ul li.selected').dataset.value;
        let selectedCategoryName = categorySelectDom.parentElement.parentElement.parentElement.querySelector("ul li.selected").textContent.trim();

        if (categoriesArray.includes(selectedCategoryId)) {
            let existingChip = Array.from(chipsDom.children).find(chip =>
                chip.getAttribute('data-id') === selectedCategoryId
            );

            if (existingChip) {
                if (typeof showMessageBox === 'function') {
                    showMessageBox('error', 'This category already exists.');
                }
                existingChip.classList.add('bg-[var(--bg-error)]', 'transition', 'duration-300');
                setTimeout(() => {
                    existingChip.classList.remove('bg-[var(--bg-error)]');
                }, 5000);
                categorySelectDom.value = '';
                addCategoryBtnDom.disabled = true;
                categorySelectDom.focus();
            }

            return;
        }

        if (selectedCategoryId) {
            let chip = document.createElement('div');
            chip.className = 'chip border border-gray-600 text-[var(--secondary-text)] text-xs rounded-xl py-2 px-4 inline-flex items-center gap-2 fade-in';
            chip.setAttribute('data-id', selectedCategoryId);
            chip.innerHTML = `
                <div class="text tracking-wide">${selectedCategoryName}</div>
                <button class="delete cursor-pointer" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                        class="size-3.5 stroke-[var(--secondary-text)]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;

            chip.querySelector('.delete').onclick = () => {
                chip.classList.add('fade-out');

                setTimeout(() => {
                    chip.remove();
                    categoriesArray = categoriesArray.filter(cat => cat !== selectedCategoryId);

                    if (categoriesArray.length <= 0) {
                        chipsDom.innerHTML = `
                            <div class="chip border border-gray-600 text-[var(--secondary-text)] text-xs rounded-xl py-2 px-4 inline-flex items-center gap-2 mx-auto">
                                <div class="text tracking-wide text-gray-400">Please add category</div>
                            </div>
                        `;
                    }

                    categoriesArrayInput.value = JSON.stringify(categoriesArray);
                }, 300);
            }

            if (chipsDom) {
                chipsDom.appendChild(chip);
                categoriesArray.push(selectedCategoryId);
                categoriesArrayInput.value = JSON.stringify(categoriesArray);
                addCategoryBtnDom.disabled = true;
                selectThisOption(categorySelectDom.parentElement.parentElement.parentElement.querySelector("ul li"));
                categorySelectDom.focus();
            } else {
                console.error('Chip container not found!');
            }
        } else {
            console.warn('No category selected!');
        }
    }

    window.validateForNextStep = function() {
        return true;
    }
}

function bootSuppliersCreate() {
    if (window.__suppliersCreate) {
        initSuppliersCreate(window.__suppliersCreate);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootSuppliersCreate);
} else {
    bootSuppliersCreate();
}
