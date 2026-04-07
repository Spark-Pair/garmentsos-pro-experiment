function closeModal(modalId, animate = 'animate') {
    const modal = document.getElementById(`${modalId}-wrapper`);
    const modalForm = modal.querySelector('form');

    if (animate === 'animate') {
        modalForm.classList.add('scale-out');

        modalForm.addEventListener('animationend', () => {
            modal.classList.add('fade-out');

            modal.addEventListener('animationend', () => {
                modal.remove();
            }, { once: true });
        }, { once: true });
    } else {
        modal.remove();
    }
    document.removeEventListener('mousedown', closeOnClickOutside);
    document.removeEventListener('keydown', escToClose);
    document.removeEventListener('keydown', enterToSubmit);
}
