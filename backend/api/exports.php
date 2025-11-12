<?php
// Exports API endpoints - for exporting SOPs to different formats

function handleExportsRequest($action, $param, $method, $body) {
    if ($method !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    switch ($action) {
        case 'pdf':
            exportPDF($body);
            break;

        case 'html':
            exportHTML($body);
            break;

        case 'markdown':
            exportMarkdown($body);
            break;

        default:
            Response::notFound('Export endpoint not found');
    }
}

/**
 * Export SOP as PDF
 */
function exportPDF($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    if (empty($body['sopId'])) {
        Response::validationError(['sopId' => 'SOP ID is required']);
    }

    $sopId = $body['sopId'];

    try {
        // Get SOP data
        list($sop, $steps) = getSOPForExport($sopId, $userId);

        // Generate HTML content
        $html = generateExportHTML($sop, $steps);

        // For production, use a library like TCPDF or mPDF
        // For now, return download URL for HTML
        $filename = "sop_{$sopId}_" . time() . ".html";
        $filepath = UPLOAD_PATH . '/' . $filename;

        file_put_contents($filepath, $html);

        // Save export record
        saveExportRecord($sopId, $userId, 'pdf', $filename, filesize($filepath));

        Response::success('PDF export created', [
            'downloadUrl' => BASE_URL . '/uploads/' . $filename,
            'filename' => $filename
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to export PDF: ' . $e->getMessage());
    }
}

/**
 * Export SOP as HTML
 */
function exportHTML($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    if (empty($body['sopId'])) {
        Response::validationError(['sopId' => 'SOP ID is required']);
    }

    $sopId = $body['sopId'];

    try {
        // Get SOP data
        list($sop, $steps) = getSOPForExport($sopId, $userId);

        // Generate HTML content
        $html = generateExportHTML($sop, $steps);

        $filename = "sop_{$sopId}_" . time() . ".html";
        $filepath = UPLOAD_PATH . '/' . $filename;

        file_put_contents($filepath, $html);

        // Save export record
        saveExportRecord($sopId, $userId, 'html', $filename, filesize($filepath));

        Response::success('HTML export created', [
            'downloadUrl' => BASE_URL . '/uploads/' . $filename,
            'filename' => $filename
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to export HTML: ' . $e->getMessage());
    }
}

/**
 * Export SOP as Markdown
 */
function exportMarkdown($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    if (empty($body['sopId'])) {
        Response::validationError(['sopId' => 'SOP ID is required']);
    }

    $sopId = $body['sopId'];

    try {
        // Get SOP data
        list($sop, $steps) = getSOPForExport($sopId, $userId);

        // Generate Markdown content
        $markdown = generateMarkdown($sop, $steps);

        $filename = "sop_{$sopId}_" . time() . ".md";
        $filepath = UPLOAD_PATH . '/' . $filename;

        file_put_contents($filepath, $markdown);

        // Save export record
        saveExportRecord($sopId, $userId, 'markdown', $filename, filesize($filepath));

        Response::success('Markdown export created', [
            'downloadUrl' => BASE_URL . '/uploads/' . $filename,
            'filename' => $filename
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to export Markdown: ' . $e->getMessage());
    }
}

/**
 * Get SOP data for export
 */
function getSOPForExport($sopId, $userId) {
    $db = Database::getInstance()->getConnection();

    // Verify ownership
    $stmt = $db->prepare("SELECT * FROM sops WHERE sop_id = ?");
    $stmt->execute([$sopId]);
    $sop = $stmt->fetch();

    if (!$sop) {
        Response::notFound('SOP not found');
    }

    if ($sop['user_id'] != $userId) {
        Response::forbidden('You do not have permission to export this SOP');
    }

    // Get steps
    $stmt = $db->prepare("
        SELECT * FROM steps
        WHERE sop_id = ?
        ORDER BY step_number ASC
    ");
    $stmt->execute([$sopId]);
    $steps = $stmt->fetchAll();

    return [$sop, $steps];
}

/**
 * Generate HTML for export
 */
function generateExportHTML($sop, $steps) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($sop['title']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
            h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
            .description { color: #666; margin-bottom: 30px; }
            .step { margin-bottom: 40px; page-break-inside: avoid; }
            .step-number { background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; }
            .step-content { margin-top: 15px; }
            .step-image { max-width: 100%; border: 1px solid #ddd; margin: 15px 0; }
            .tips { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 10px; }
            .footer { margin-top: 50px; text-align: center; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($sop['title']); ?></h1>
        <p class="description"><?php echo htmlspecialchars($sop['description']); ?></p>

        <?php foreach ($steps as $step): ?>
            <div class="step">
                <span class="step-number">Step <?php echo $step['step_number']; ?></span>
                <div class="step-content">
                    <p><strong><?php echo htmlspecialchars($step['ai_description'] ?? 'No description'); ?></strong></p>

                    <?php if ($step['screenshot_path']): ?>
                        <img class="step-image" src="<?php echo BASE_URL . $step['screenshot_path']; ?>" alt="Step <?php echo $step['step_number']; ?>">
                    <?php endif; ?>

                    <?php if ($step['tips']): ?>
                        <div class="tips">
                            <strong>Tip:</strong> <?php echo htmlspecialchars($step['tips']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="footer">
            <p>Generated by SOP Recorder on <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Generate Markdown
 */
function generateMarkdown($sop, $steps) {
    $md = "# " . $sop['title'] . "\n\n";
    $md .= $sop['description'] . "\n\n";
    $md .= "---\n\n";

    foreach ($steps as $step) {
        $md .= "## Step " . $step['step_number'] . "\n\n";
        $md .= ($step['ai_description'] ?? 'No description') . "\n\n";

        if ($step['screenshot_path']) {
            $md .= "![Step " . $step['step_number'] . "](" . BASE_URL . $step['screenshot_path'] . ")\n\n";
        }

        if ($step['tips']) {
            $md .= "> **Tip:** " . $step['tips'] . "\n\n";
        }
    }

    $md .= "---\n\n";
    $md .= "*Generated by SOP Recorder on " . date('Y-m-d H:i:s') . "*\n";

    return $md;
}

/**
 * Save export record
 */
function saveExportRecord($sopId, $userId, $format, $filename, $filesize) {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO exports (sop_id, user_id, format, file_path, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$sopId, $userId, $format, '/uploads/' . $filename, $filesize]);

    } catch (Exception $e) {
        // Silent fail
        error_log('Failed to save export record: ' . $e->getMessage());
    }
}
