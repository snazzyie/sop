<?php require BASE_PATH . 'views/_partials/html_head.php'; ?>

<?php require BASE_PATH . 'views/_partials/html_header.php'; ?>

<div class="main-layout">
    <?php require BASE_PATH . 'views/_partials/html_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2>SOPs</h2>

            <div class="header-actions">
                <a href="/sessions/start" class="btn btn-primary">
                    <i class="material-icons">add</i>
                    <span>Create SOP</span>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" action="/sops" class="filters-form">
                <div class="filter-group">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search SOPs..."
                        class="form-control"
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                    >
                </div>

                <div class="filter-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo ($_GET['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>
                            Draft
                        </option>
                        <option value="published" <?php echo ($_GET['status'] ?? '') === 'published' ? 'selected' : ''; ?>>
                            Published
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <i class="material-icons">search</i>
                    <span>Filter</span>
                </button>

                <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                    <a href="/sops" class="btn btn-text">Clear Filters</a>
                <?php endif; ?>
            </form>

            <div class="stats-summary">
                <div class="stat-item">
                    <span class="stat-label">Total:</span>
                    <span class="stat-value"><?php echo $stats['total'] ?? 0; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Published:</span>
                    <span class="stat-value"><?php echo $stats['published'] ?? 0; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Draft:</span>
                    <span class="stat-value"><?php echo $stats['draft'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <!-- SOPs List -->
        <?php if (!empty($sops)): ?>
            <div class="sops-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Steps</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sops as $sop): ?>
                            <tr>
                                <td>
                                    <a href="/sops/view?id=<?php echo $sop['sop_id']; ?>" class="table-link">
                                        <?php echo htmlspecialchars($sop['title']); ?>
                                    </a>
                                    <?php if ($sop['description']): ?>
                                        <div class="table-description">
                                            <?php echo htmlspecialchars(substr($sop['description'], 0, 100)); ?>
                                            <?php if (strlen($sop['description']) > 100) echo '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-light">
                                        <?php echo $sop['step_count'] ?? 0; ?> steps
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $sop['status']; ?>">
                                        <?php echo ucfirst($sop['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($sop['creator_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($sop['updated_at'])); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="/sops/view?id=<?php echo $sop['sop_id']; ?>" class="btn-icon" title="View">
                                            <i class="material-icons">visibility</i>
                                        </a>
                                        <a href="/sops/edit?id=<?php echo $sop['sop_id']; ?>" class="btn-icon" title="Edit">
                                            <i class="material-icons">edit</i>
                                        </a>
                                        <a href="/sops/share?id=<?php echo $sop['sop_id']; ?>" class="btn-icon" title="Share">
                                            <i class="material-icons">share</i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" class="pagination-link">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" class="pagination-link">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="material-icons">description</i>
                <h3>No SOPs Found</h3>
                <p>Start recording to create your first SOP.</p>
                <a href="/sessions/start" class="btn btn-primary">Start Recording</a>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require BASE_PATH . 'views/_partials/html_footer.php'; ?>
