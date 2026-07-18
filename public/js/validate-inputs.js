function validationLabel(input) {
    const fieldName = input.name || input.id || 'This field';
    const label = input.closest('.form-group')?.querySelector('label')?.textContent
        || document.querySelector(`label[for="${CSS.escape(input.id || '')}"]`)?.textContent
        || fieldName.replace(/[_-]/g, ' ');

    return label.replace(/\s*\(optional\)\s*/i, '').replace(/\s*\*\s*$/, '').trim() || 'This field';
}

function validationErrorElement(input) {
    const name = input.dataset.errorFor || input.name || input.id;
    if (!name) return null;

    let errorEl = document.getElementById(`${name}-error`);
    if (errorEl) return errorEl;

    const group = input.closest('.form-group');
    if (!group) return null;

    errorEl = document.createElement('div');
    errorEl.id = `${name}-error`;
    errorEl.className = 'text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out';
    group.appendChild(errorEl);

    return errorEl;
}

function setValidationError(input, error) {
    const errorEl = validationErrorElement(input);

    if (error) {
        input.classList.add("border-[var(--border-error)]");
        input.setAttribute('aria-invalid', 'true');
        if (errorEl) {
            errorEl.classList.remove("hidden");
            errorEl.textContent = error;
        }
        return false;
    }

    input.classList.remove("border-[var(--border-error)]");
    input.removeAttribute('aria-invalid');
    if (errorEl) {
        errorEl.classList.add("hidden");
        errorEl.textContent = '';
    }
    return true;
}

function validationValue(input) {
    if (!input.dataset.errorFor) {
        return input.value;
    }

    const selectHiddenInput = input.closest('.selectParent')?.querySelector(`input.dbInput[name="${CSS.escape(input.dataset.errorFor)}"]`)
        || input.closest('.selectParent')?.querySelector(`input.dbInput[data-for="${CSS.escape(input.id || '')}"]`);

    if (selectHiddenInput) {
        return selectHiddenInput.value;
    }

    const hiddenInput = Array.from(input.closest('form')?.querySelectorAll('input[type="hidden"]') || document.querySelectorAll('input[type="hidden"]'))
        .find(field => field.name === input.dataset.errorFor);

    return hiddenInput?.value ?? input.value;
}

function normalizeValidationRules(input) {
    const rules = (input.dataset.validate || '').split('|').filter(Boolean);
    if (input.required && !rules.includes('required')) {
        rules.unshift('required');
    }

    if (input.type === 'email' && !rules.includes('email')) {
        rules.push('email');
    }

    if (input.type === 'number' && !rules.includes('numeric')) {
        rules.push('numeric');
    }

    return rules;
}

