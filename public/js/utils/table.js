function sortByThis(elem) {
    const tableHead = elem.parentElement;
    const index = Array.from(tableHead.children).indexOf(elem);
    const searchContainer = tableHead.parentElement.querySelector('.search_container');
    const rows = Array.from(searchContainer.querySelectorAll('.item'));

    searchContainer.querySelectorAll('.item').forEach((row, i) => {
        row.dataset.index = i;
    });

    const order = elem.dataset.sort === 'asc' ? 'desc' : 'asc';
    elem.dataset.sort = order;

    const isWholeNumberString = s => {
        if (!s) return false;
        const cleaned = s.replace(/,/g, '').trim();
        return /^-?\d+(\.\d+)?$/.test(cleaned);
    };

    const parseDateString = s => {
        if (!s) return NaN;
        s = s.replace(/\b(?:mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/gi, '').replace(/,/g, '').trim();

        const iso = Date.parse(s);
        if (!isNaN(iso)) return iso;

        const normalized = s.replace(/[-\/]/g, ' ').replace(/\s+/g, ' ').trim();
        const months = {
            jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
            jul: 6, aug: 7, sep: 8, sept: 8, oct: 9, nov: 10, dec: 11
        };

        let m = normalized.match(/^(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{2,4})$/);
        if (m) {
            const day = Number(m[1]);
            const mon = months[m[2].slice(0,3).toLowerCase()];
            const year = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
            if (mon !== undefined) return new Date(year, mon, day).getTime();
        }

        m = normalized.match(/^(\d{1,2})\s+([A-Za-z]{3,9})$/);
        if (m) {
            const day = Number(m[1]);
            const mon = months[m[2].slice(0,3).toLowerCase()];
            const year = new Date().getFullYear();
            if (mon !== undefined) return new Date(year, mon, day).getTime();
        }

        m = normalized.match(/^([A-Za-z]{3,9})\s+(\d{1,2})\s+(\d{2,4})$/);
        if (m) {
            const mon = months[m[1].slice(0,3).toLowerCase()];
            const day = Number(m[2]);
            const year = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
            if (mon !== undefined) return new Date(year, mon, day).getTime();
        }

        m = normalized.match(/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})$/);
        if (m) {
            const d1 = Number(m[1]), d2 = Number(m[2]), y = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
            const day = d1;
            const month = d2 - 1;
            if (month >= 0 && month <= 11) return new Date(y, month, day).getTime();
        }

        return NaN;
    };

    rows.sort((a, b) => {
        const aText = (a.children[index] && a.children[index].innerText) ? a.children[index].innerText.trim() : '';
        const bText = (b.children[index] && b.children[index].innerText) ? b.children[index].innerText.trim() : '';

        if (isWholeNumberString(aText) && isWholeNumberString(bText)) {
            const na = parseFloat(aText.replace(/,/g, ''));
            const nb = parseFloat(bText.replace(/,/g, ''));
            return order === 'asc' ? na - nb : nb - na;
        }

        const ta = parseDateString(aText);
        const tb = parseDateString(bText);
        if (!isNaN(ta) && !isNaN(tb)) {
            return order === 'asc' ? ta - tb : tb - ta;
        }

        return order === 'asc'
            ? aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' })
            : bText.localeCompare(aText, undefined, { numeric: true, sensitivity: 'base' });
    });

    searchContainer.innerHTML = '';
    rows.forEach(row => searchContainer.appendChild(row));
}

function resetSort() {
    const searchContainer = document.querySelector('.search_container');
    const rows = Array.from(searchContainer.querySelectorAll('.item'));

    rows.sort((a, b) => {
        return a.dataset.index - b.dataset.index;
    });

    searchContainer.innerHTML = '';
    rows.forEach(row => searchContainer.appendChild(row));

    document.querySelectorAll('#table-head > div').forEach(header => {
        delete header.dataset.sort;
    });
}
