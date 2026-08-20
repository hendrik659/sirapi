const certificateDocumentInput = document.querySelector('[data-certificate-document]');

if (certificateDocumentInput) {
    const previewArea = document.querySelector('[data-certificate-document-preview-area]');
    const previewContent = document.querySelector('[data-certificate-document-preview-content]');
    const fileName = document.querySelector('[data-certificate-document-name]');
    const fileType = document.querySelector('[data-certificate-document-type]');
    const fileSize = document.querySelector('[data-certificate-document-size]');
    const errorMessage = document.querySelector('[data-certificate-document-error]');
    const allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    const maximumSize = 5 * 1024 * 1024;
    const initialState = {
        hidden: previewArea?.classList.contains('d-none') ?? true,
        content: previewContent?.innerHTML ?? '',
        name: fileName?.textContent ?? '-',
        type: fileType?.textContent ?? '-',
        size: fileSize?.textContent ?? '-',
    };
    let objectUrl = null;

    const revokeObjectUrl = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const restoreInitialPreview = () => {
        revokeObjectUrl();

        if (previewContent) {
            previewContent.innerHTML = initialState.content;
        }

        if (fileName) fileName.textContent = initialState.name;
        if (fileType) fileType.textContent = initialState.type;
        if (fileSize) fileSize.textContent = initialState.size;
        previewArea?.classList.toggle('d-none', initialState.hidden);
    };

    const showError = (message) => {
        restoreInitialPreview();

        if (errorMessage) {
            errorMessage.textContent = message;
            errorMessage.classList.remove('d-none');
            errorMessage.classList.add('d-block');
        }

        certificateDocumentInput.classList.add('is-invalid');
        certificateDocumentInput.value = '';
    };

    const formatSize = (bytes) => {
        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
        }

        return `${(bytes / 1024).toFixed(1)} KB`;
    };

    certificateDocumentInput.addEventListener('change', () => {
        errorMessage?.classList.add('d-none');
        errorMessage?.classList.remove('d-block');
        certificateDocumentInput.classList.remove('is-invalid');

        const file = certificateDocumentInput.files?.[0];

        if (!file) {
            restoreInitialPreview();
            return;
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!allowedMimeTypes.includes(file.type) || !allowedExtensions.includes(extension)) {
            showError('Format dokumen tidak valid. Pilih file PDF, JPG, JPEG, atau PNG.');
            return;
        }

        if (file.size > maximumSize) {
            showError('Ukuran dokumen melebihi batas maksimum 5 MB.');
            return;
        }

        revokeObjectUrl();
        objectUrl = URL.createObjectURL(file);

        if (fileName) fileName.textContent = file.name;
        if (fileType) fileType.textContent = file.type;
        if (fileSize) fileSize.textContent = formatSize(file.size);

        if (previewContent) {
            previewContent.replaceChildren();

            if (file.type === 'application/pdf') {
                const frame = document.createElement('iframe');
                frame.className = 'rs-document-frame';
                frame.src = objectUrl;
                frame.title = `Preview ${file.name}`;
                previewContent.append(frame);
            } else {
                const image = document.createElement('img');
                image.className = 'rs-document-image';
                image.src = objectUrl;
                image.alt = `Preview ${file.name}`;
                previewContent.append(image);
            }
        }

        previewArea?.classList.remove('d-none');
    });

    window.addEventListener('pagehide', revokeObjectUrl);
}
