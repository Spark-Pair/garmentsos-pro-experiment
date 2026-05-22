function formatAllAmountInputs() {
    const allAmountInputs = document.querySelectorAll('input[type="amount"]');

    allAmountInputs.forEach((input) => {
        validateInput(input);
    });
    document.getElementById('amount-error')?.classList.add('hidden');
}

function initAmountInputs() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', formatAllAmountInputs);
    } else {
        formatAllAmountInputs();
    }
}
