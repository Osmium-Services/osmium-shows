(function () {
    'use strict';

    const config = window.ShowsConfig || {};
    const actionUrl = config.basePath + 'shows/action/';
    const venuePostcodes = config.venuePostcodes || {};

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        const resultDiv = document.getElementById('showResult');
        resultDiv.className = 'alert alert-danger';
        resultDiv.textContent = message;
        resultDiv.classList.remove('d-none');
    }

    function clearResult() {
        const resultDiv = document.getElementById('showResult');
        resultDiv.className = 'alert d-none';
        resultDiv.textContent = '';
    }

    async function postJson(payload) {
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: window.csrfToken }, payload))
        });

        return response.json();
    }

    function openModal(show) {
        clearResult();

        const isEdit = Boolean(show);
        document.getElementById('showModalTitle').textContent = isEdit ? 'Edit Show' : 'Add Show';
        document.getElementById('showId').value = isEdit ? show.id : '';
        document.getElementById('showName').value = isEdit ? show.name : '';
        document.getElementById('showStartDate').value = isEdit ? show.startDate : '';
        document.getElementById('showEndDate').value = isEdit ? show.endDate : '';
        document.getElementById('showLocation').value = isEdit ? show.location : '';
        document.getElementById('showPostcode').value = isEdit ? show.postcode : '';
        document.getElementById('showLinkUrl').value = isEdit ? show.linkUrl : '';
        document.getElementById('showIsPublished').checked = isEdit ? show.isPublished : false;
        document.getElementById('showImageFile').value = '';

        // A logo upload posts against an existing id, so it can only be offered
        // once the show has been saved at least once.
        document.getElementById('showImageRow').hidden = !isEdit;

        showLogo(isEdit && show.hasImage ? show.imageUrl : '');

        new bootstrap.Modal(document.getElementById('showModal')).show();
    }

    async function handleSubmit(e) {
        e.preventDefault();
        clearResult();

        const btn = document.getElementById('showSaveButton');
        btn.disabled = true;

        const id = document.getElementById('showId').value;

        try {
            const result = await postJson({
                action: id ? 'update' : 'create',
                id: id,
                name: document.getElementById('showName').value,
                start_date: document.getElementById('showStartDate').value,
                end_date: document.getElementById('showEndDate').value,
                location: document.getElementById('showLocation').value,
                postcode: document.getElementById('showPostcode').value,
                link_url: document.getElementById('showLinkUrl').value,
                is_published: document.getElementById('showIsPublished').checked
            });

            if (result.success) {
                window.location.reload();
                return;
            }

            showError(result.error || 'Failed to save');
        } catch (error) {
            showError('An error occurred while saving');
        } finally {
            btn.disabled = false;
        }
    }

    function showLogo(src) {
        const zone = document.getElementById('showLogoZone');
        const preview = zone.querySelector('.show-logo-preview');
        preview.classList.toggle('d-none', !src);
        zone.querySelector('.show-logo-empty').classList.toggle('d-none', !!src);
        zone.querySelector('.show-logo-remove').classList.toggle('d-none', !src);
        if (src) preview.src = src;
    }

    function initLogoZone() {
        const zone = document.getElementById('showLogoZone');
        const input = document.getElementById('showImageFile');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.add('drag-active'); });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.remove('drag-active'); });
        });
        zone.addEventListener('drop', function (e) { uploadImage(e.dataTransfer.files[0]); });
        zone.addEventListener('click', function (e) {
            if (!e.target.closest('.show-logo-remove')) input.click();
        });
        zone.querySelector('.show-logo-remove').addEventListener('click', function (e) {
            e.stopPropagation();
            removeImage();
        });
    }

    async function handleImageChange(e) {
        await uploadImage(e.target.files[0]);
    }

    async function removeImage() {
        clearResult();

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_image', id: document.getElementById('showId').value, csrf_token: window.csrfToken })
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
                return;
            }

            showError(result.error || 'Failed to remove logo');
        } catch (error) {
            showError('An error occurred while removing the logo');
        }
    }

    async function uploadImage(file) {
        if (!file || !/^image\/(jpeg|png|gif|webp|avif)$/.test(file.type)) return;

        clearResult();

        const formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('id', document.getElementById('showId').value);
        formData.append('csrf_token', window.csrfToken);
        formData.append('image', file);

        try {
            const response = await fetch(actionUrl, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
                return;
            }

            showError(result.error || 'Failed to upload logo');
        } catch (error) {
            showError('An error occurred while uploading the logo');
        }
    }

    function handleStartDateChange(e) {
        const endDate = document.getElementById('showEndDate');
        if (!endDate.value) endDate.value = e.target.value;
    }

    function handleLocationInput(e) {
        const postcode = venuePostcodes[e.target.value];
        if (postcode) document.getElementById('showPostcode').value = postcode;
    }

    function init() {
        document.getElementById('addShowButton').addEventListener('click', function () {
            openModal(null);
        });

        // Row actions come from the ListView; Edit fires list:action with the row's id
        document.addEventListener('list:action', function (e) {
            if (e.detail.action !== 'edit') return;
            openModal(config.shows[e.detail.id]);
        });

        document.getElementById('showForm').addEventListener('submit', handleSubmit);
        document.getElementById('showImageFile').addEventListener('change', handleImageChange);
        initLogoZone();
        document.getElementById('showLocation').addEventListener('input', handleLocationInput);
        document.getElementById('showStartDate').addEventListener('input', handleStartDateChange);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
