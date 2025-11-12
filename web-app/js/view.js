// SOP View page functionality
let sopData = null;
let sopId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Require authentication
    if (!requireAuth()) return;

    // Get SOP ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    sopId = urlParams.get('id');

    if (!sopId) {
        showError('No SOP ID provided');
        return;
    }

    setupViewPage();
    loadSOP();
});

function setupViewPage() {
    // Share button
    document.getElementById('share-btn')?.addEventListener('click', handleShare);

    // Export button
    document.getElementById('export-btn')?.addEventListener('click', openExportModal);

    // Export modal
    setupExportModal();
}

async function loadSOP() {
    showLoading();

    try {
        const data = await apiRequest(`/sops/view/${sopId}`);

        sopData = data.data;
        displaySOP(sopData.sop, sopData.steps);

    } catch (error) {
        console.error('Failed to load SOP:', error);
        showError(error.message);
    } finally {
        hideLoading();
    }
}

function displaySOP(sop, steps) {
    // Update header
    document.getElementById('sop-title').textContent = sop.title;
    document.getElementById('sop-description').textContent = sop.description || 'No description provided';
    document.getElementById('sop-created').textContent = new Date(sop.created_at).toLocaleDateString();
    document.getElementById('sop-steps-count').textContent = sop.total_steps;
    document.getElementById('sop-views').textContent = sop.views_count;

    const statusBadge = document.getElementById('sop-status');
    statusBadge.textContent = sop.status;
    statusBadge.className = `badge badge-${sop.status}`;

    // Display steps
    displaySteps(steps);

    // Show content
    document.getElementById('sop-content').style.display = 'block';
}

function displaySteps(steps) {
    const container = document.getElementById('steps-container');
    container.innerHTML = '';

    steps.forEach(step => {
        const stepCard = createStepCard(step);
        container.appendChild(stepCard);
    });
}

function createStepCard(step) {
    const card = document.createElement('div');
    card.className = 'step-card';

    const pageData = step.page_data || {};
    const elementData = step.element_data || {};

    card.innerHTML = `
        <div class="step-header">
            <div class="step-number">${step.step_number}</div>
            <div class="step-title">${getStepTitle(step)}</div>
        </div>

        <div class="step-description">
            ${escapeHtml(step.ai_description || 'No description available')}
        </div>

        ${step.screenshot_url ? `
            <img src="${step.screenshot_url}"
                 alt="Step ${step.step_number}"
                 class="step-screenshot"
                 onclick="openImageModal('${step.screenshot_url}')">
        ` : ''}

        ${step.tips ? `
            <div class="step-tips">
                <strong>💡 Tip:</strong> ${escapeHtml(step.tips)}
            </div>
        ` : ''}

        <div class="step-details">
            <strong>Action:</strong> ${escapeHtml(step.action_type)}<br>
            ${pageData.url ? `<strong>Page:</strong> ${escapeHtml(pageData.url)}<br>` : ''}
            ${elementData.text ? `<strong>Element:</strong> ${escapeHtml(elementData.text)}<br>` : ''}
        </div>
    `;

    return card;
}

function getStepTitle(step) {
    if (step.action_verb) {
        return step.action_verb;
    }

    const actionTitles = {
        'click': 'Click',
        'input': 'Enter Text',
        'select': 'Select Option',
        'navigate': 'Navigate',
        'submit': 'Submit Form',
        'checkbox': 'Toggle Checkbox',
        'radio': 'Select Radio Button'
    };

    return actionTitles[step.action_type] || step.action_type;
}

// Share functionality
async function handleShare() {
    try {
        const data = await apiRequest('/shares/create', {
            method: 'POST',
            body: JSON.stringify({
                sopId: sopId,
                visibility: 'link-only'
            })
        });

        const shareUrl = data.data.shareUrl;

        // Copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('Share link copied to clipboard!\n\n' + shareUrl);
        }).catch(() => {
            prompt('Share link (Ctrl+C to copy):', shareUrl);
        });

    } catch (error) {
        alert('Failed to create share link: ' + error.message);
    }
}

// Export Modal
function setupExportModal() {
    const modal = document.getElementById('export-modal');

    // Export options
    const exportOptions = document.querySelectorAll('.export-option');
    exportOptions.forEach(option => {
        option.addEventListener('click', () => {
            const format = option.getAttribute('data-format');
            exportSOP(format);
        });
    });

    // Close modal
    const closeButtons = modal?.querySelectorAll('.modal-close');
    closeButtons?.forEach(btn => {
        btn.addEventListener('click', closeExportModal);
    });

    // Close on outside click
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeExportModal();
        }
    });
}

function openExportModal() {
    const modal = document.getElementById('export-modal');
    modal.classList.add('active');
    document.getElementById('export-progress').style.display = 'none';
    document.getElementById('export-result').style.display = 'none';
}

function closeExportModal() {
    const modal = document.getElementById('export-modal');
    modal.classList.remove('active');
}

async function exportSOP(format) {
    const progressDiv = document.getElementById('export-progress');
    const resultDiv = document.getElementById('export-result');

    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';

    try {
        const data = await apiRequest(`/exports/${format}`, {
            method: 'POST',
            body: JSON.stringify({ sopId: sopId })
        });

        // Show download link
        const downloadLink = document.getElementById('download-link');
        downloadLink.href = data.data.downloadUrl;
        downloadLink.download = data.data.filename;

        progressDiv.style.display = 'none';
        resultDiv.style.display = 'block';

    } catch (error) {
        alert('Failed to export SOP: ' + error.message);
        progressDiv.style.display = 'none';
    }
}

// Image modal
function openImageModal(imageUrl) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 90%; max-height: 90vh;">
            <div class="modal-header">
                <h3>Screenshot</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <img src="${imageUrl}" style="max-width: 100%; height: auto;">
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector('.modal-close').addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Utility functions
function showLoading() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('sop-content').style.display = 'none';
    document.getElementById('error-state').style.display = 'none';
}

function hideLoading() {
    document.getElementById('loading-state').style.display = 'none';
}

function showError(message) {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('sop-content').style.display = 'none';
    document.getElementById('error-state').style.display = 'block';
    document.getElementById('error-message').textContent = message;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
