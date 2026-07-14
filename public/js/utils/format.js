function formatDate(date, notDay, dbDate) {
    if (!date) return '';

    const inputDate = parseLocalDate(date);
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

function parseLocalDate(date) {
    if (date instanceof Date) return date;

    const value = String(date).trim();
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s|T|$)/);
    if (match) {
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    return new Date(value);
}

function localDateString(date = new Date()) {
    const inputDate = parseLocalDate(date);
    const year = inputDate.getFullYear();
    const month = String(inputDate.getMonth() + 1).padStart(2, '0');
    const day = String(inputDate.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatNumbersDigitLess(number) {
    number = parseFormattedNumber(number);
    return new Intl.NumberFormat('en-US').format(number);
}

function formatNumbersWithDigits(number, maxFraction, minFraction) {
    number = parseFormattedNumber(number);
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: maxFraction,
        minimumFractionDigits: minFraction
    }).format(number);
}

function formatMoney(number) {
    return formatNumbersWithDigits(number, 1, 1);
}

function parseFormattedNumber(number) {
    if (number === null || typeof number === 'undefined' || number === '') return 0;
    if (typeof number === 'number') return Number.isFinite(number) ? number : 0;

    const parsed = Number(String(number).replace(/,/g, '').trim());
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatAmountInput(input) {
    const allowNegative = input.dataset.allowNegativeAmount === 'true';
    const isNegative = allowNegative && input.value.trim().startsWith('-');
    let value = input.value.replace(/[^0-9.]/g, '');

    if (value.includes('.')) {
        let [intPart, decPart] = value.split('.');
        decPart = decPart.slice(0, 2);
        value = decPart ? `${intPart}.${decPart}` : intPart;
    }

    input.value = isNegative && value ? `-${value}` : value;
    input.type = 'number';
    input.step = '0.01';
}