function validateInput(input) {
    if (!input || input.disabled || input.readOnly || input.type === 'hidden') {
        return true;
    }

    const rules = normalizeValidationRules(input);
    let value = input.value;
    let error = '';
    const label = validationLabel(input);
    const hasRequiredRule = rules.includes('required');
    const rawValidationValue = String(validationValue(input)).trim();

    if (!hasRequiredRule && rawValidationValue === '' && String(value).trim() === '') {
        return setValidationError(input, '');
    }

    rules.forEach(rule => {
        if (error) return;

        if (rule === 'required' && String(validationValue(input)).trim() === '') {
            error = `${label} is required.`;
        }

        if (rule === 'lowercase') {
            value = value.toLowerCase();
        }

        if (rule === 'alphanumeric') {
            if (/[^a-z0-9]/gi.test(value)) {
                error = `${label} can only contain letters and numbers.`;
            }
            value = value.replace(/[^a-z0-9]/gi, '');
        }

        if (rule === 'letters') {
            value = value.replace(/[^a-zA-Z ]/g, '');
        }

        if (rule === 'numeric') {
            const allowNegative = input.dataset.allowNegativeAmount === 'true';
            const allowedPattern = allowNegative ? /[^0-9.-]/g : /[^0-9.]/g;
            if (allowedPattern.test(value) || (allowNegative && (value.match(/-/g) || []).length > 1)) {
                error = `${label} must be a number.`;
            }
            value = value.replace(allowedPattern, '');
            if (allowNegative && value.includes('-')) {
                value = (value.trim().startsWith('-') ? '-' : '') + value.replace(/-/g, '');
            }
        }

        // friendly = allows letters, numbers, space, dot, dash
        if (rule === 'friendly') {
            if (/[^a-zA-Z0-9 .-|]/g.test(value)) {
                error = `${label} can only contain letters, numbers, spaces, dots, dashes, and pipe.`;
            }
            value = value.replace(/[^a-zA-Z0-9 .-|]/g, '');
        }

        // phone = one or more phone numbers separated by commas.
        if (rule === 'phone') {
            value = value.replace(/[^\d+,\-()\s]/g, '');
            value = value
                .split(',')
                .map(part => part.replace(/\s+/g, ' ').trim())
                .filter((part, index, parts) => part !== '' || index === parts.length - 1)
                .join(', ');

            const phoneParts = value.split(',').map(part => part.trim()).filter(Boolean);
            const isValid = phoneParts.length > 0 && phoneParts.every(part => {
                const digits = part.replace(/\D/g, '');
                return digits.length >= 7 && /^\+?[0-9][0-9\s\-()]*$/.test(part);
            });

            if (!isValid) {
                error = 'Enter valid phone number(s), separated by commas.';
            }
        }

        // urdu = only Urdu letters, Urdu & English numbers, Urdu punctuation, and spaces
        if (rule === 'urdu') {
            // Allow: Urdu letters (\u0600-\u06FF), Urdu digits (\u06F0-\u06F9), English digits (0-9), spaces, and Urdu punctuation
            value = value.replace(/[^\u0600-\u06FF\u06F0-\u06F90-9\s،۔!?؟]/g, '');

            // Check if at least one Urdu letter or number exists
            if (!/[\u0600-\u06FF\u06F0-\u06F90-9]/.test(value)) {
                error = 'Please enter in Urdu only.';
            }
        }

        if (rule === 'amount') {
            const allowNegative = input.dataset.allowNegativeAmount === 'true';
            const isNegative = allowNegative && value.trim().startsWith('-');
            // remove non-numeric characters except dot (for decimals)
            value = value.replace(/[^0-9.]/g, '');

            if (value) {
                const parts = value.split('.');

                // format integer part with commas
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

                // limit decimal part to max 2 digits
                if (parts[1]) {
                    parts[1] = parts[1].slice(0, 2);
                }

                value = parts.join('.');
            }

            if (isNegative && value) {
                value = `-${value}`;
            }
        }

        if (rule.startsWith('min:')) {
            const min = parseInt(rule.split(':')[1]);
            if (value.length < min) {
                error = `${label} must be at least ${min} characters.`;
            }
        }

        if (rule.startsWith('max:')) {
            const max = parseInt(rule.split(':')[1]);
            if (parseFloat(value) > max) {
                error = `${label} cannot be more than ${max}.`;
                value = max;
            }
        }

        if (rule === 'email' && value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) {
            error = 'Enter a valid email address.';
        }

        // if (rule.startsWith('unique:')) {
        //     const field = rule.split(':')[1];
        //     if (typeof window[field + 's'] !== 'undefined') {
        //         const dataset = window[field + 's'];
        //         if (Array.isArray(dataset) && dataset.some(item => item[field] === value)) {
        //             error = `${field.replace('_', ' ')} is already taken.`;
        //         }
        //     }
        // }

        if (rule.startsWith('unique:')) {
            const field = rule.split(':')[1];
            const dataset = window[field + 's'];
            if (Array.isArray(dataset) && dataset.includes(value)) {
                error = `${field.replace(/[_-]/g, ' ')} already exists.`;
            }
        }
    });

    input.value = value;

    return setValidationError(input, error);
}

function shouldRealtimeValidate(input) {
    return input && input.matches('input, select, textarea') && (input.required || input.dataset.validate);
}

const touchedValidationFields = new WeakSet();

function markValidationFieldTouched(input) {
    if (input) {
        touchedValidationFields.add(input);
    }
}

function shouldShowRealtimeValidation(input) {
    return touchedValidationFields.has(input) || input?.getAttribute('aria-invalid') === 'true';
}

function shouldFormatAmountOnInput(input) {
    if (!input || !input.matches('input')) {
        return false;
    }

    return (input.dataset.validate || '').split('|').includes('amount') || input.getAttribute('type') === 'amount';
}

function visibleValidationFieldForHiddenInput(input) {
    if (!input || !input.classList?.contains('dbInput')) {
        return null;
    }

    const scope = input.closest('.selectParent') || input.closest('form') || document;
    const forId = input.dataset.for || '';
    const name = input.name || '';

    return scope.querySelector(`[data-error-for="${CSS.escape(name)}"]`)
        || (forId ? scope.querySelector(`#${CSS.escape(forId)}`) : null);
}

document.addEventListener('input', (event) => {
    if (shouldFormatAmountOnInput(event.target)) {
        validateInput(event.target);
        return;
    }

    if (shouldRealtimeValidate(event.target) && shouldShowRealtimeValidation(event.target)) {
        validateInput(event.target);
    }
});

document.addEventListener('change', (event) => {
    if (shouldRealtimeValidate(event.target) && shouldShowRealtimeValidation(event.target)) {
        validateInput(event.target);
        return;
    }

    const visibleField = visibleValidationFieldForHiddenInput(event.target);
    if (visibleField && shouldRealtimeValidate(visibleField) && shouldShowRealtimeValidation(visibleField)) {
        validateInput(visibleField);
    }
});

document.addEventListener('blur', (event) => {
    if (shouldRealtimeValidate(event.target)) {
        markValidationFieldTouched(event.target);
        validateInput(event.target);
    }
}, true);

function validateAllInputs(root = document) {
    let valid = true;
    root.querySelectorAll('input, select, textarea').forEach(input => {
        if (!shouldRealtimeValidate(input)) return;
        markValidationFieldTouched(input);
        if (!validateInput(input)) valid = false;
    });

    if (!valid) {
        root.querySelector('[aria-invalid="true"]')?.focus();
    }

    return valid;
}
