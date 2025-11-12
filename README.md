# SOP Recorder - Automatic Process Documentation System

> **Record browser actions once, automatically generate step-by-step Standard Operating Procedures (SOPs) with screenshots and AI-generated descriptions.**

Similar to Scribe, this system captures your screen interactions and converts them into polished, shareable documentation.

---

## 🎯 Features

### Core Functionality
- **🎥 Browser Recording** - Chrome extension captures clicks, inputs, navigation, and form submissions
- **📸 Automatic Screenshots** - Every action is captured with annotated screenshots
- **🤖 AI Descriptions** - Generate natural language instructions for each step
- **✏️ Edit & Customize** - Edit text, reorder steps, blur sensitive data, add tips
- **📤 Multiple Export Formats** - PDF, HTML, Markdown
- **🔗 Easy Sharing** - Share via link (public, password-protected, or private)
- **👥 Team Collaboration** - Share SOPs with team members (planned)

---

## 🏗️ Architecture

```
sop-recorder/
├── chrome-extension/     # Browser extension for recording
│   ├── manifest.json
│   ├── scripts/
│   │   ├── background.js    # Service worker
│   │   └── content.js       # Page interaction capture
│   ├── popup/
│   │   ├── popup.html
│   │   ├── popup.css
│   │   └── popup.js
│   └── styles/
│
├── backend/             # PHP/MySQL API
│   ├── api/
│   │   ├── index.php        # Main router
│   │   ├── auth.php         # Authentication
│   │   ├── sessions.php     # Recording sessions
│   │   ├── sops.php         # SOP management
│   │   ├── steps.php        # Step editing
│   │   ├── shares.php       # Sharing
│   │   └── exports.php      # Export functionality
│   ├── config/
│   │   ├── config.php
│   │   └── database.php
│   ├── classes/
│   │   ├── JWT.php
│   │   └── Response.php
│   └── uploads/
│
├── web-app/             # Frontend web application
│   ├── index.html           # Dashboard
│   ├── pages/
│   │   ├── login.html       # Authentication
│   │   └── view.html        # SOP viewer
│   ├── css/
│   │   └── styles.css
│   └── js/
│       ├── auth.js
│       ├── auth-page.js
│       ├── dashboard.js
│       └── view.js
│
└── database/
    └── schema.sql           # Database structure
```

---

## 🚀 Installation

### Prerequisites
- PHP 7.4+ with PDO extension
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) or PHP built-in server
- Chrome browser (for extension)

### Step 1: Database Setup

1. Create the database:
```sql
CREATE DATABASE sop_recorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:
```bash
mysql -u root -p sop_recorder < database/schema.sql
```

3. Update database credentials in `backend/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sop_recorder');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Step 2: Backend Setup

1. Configure the backend in `backend/config/config.php`:
```php
define('BASE_URL', 'http://localhost:8000');  // Change to your URL
define('JWT_SECRET', 'change-this-to-random-string');
```

2. Ensure upload directories exist and are writable:
```bash
chmod 777 backend/uploads
chmod 777 backend/uploads/sessions
```

3. Start the PHP server:
```bash
cd backend
php -S localhost:8000
```

Or configure Apache/Nginx to serve from the `backend/api/` directory.

### Step 3: Web App Setup

1. Update API URL in web app JavaScript files:
   - `web-app/js/auth.js`
   - `web-app/js/auth-page.js`

Change `API_BASE_URL` to match your backend URL:
```javascript
const API_BASE_URL = 'http://localhost:8000/api';
```

2. Serve the web app:
```bash
cd web-app
php -S localhost:8080
```

Or use any static file server.

### Step 4: Chrome Extension Setup

1. Update the API URL in `chrome-extension/scripts/background.js`:
```javascript
const API_BASE_URL = 'http://localhost:8000/api';
```

2. Load the extension in Chrome:
   - Open Chrome and go to `chrome://extensions/`
   - Enable "Developer mode" (top right)
   - Click "Load unpacked"
   - Select the `chrome-extension` folder

3. The extension icon should appear in your toolbar!

---

## 📖 Usage Guide

### 1. Create an Account

1. Open the web app at `http://localhost:8080/pages/login.html`
2. Click "Sign up" and create your account
3. Login with your credentials

