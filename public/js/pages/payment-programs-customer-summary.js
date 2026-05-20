(function () {
    window.authLayout = 'table';

    window.createRow = function createRow(data) {
        const programs = data?.data?.payment_programs || [];
        const total_amount = programs.reduce((sum, p) => sum + Number(p.amount || 0), 0);
        const total_payment = programs.reduce((sum, p) => sum + Number(p.payment || 0), 0);
        const total_balance = programs.reduce((sum, p) => sum + Number(p.balance || 0), 0);

        if (total_balance !== 0 || total_payment !== 0) {
            return `
                <div id="${data.id}" class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out">
                    <span>${data.name_city}</span>
                    <span>${total_amount}</span>
                    <span>${total_payment}</span>
                    <span>${total_balance}</span>
                </div>`;
        }
    };
})();
