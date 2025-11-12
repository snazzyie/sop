// Dashboard functionality
let currentPage = 1;
let currentStatus = '';
let currentSearch = '';
let currentSopId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Require authentication
    if (!requireAuth()) return;

    setupDashboard();
    loadSOPs();
});

function setupDashboard() {
    // Search
    const searchInput = document.getElementById('search-input');
    searchInput?.addEventListener('input', debounce((e) => {
        currentSearch = e.target.value;
        currentPage = 1;
        loadSOPs();
    }, 500));

    // Status filter
    const statusFilter = document.getElementById('status-filter');
    statusFilter?.addEventListener('change', (e) => {
        currentStatus = e.target.value;
        currentPage = 1;
        loadSOPs();
    });

    // Pagination
    document.getElementById('prev-page')?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadSOPs();
        }
    });

    document.getElementById('next-page')?.addEventListener('click', () => {
        currentPage++;
        loadSOPs();
    });

    // Share modal
    setupShareModal();
}

async function loadSOPs() {
    showLoading();

    try {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 12
        });

        if (currentStatus) params.append('status', currentStatus);
        if (currentSearch) params.append('search', currentSearch);

        const data = await apiRequest(`/sops/list?${params.toString()}`);

        displaySOPs(data.data.sops);
        updatePagination(data.data.pagination);

        if (data.data.sops.length === 0) {
            showEmptyState();
        } else {
            hideEmptyState();
        }

    } catch (error) {
        console.error('Failed to load SOPs:', error);
        showError('Failed to load SOPs. Please try again.');
    } finally {
        hideLoading();
    }
}

function displaySOPs(sops) {
    const container = document.getElementById('sops-container');
    container.innerHTML = '';

    sops.forEach(sop => {
        const card = createSOPCard(sop);
        container.appendChild(card);
    });
}

function createSOPCard(sop) {
    const card = document.createElement('div');
    card.className = 'sop-card';

    const statusClass = `badge-${sop.status}`;
    const createdDate = new Date(sop.created_at).toLocaleDateString();

    card.innerHTML = `
        <div class="sop-card-header">
            <h3 class="sop-card-title">${escapeHtml(sop.title)}</h3>
            <p class="sop-card-description">${escapeHtml(sop.description || 'No description')}</p>
        </div>
        <div class="sop-card-meta">
            <span>📅 ${createdDate}</span>
            <span>📊 ${sop.total_steps} steps</span>
            <span>👁️ ${sop.views_count} views</span>
        </div>
        <div class="sop-card-actions">
            <span class="badge ${statusClass}">${sop.status}</span>
            <button class="btn btn-sm btn-secondary share-btn" data-sop-id="${sop.sop_id}">Share</button>
            <button class="btn btn-sm btn-danger delete-btn" data-sop-id="${sop.sop_id}">Delete</button>
        </div>
    `;

    // Click to view
    card.addEventListener('click', (e) => {
        if (!e.target.classList.contains('btn')) {
            window.location.href = `/pages/view.html?id=${sop.sop_id}`;
        }
    });

    // Share button
    card.querySelector('.share-btn').addEventListener('click', (e) => {
        e.stopPropagation();
        currentSopId = sop.sop_id;
        openShareModal();
    });

    // Delete button
    card.querySelector('.delete-btn').addEventListener('click', (e) => {
        e.stopPropagation();
        if (confirm('Are you sure you want to delete this SOP?')) {
            deleteSOP(sop.sop_id);
        }
    });

    return card;
}

async function deleteSOP(sopId) {
    try {
        await apiRequest(`/sops/${sopId}`, { method: 'DELETE' });
        loadSOPs(); // Reload list
    } catch (error) {
        alert('Failed to delete SOP: ' + error.message);
    }
}

function updatePagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    const pageInfo = document.getElementById('page-info');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    if (pagination.total_pages > 1) {
        paginationDiv.style.display = 'flex';
        pageInfo.textContent = `Page ${pagination.page} of ${pagination.total_pages}`;
        prevBtn.disabled = pagination.page <= 1;
        nextBtn.disabled = pagination.page >= pagination.total_pages;
    } else {
        paginationDiv.style.display = 'none';
    }
}

// Share Modal
function setupShareModal() {
    const modal = document.getElementById('share-modal');
    const visibilitySelect = document.getElementById('share-visibility');
    const passwordGroup = document.getElementById('password-group');
    const createBtn = document.getElementById('create-share-btn');
    const copyBtn = document.getElementById('copy-url-btn');

    // Show/hide password field
    visibilitySelect?.addEventListener('change', (e) => {
        passwordGroup.style.display = e.target.value === 'password' ? 'block' : 'none';
    });

    // Create share link
    createBtn?.addEventListener('click', createShareLink);

    // Copy URL
    copyBtn?.addEventListener('click', () => {
        const urlInput = document.getElementById('share-url');
        urlInput.select();
        document.execCommand('copy');
        alert('Share URL copied to clipboard!');
    });

    // Close modal
    const closeButtons = modal?.querySelectorAll('.modal-close');
    closeButtons?.forEach(btn => {
        btn.addEventListener('click', closeShareModal);
    });

    // Close on outside click
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeShareModal();
        }
    });
}

function openShareModal() {
    const modal = document.getElementById('share-modal');
    modal.classList.add('active');
    document.getElementById('share-result').style.display = 'none';
}

function closeShareModal() {
    const modal = document.getElementById('share-modal');
    modal.classList.remove('active');
}

async function createShareLink() {
    const visibility = document.getElementById('share-visibility').value;
    const password = document.getElementById('share-password').value;
    const expiresAt = document.getElementById('share-expiry').value;

    try {
        const body = {
            sopId: currentSopId,
            visibility: visibility
        };

        if (visibility === 'password' && password) {
            body.password = password;
        }

        if (expiresAt) {
            body.expiresAt = expiresAt;
        }

        const data = await apiRequest('/shares/create', {
            method: 'POST',
            body: JSON.stringify(body)
        });

        // Show share URL
        document.getElementById('share-url').value = data.data.shareUrl;
        document.getElementById('share-result').style.display = 'block';
        document.getElementById('create-share-btn').style.display = 'none';

    } catch (error) {
        alert('Failed to create share link: ' + error.message);
    }
}

// Utility functions
function showLoading() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('sops-container').style.display = 'none';
}

function hideLoading() {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('sops-container').style.display = 'grid';
}

function showEmptyState() {
    document.getElementById('empty-state').style.display = 'block';
    document.getElementById('sops-container').style.display = 'none';
}

function hideEmptyState() {
    document.getElementById('empty-state').style.display = 'none';
}

function showError(message) {
    alert(message); // Simple error display
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