**Test Account** (pre-created):
- Email: `test@example.com`
- Password: `password123`

### 2. Start Recording

1. Click the SOP Recorder extension icon in Chrome
2. Login if not already authenticated
3. Configure recording options:
   - ✅ Privacy mode (blur sensitive data)
   - ✅ Current tab only
   - ☐ Capture keystrokes (optional)
4. Click **"Start Recording"**
5. A red recording indicator will appear on the page

### 3. Perform Your Process

Navigate through your process as you normally would:
- **Clicks** on buttons, links, menus → Captured as steps
- **Text inputs** in forms → Captured with descriptions
- **Dropdown selections** → Recorded automatically
- **Navigation** to new pages → Logged as steps
- **Form submissions** → Captured

Each action is:
- ✅ Logged with metadata
- ✅ Screenshot automatically
- ✅ Element highlighted in screenshot
- ✅ Sent to backend for processing

### 4. Stop Recording

1. Click the extension icon
2. Click **"Stop Recording"**
3. The system will:
   - Finalize the session
   - Generate AI descriptions (if configured)
   - Create a draft SOP
   - Provide a link to view/edit

### 5. View & Edit Your SOP

1. Click **"View & Edit SOP"** or go to the dashboard
2. Your new SOP appears in the list
3. Click to open the SOP viewer
4. You'll see:
   - Title and description
   - All steps in order
   - Annotated screenshots
   - Action descriptions

**Editing Options** (planned):
- Edit step descriptions
- Reorder steps (drag & drop)
- Delete unnecessary steps
- Add tips and notes
- Blur sensitive information in screenshots
- Customize branding (logo, colors)

### 6. Share Your SOP

1. Open an SOP
2. Click **"Share"** button
3. Choose visibility:
   - **Anyone with link** - Public access
   - **Password protected** - Requires password
   - **Private** - Only you can view
4. Optionally set expiration date
5. Copy the share link

### 7. Export Your SOP

1. Open an SOP
2. Click **"Export"** button
3. Choose format:
   - **📄 PDF** - Printable document
   - **🌐 HTML** - Standalone web page
   - **📝 Markdown** - Text format with images
4. Download the file

---

## 🔧 Configuration

### Backend Configuration (`backend/config/config.php`)

```php
// Environment
define('ENV', 'development'); // 'development' or 'production'

// URLs
define('BASE_URL', 'http://localhost:8000');

// JWT Settings
define('JWT_SECRET', 'your-secret-key');
define('JWT_EXPIRATION', 86400); // 24 hours

// File Upload
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
```

### Extension Configuration

Update `chrome-extension/scripts/background.js`:
```javascript
const API_BASE_URL = 'http://your-domain.com/api';
```

### Web App Configuration

Update all JavaScript files in `web-app/js/`:
```javascript
const API_BASE_URL = 'http://your-domain.com/api';
```

---

## 🗄️ Database Schema

### Core Tables

- **users** - User accounts
- **sessions** - Recording sessions
- **sops** - Standard Operating Procedures
- **steps** - Individual steps in SOPs
- **shares** - Share links and permissions
- **exports** - Export history
- **teams** - Team management (planned)
- **team_members** - Team membership (planned)
- **sop_permissions** - Access control (planned)
- **sop_revisions** - Version history
- **comments** - Collaboration (planned)
- **sop_analytics** - View tracking

---

## 🔌 API Endpoints

### Authentication
```
POST /api/auth/login           - Login user
POST /api/auth/register        - Register new user
POST /api/auth/logout          - Logout user
GET  /api/auth/me              - Get current user info
```

### Recording Sessions
```
POST /api/sessions/create              - Create new recording session
GET  /api/sessions/:sessionId          - Get session info
POST /api/sessions/:sessionId/steps    - Add steps to session
POST /api/sessions/:sessionId/finalize - Finalize session and create SOP
```

### SOPs Management
```
GET    /api/sops/list          - List all SOPs
GET    /api/sops/:sopId        - View single SOP with steps
PUT    /api/sops/:sopId        - Update SOP
DELETE /api/sops/:sopId        - Delete SOP
```

