import { Modal, Toast } from 'bootstrap';

document.querySelectorAll('[data-global-toast]').forEach((toastElement) => {
    Toast.getOrCreateInstance(toastElement).show();
});

const confirmationModalElement = document.querySelector('[data-confirmation-modal]');

if (confirmationModalElement) {
    const confirmationModal = Modal.getOrCreateInstance(confirmationModalElement);
    const titleElement = confirmationModalElement.querySelector('[data-confirmation-title]');
    const messageElement = confirmationModalElement.querySelector('[data-confirmation-message]');
    const iconElement = confirmationModalElement.querySelector('[data-confirmation-icon]');
    const iconWrapper = confirmationModalElement.querySelector('[data-confirmation-icon-wrapper]');
    const actionButton = confirmationModalElement.querySelector('[data-confirmation-submit]');
    const actionIcon = confirmationModalElement.querySelector('[data-confirmation-action-icon]');
    const actionLabel = confirmationModalElement.querySelector('[data-confirmation-action-label]');
    const allowedVariants = ['primary', 'success', 'danger', 'warning'];
    const allowedIconPattern = /^fa-[a-z0-9-]+$/;
    const confirmedForms = new WeakSet();
    let pendingForm = null;
    let pendingSubmitter = null;
    let lastTrigger = null;

    document.querySelectorAll('form[data-confirmation]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (confirmedForms.has(form)) {
                confirmedForms.delete(form);
                return;
            }

            event.preventDefault();

            const requestedVariant = form.dataset.confirmationVariant ?? 'primary';
            const variant = allowedVariants.includes(requestedVariant) ? requestedVariant : 'primary';
            const requestedIcon = form.dataset.confirmationIcon ?? 'fa-circle-question';
            const icon = allowedIconPattern.test(requestedIcon) ? requestedIcon : 'fa-circle-question';

            pendingForm = form;
            pendingSubmitter = event.submitter instanceof HTMLElement
                ? event.submitter
                : form.querySelector('button[type="submit"], input[type="submit"]');
            lastTrigger = pendingSubmitter;

            if (titleElement) titleElement.textContent = form.dataset.confirmationTitle ?? 'Konfirmasi';
            if (messageElement) messageElement.textContent = form.dataset.confirmationMessage ?? 'Pastikan Anda ingin melanjutkan tindakan ini.';
            if (actionLabel) actionLabel.textContent = form.dataset.confirmationActionLabel ?? 'Lanjutkan';

            if (iconElement) {
                iconElement.className = `fa-solid ${icon}`;
            }

            iconWrapper?.setAttribute('data-confirmation-variant', variant);
            actionButton?.setAttribute('class', `btn btn-${variant} d-inline-flex align-items-center justify-content-center gap-2`);
            actionIcon?.setAttribute('class', `fa-solid ${icon}`);

            confirmationModal.show(lastTrigger);
        });
    });

    actionButton?.addEventListener('click', () => {
        if (!pendingForm) {
            return;
        }

        const form = pendingForm;
        const submitter = pendingSubmitter;

        pendingForm = null;
        pendingSubmitter = null;
        confirmedForms.add(form);
        confirmationModal.hide();
        form.requestSubmit(submitter ?? undefined);

        queueMicrotask(() => confirmedForms.delete(form));
    });

    confirmationModalElement.addEventListener('hidden.bs.modal', () => {
        pendingForm = null;
        pendingSubmitter = null;

        if (lastTrigger instanceof HTMLElement && document.contains(lastTrigger)) {
            lastTrigger.focus();
        }

        lastTrigger = null;
    });
}
