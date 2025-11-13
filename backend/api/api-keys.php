<?php
// API Key Management endpoints

function handleApiKeysRequest($action, $param, $method, $body) {
    switch ($action) {
        case '':
        case 'list':
            if ($method === 'GET') {
                listApiKeys();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'create':
            if ($method === 'POST') {
                createApiKey($body);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'revoke':
            if ($method === 'POST' && $param) {
                revokeApiKey($param);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'delete':
            if ($method === 'DELETE' && $param) {
                deleteApiKey($param);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        default:
            Response::notFound('API key endpoint not found');
    }
}

/**
 * List API keys for user's team
 */
function listApiKeys() {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE owner_user_id = ?");
        $stmt->execute([$userId]);
        $team = $stmt->fetch();

        if (!$team) {
            Response::error('Team not found', 404);
        }

        // Check if user has API access
        if (!Subscription::canPerformAction($team['team_id'], 'api_access')) {
            Response::error('API access not available on your plan. Upgrade to Pro or Enterprise.', 403);
        }

        // Get API keys
        $stmt = $db->prepare("
            SELECT
                api_key_id,
                key_name,
                api_key,
                permissions,
                is_active,
                last_used_at,
                last_used_ip,
                expires_at,
                created_at
            FROM api_keys
            WHERE team_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$team['team_id']]);
        $keys = $stmt->fetchAll();

        foreach ($keys as &$key) {
            $key['permissions'] = json_decode($key['permissions'], true);
            // Mask the API key (show only first and last 4 characters)
            $key['api_key_masked'] = substr($key['api_key'], 0, 8) . '...' . substr($key['api_key'], -4);
            unset($key['api_secret']); // Never return the secret
        }

        Response::success('API keys retrieved', ['api_keys' => $keys]);

    } catch (Exception $e) {
        Response::serverError('Failed to list API keys: ' . $e->getMessage());
    }
}

/**
 * Create new API key
 */
function createApiKey($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    if (empty($body['key_name'])) {
        Response::validationError(['key_name' => 'Key name is required']);
    }

    $keyName = $body['key_name'];
    $permissions = $body['permissions'] ?? ['read_sops'];
    $expiresAt = $body['expires_at'] ?? null;

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE owner_user_id = ?");
        $stmt->execute([$userId]);
        $team = $stmt->fetch();

        if (!$team) {
            Response::error('Team not found', 404);
        }

        $teamId = $team['team_id'];

        // Check if user has API access
        if (!Subscription::canPerformAction($teamId, 'api_access')) {
            Response::error('API access not available on your plan. Upgrade to Pro or Enterprise.', 403);
        }

        // Generate API key and secret
        $apiKey = 'sk_' . bin2hex(random_bytes(24)); // 48 characters
        $apiSecret = bin2hex(random_bytes(32)); // 64 characters
        $apiSecretHash = password_hash($apiSecret, PASSWORD_DEFAULT);

        // Insert API key
        $stmt = $db->prepare("
            INSERT INTO api_keys (
                team_id, user_id, key_name, api_key, api_secret,
                permissions, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $teamId,
            $userId,
            $keyName,
            $apiKey,
            $apiSecretHash,
            json_encode($permissions),
            $expiresAt
        ]);

        $keyId = $db->lastInsertId();

        // IMPORTANT: Return the API key and secret only once
        Response::success('API key created successfully', [
            'api_key_id' => $keyId,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret, // Only shown once!
            'key_name' => $keyName,
            'permissions' => $permissions,
            'message' => 'Save this API key and secret securely. You won\'t be able to see the secret again!'
        ], 201);

    } catch (Exception $e) {
        Response::serverError('Failed to create API key: ' . $e->getMessage());
    }
}

/**
 * Revoke (deactivate) API key
 */
function revokeApiKey($keyId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("
            SELECT ak.team_id
            FROM api_keys ak
            JOIN teams t ON ak.team_id = t.team_id
            WHERE ak.api_key_id = ? AND t.owner_user_id = ?
        ");
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch();

        if (!$key) {
            Response::notFound('API key not found');
        }

        // Revoke key
        $stmt = $db->prepare("UPDATE api_keys SET is_active = FALSE WHERE api_key_id = ?");
        $stmt->execute([$keyId]);

        Response::success('API key revoked');

    } catch (Exception $e) {
        Response::serverError('Failed to revoke API key: ' . $e->getMessage());
    }
}

/**
 * Delete API key
 */
function deleteApiKey($keyId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("
            SELECT ak.team_id
            FROM api_keys ak
            JOIN teams t ON ak.team_id = t.team_id
            WHERE ak.api_key_id = ? AND t.owner_user_id = ?
        ");
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch();

        if (!$key) {
            Response::notFound('API key not found');
        }

        // Delete key
        $stmt = $db->prepare("DELETE FROM api_keys WHERE api_key_id = ?");
        $stmt->execute([$keyId]);

        Response::success('API key deleted');

    } catch (Exception $e) {
        Response::serverError('Failed to delete API key: ' . $e->getMessage());
    }
}