### Steps Management
```
GET    /api/steps/:stepId      - Get single step
PUT    /api/steps/:stepId      - Update step
DELETE /api/steps/:stepId      - Delete step
```

### Sharing
```
POST /api/shares/create        - Create share link
GET  /api/shares/:token        - View shared SOP (public access)
```

### Exports
```
POST /api/exports/pdf          - Export SOP as PDF
POST /api/exports/html         - Export SOP as HTML
POST /api/exports/markdown     - Export SOP as Markdown
```

---

## 🎨 Customization

### Adding AI Integration

Currently, the system has placeholders for AI-generated descriptions. To integrate AI:

1. **OpenAI GPT Integration**:

Edit `backend/api/sessions.php` and add:
```php
function generateAIDescription($step) {
    $apiKey = 'your-openai-api-key';
    $endpoint = 'https://api.openai.com/v1/chat/completions';

    $prompt = "Generate a clear instruction for this action:\n"
            . "Action: {$step['actionType']}\n"
            . "Element: {$step['element']['text']}\n"
            . "Page: {$step['page']['title']}";

    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
}
```

2. Call this function when processing steps in `addSessionSteps()`

### Custom Branding

Add logo and colors to SOP:
```javascript
// Update SOP with branding
await apiRequest(`/sops/${sopId}`, {
    method: 'PUT',
    body: JSON.stringify({
        branding: {
            logo: 'https://your-logo-url.com/logo.png',
            primaryColor: '#667eea',
            secondaryColor: '#764ba2'
        }
    })
});
```

---

## 🐛 Troubleshooting

### Extension Not Loading
- Ensure Chrome extensions developer mode is enabled
- Check for errors in `chrome://extensions/` page
- Verify `manifest.json` is valid JSON

### API Connection Errors
- Check CORS settings in `backend/api/index.php`
- Verify `API_BASE_URL` is correct in all files
- Ensure PHP server is running
- Check browser console for errors

### Screenshots Not Saving
- Verify `backend/uploads/sessions/` directory exists
- Check directory permissions (777 or appropriate)
- Ensure base64 decoding is working

### Database Errors
- Verify database credentials in `backend/config/database.php`
- Check that schema is imported correctly
- Ensure MySQL user has proper permissions

### Login Issues
- Clear browser localStorage
- Check JWT token generation
- Verify password hashing works (`password_verify()`)

---

## 🚧 Planned Features

- [ ] **Step Editing UI** - Inline editing of steps
- [ ] **Drag & Drop Reordering** - Reorder steps visually
- [ ] **Image Redaction Tool** - Canvas-based blur tool
- [ ] **Team Collaboration** - Share with teams, permissions
- [ ] **Comments** - Collaborate on SOPs
- [ ] **Version History** - Track changes over time
- [ ] **Templates** - Create SOP templates
- [ ] **Multi-page SOPs** - Combine multiple recordings
- [ ] **Video Recording** - Optional screen recording
- [ ] **Desktop App** - Electron app for non-web processes
- [ ] **Mobile Viewer** - View SOPs on mobile
- [ ] **Integrations** - Confluence, Notion, Slack

---

## 📝 Development

### Project Structure

```
Chrome Extension → Captures Actions → Background Service Worker
                                            ↓
                                    PHP API Backend
                                            ↓
                                    MySQL Database
                                            ↓
                                    Web App Frontend
```

### Tech Stack

**Frontend:**
- Vanilla JavaScript (ES6+)
- HTML5 / CSS3
- Chrome Extension API (Manifest V3)

**Backend:**
- PHP 7.4+
- MySQL 8.0
- JWT Authentication
- REST API

**Future:**
- AI: OpenAI GPT / Claude API
- PDF: TCPDF or mPDF
- Queue: Redis for background jobs

---

## 🤝 Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

## 📄 License

This project is open-source and available under the MIT License.

---

## 🙏 Acknowledgments

Inspired by [Scribe](https://scribehow.com/) - the original automatic documentation tool.

---

## 📧 Support

For issues or questions:
- Check the Troubleshooting section above
- Review API responses in browser console
- Check PHP error logs
- Open an issue on GitHub

---

**Built with ❤️ for making documentation effortless.**
