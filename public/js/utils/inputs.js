function checkMax(input) {
    input.value = input.value.replace(/\D/g, '');

    let errorElem = document.getElementById(input.id + '-error');

    const max = parseInt(input.max, 10);
    if (parseInt(input.value, 10) > max) {
        errorElem.textContent = `Value cannot exceed ${max}.`;
        if (errorElem.classList.contains('hidden')) {
            errorElem.classList.remove('hidden');
        }

        input.value = max;
    } else {
        errorElem.textContent = '';
        if (!errorElem.classList.contains('hidden')) {
            errorElem.classList.add('hidden');
        }
    }
}

function setQuantityPairError(input, message = '') {
    if (!input) return;

    const errorElem = document.getElementById(`${input.id}-error`);
    if (message) {
        input.classList.add('border-[var(--border-error)]');
        if (errorElem) {
            errorElem.textContent = message;
            errorElem.classList.remove('hidden');
        }
        return;
    }

    input.classList.remove('border-[var(--border-error)]');
    if (errorElem) {
        errorElem.textContent = '';
        errorElem.classList.add('hidden');
    }
}

function integerInputValue(input) {
    const raw = String(input?.value ?? '').trim();
    if (raw === '') {
        return { empty: true, valid: true, value: 0 };
    }

    if (!/^\d+$/.test(raw)) {
        return { empty: false, valid: false, value: 0 };
    }

    return { empty: false, valid: true, value: parseInt(raw, 10) };
}

function syncArticleQuantityPair(source, pcsPerPacket, maxPcs = 0) {
    const pcsInput = document.getElementById('quantity');
    const packetsInput = document.getElementById('quantity_packets');
    const setButton = document.getElementById('setQuantityBtn-in-modal');
    if (!pcsInput || !packetsInput) return true;

    const unit = parseInt(pcsPerPacket || 0, 10);
    const max = parseInt(maxPcs || pcsInput.max || 0, 10);
    let valid = true;

    setQuantityPairError(pcsInput);
    setQuantityPairError(packetsInput);

    if (source === 'packets') {
        const packets = integerInputValue(packetsInput);
        if (!packets.valid) {
            setQuantityPairError(packetsInput, 'Packets must be a whole number.');
            valid = false;
        } else if (unit <= 0 && !packets.empty) {
            setQuantityPairError(packetsInput, 'Pcs per packet is missing for this article.');
            valid = false;
        } else if (packets.empty) {
            pcsInput.value = '';
        } else {
            const pcs = packets.value * unit;
            pcsInput.value = pcs || '';
            if (max > 0 && pcs > max) {
                setQuantityPairError(pcsInput, `Quantity cannot exceed ${max} pcs.`);
                valid = false;
            }
        }
    } else {
        const pcs = integerInputValue(pcsInput);
        if (!pcs.valid) {
            setQuantityPairError(pcsInput, 'Quantity must be a whole number.');
            valid = false;
        } else if (pcs.empty) {
            packetsInput.value = '';
        } else if (max > 0 && pcs.value > max) {
            setQuantityPairError(pcsInput, `Quantity cannot exceed ${max} pcs.`);
            valid = false;
        } else if (unit <= 0) {
            packetsInput.value = '';
        } else if (pcs.value % unit !== 0) {
            packetsInput.value = '';
            setQuantityPairError(pcsInput, `Quantity must make whole packets of ${unit} pcs.`);
            valid = false;
        } else {
            packetsInput.value = pcs.value / unit;
        }
    }

    if (setButton) {
        setButton.disabled = !valid;
        setButton.classList.toggle('opacity-50', !valid);
        setButton.classList.toggle('cursor-not-allowed', !valid);
    }

    return valid;
}

function initializeArticleQuantityPair(pcsPerPacket, maxPcs = 0, pcsValue = '') {
    const pcsInput = document.getElementById('quantity');
    const packetsInput = document.getElementById('quantity_packets');
    if (!pcsInput || !packetsInput) return;

    pcsInput.value = pcsValue ? parseInt(pcsValue, 10) : '';
    syncArticleQuantityPair('pcs', pcsPerPacket, maxPcs);
}
