<div
    class="modal fade"
    id="rsConfirmationModal"
    tabindex="-1"
    aria-labelledby="rsConfirmationModalTitle"
    aria-describedby="rsConfirmationModalMessage"
    aria-hidden="true"
    data-confirmation-modal
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rs-confirmation-modal">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5" id="rsConfirmationModalTitle" data-confirmation-title>Konfirmasi</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup konfirmasi"></button>
            </div>
            <div class="modal-body d-flex align-items-start gap-3 py-4">
                <span class="rs-confirmation-icon" aria-hidden="true" data-confirmation-icon-wrapper>
                    <i class="fa-solid fa-circle-question" data-confirmation-icon></i>
                </span>
                <p class="mb-0 text-body-secondary" id="rsConfirmationModalMessage" data-confirmation-message>
                    Pastikan Anda ingin melanjutkan tindakan ini.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="button" data-confirmation-submit>
                    <i class="fa-solid fa-check" aria-hidden="true" data-confirmation-action-icon></i>
                    <span data-confirmation-action-label>Lanjutkan</span>
                </button>
            </div>
        </div>
    </div>
</div>
