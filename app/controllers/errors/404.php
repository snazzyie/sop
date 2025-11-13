<?php
// 404 Error Page

$page_title = '404 - Page Not Found';

// If logged in, show full layout
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] > 0) {
    $menu = fn_core_menu_main();
    require BASE_PATH . 'views/errors/404.php';
} else {
    // Show minimal error page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page Not Found</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: #f8f9fa;
            }
            .error-container {
                text-align: center;
                padding: 40px;
            }
            h1 {
                font-size: 72px;
                margin: 0;
                color: #667eea;
            }
            p {
                font-size: 18px;
                color: #666;
            }
            a {
                color: #667eea;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>404</h1>
            <p>Page not found</p>
            <p><a href="/login">← Back to Login</a></p>
        </div>
    </body>
    </html>
    <?php
}
