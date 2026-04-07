(function () {
    window.generateTagNo = function generateTagNo() {
        const supplierSelect = document.getElementById('supplier_id');
        const fabricSelect = document.getElementById('fabric_id');
        const colorSelect = document.getElementById('color');
        const unitSelect = document.getElementById('unit');
        const tagInput = document.getElementById('tag');

        if (!supplierSelect || !fabricSelect || !colorSelect || !unitSelect || !tagInput) return;

        const selectedSupplier = JSON.parse(
            supplierSelect.parentElement.parentElement.parentElement
                .querySelector('li.selected')
                ?.getAttribute('data-option') ?? '{}'
        );
        const selectedFabric = JSON.parse(
            fabricSelect.parentElement.parentElement.parentElement
                .querySelector('li.selected')
                ?.getAttribute('data-option') ?? '{}'
        );
        const selectedColor =
            colorSelect.parentElement.parentElement.parentElement
                .querySelector('li.selected')
                ?.getAttribute('data-option') ?? '';

        const supplierName = selectedSupplier.supplier_name ?? '';
        const supplierCode = supplierName
            .split(' ')
            .map(word => word.slice(0, 3).toUpperCase())
            .join('.');
        const unitCode = (unitSelect.value ?? '').charAt(0).toUpperCase();
        const colorTitle = selectedColor.toUpperCase() ?? '';
        const fabricTitle = selectedFabric.title ?? '';

        const tagNo = `${supplierCode}-${unitCode}-${colorTitle}-${fabricTitle}`;
        tagInput.value = tagNo;
    };
})();
