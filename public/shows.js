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

        const preview = document.getElementById('showImagePreview');
        preview.innerHTML = isEdit && show.hasImage
            ? '<img src="' + escapeHtml(show.imageUrl) + '" alt="" style="height: 60px; object-fit: contain;">'
            : '';

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

    async function handleImageChange(e) {
        const file = e.target.files[0];
        if (!file) return;

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
        document.getElementById('showLocation').addEventListener('input', handleLocationInput);
        document.getElementById('showStartDate').addEventListener('input', handleStartDateChange);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
