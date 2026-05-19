function formatDate(date, notDay, dbDate) {
    if (!date) return '';

    const inputDate = new Date(date);
    const day = inputDate.getDate().toString().padStart(2, '0');
    const monthNum = (inputDate.getMonth() + 1).toString().padStart(2, '0');
    const month = inputDate.toLocaleString('en-US', { month: 'short' });
    const year = inputDate.getFullYear();
    const weekday = inputDate.toLocaleString('en-US', { weekday: 'short' });

    let formatted = `${day}-${month}-${year} ${weekday}`;
    if (notDay) {
        formatted = `${day}-${month}-${year}`;
    } else if (dbDate) {
        formatted = `${year}-${monthNum}-${day}`;
    }
    return formatted;
}

function formatNumbersDigitLess(number) {
    number = Number(number);
    return new Intl.NumberFormat('en-US').format(number);
}

function formatNumbersWithDigits(number, maxFraction, minFraction) {
    number = Number(number);
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: maxFraction,
        minimumFractionDigits: minFraction
    }).format(number);
}

function formatMoney(number) {
    return formatNumbersWithDigits(number, 1, 1);
}

function formatAmountInput(input) {
    let value = input.value.replace(/[^0-9.]/g, '');

    if (value.includes('.')) {
        let [intPart, decPart] = value.split('.');
        decPart = decPart.slice(0, 2);
        value = decPart ? `${intPart}.${decPart}` : intPart;
    }

    input.value = value;
    input.type = 'number';
    input.step = '0.01';
}
