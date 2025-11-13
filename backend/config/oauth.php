<?php
// OAuth Configuration

// Google OAuth (Get from https://console.cloud.google.com/apis/credentials)
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', BASE_URL . '/api/auth/google/callback');

// GitHub OAuth (Optional - Get from https://github.com/settings/developers)
define('GITHUB_CLIENT_ID', 'YOUR_GITHUB_CLIENT_ID');
define('GITHUB_CLIENT_SECRET', 'YOUR_GITHUB_CLIENT_SECRET');
define('GITHUB_REDIRECT_URI', BASE_URL . '/api/auth/github/callback');

// OAuth Settings
define('OAUTH_STATE_LIFETIME', 600); // 10 minutes

class GoogleOAuth {

    /**
     * Get Google OAuth authorization URL
     */
    public static function getAuthorizationUrl() {
        $state = bin2hex(random_bytes(16));

        // Store state in session for verification
        session_start();
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_state_time'] = time();

        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public static function exchangeCodeForToken($code) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $params = [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to exchange code for token');
        }

        return json_decode($response, true);
    }

    /**
     * Get user info from Google
     */
    public static function getUserInfo($accessToken) {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $ch = curl_init($userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get user info');
        }

        return json_decode($response, true);
    }

    /**
     * Verify OAuth state
     */
    public static function verifyState($state) {
        session_start();

        if (!isset($_SESSION['oauth_state']) || !isset($_SESSION['oauth_state_time'])) {
            return false;
        }

        // Check if state matches
        if ($_SESSION['oauth_state'] !== $state) {
            return false;
        }

        // Check if state is still valid (not expired)
        if (time() - $_SESSION['oauth_state_time'] > OAUTH_STATE_LIFETIME) {
            return false;
        }

        // Clear state
        unset($_SESSION['oauth_state']);
        unset($_SESSION['oauth_state_time']);

        return true;
    }
}
