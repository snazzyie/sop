# Quick Setup Guide - SOP Recorder

## 🚀 5-Minute Setup

### Step 1: Database (2 minutes)

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE sop_recorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p sop_recorder < database/schema.sql

# Verify
mysql -u root -p sop_recorder -e "SHOW TABLES;"
```

Expected output: 13 tables listed

### Step 2: Configure Backend (1 minute)

Edit `backend/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sop_recorder');
define('DB_USER', 'root');          // Change this
define('DB_PASS', 'your_password'); // Change this
```

Edit `backend/config/config.php`:
```php
define('BASE_URL', 'http://localhost:8000');
define('JWT_SECRET', 'CHANGE-THIS-TO-RANDOM-STRING');
```

### Step 3: Start Servers (1 minute)

**Terminal 1 - Backend:**
```bash
cd backend
php -S localhost:8000
```

**Terminal 2 - Web App:**
```bash
cd web-app
php -S localhost:8080
```

### Step 4: Load Extension (1 minute)

1. Open Chrome: `chrome://extensions/`
2. Enable "Developer mode" (toggle top-right)
3. Click "Load unpacked"
4. Select the `chrome-extension` folder
5. Extension icon appears!

### Step 5: Test It!

1. **Login**: Open `http://localhost:8080/pages/login.html`
   - Use test account: `test@example.com` / `password123`
   - Or create a new account

2. **Start Recording**:
   - Click extension icon
   - Click "Start Recording"
   - Navigate to any website
   - Click around, fill forms
   - Click "Stop Recording"

3. **View SOP**:
   - Click "View & Edit SOP"
   - See your recorded steps!

---

## 🔧 Quick Configuration Checklist

Before starting:
- [ ] MySQL is running
- [ ] Database created and schema imported
- [ ] `backend/config/database.php` updated with your credentials
- [ ] `backend/uploads/sessions/` directory exists
- [ ] Directory permissions set (755 or 777 for uploads)
- [ ] Backend server running on port 8000
- [ ] Web app server running on port 8080
- [ ] Chrome extension loaded

---

## 🌐 Production Deployment

### Using Apache/Nginx

**Apache**:
1. Create virtual host pointing to `backend/` directory
2. Copy `.htaccess` file (already included)
3. Enable mod_rewrite: `sudo a2enmod rewrite`
4. Restart Apache: `sudo service apache2 restart`

**Nginx**:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/sop/backend;
    index index.php;

    location /api {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location /uploads {
        alias /path/to/sop/backend/uploads;
    }
}
```

### Update Configuration

1. **Backend** `backend/config/config.php`:
```php
define('ENV', 'production');
define('BASE_URL', 'https://your-domain.com');
define('JWT_SECRET', 'LONG-RANDOM-STRING-HERE');
```

2. **Extension** `chrome-extension/scripts/background.js`:
```javascript
const API_BASE_URL = 'https://your-domain.com/api';
```

3. **Web App** - All JS files in `web-app/js/`:
```javascript
const API_BASE_URL = 'https://your-domain.com/api';
```

### Security Hardening

1. **Change default test user password** in database:
```sql
UPDATE users SET password_hash = '$2y$10$...' WHERE email = 'test@example.com';
```

2. **Set proper file permissions**:
```bash
chmod 644 backend/config/*.php
chmod 755 backend/uploads
chmod 755 backend/uploads/sessions
```

3. **Enable HTTPS** using Let's Encrypt:
```bash
sudo certbot --apache -d your-domain.com
```

---

## 📱 Extension Distribution

### Chrome Web Store

1. Zip the extension:
```bash
cd chrome-extension
zip -r sop-recorder-extension.zip . -x "*.git*"
```

2. Go to [Chrome Developer Dashboard](https://chrome.google.com/webstore/devconsole)
3. Pay one-time $5 developer fee
4. Upload the zip file
5. Fill in store listing
6. Submit for review

### Private Distribution

1. Pack extension:
   - Go to `chrome://extensions/`
   - Click "Pack extension"
   - Select `chrome-extension` folder
   - Creates `.crx` file

2. Share the `.crx` file with your team

---

## 🧪 Testing

### Manual Test Checklist

**Extension:**
- [ ] Icon appears in Chrome toolbar
- [ ] Login works
- [ ] Recording starts/stops
- [ ] Steps captured correctly
- [ ] Screenshots taken
- [ ] Data sent to backend

**Backend API:**
- [ ] Login endpoint works
- [ ] Session creation works
- [ ] Steps are saved
- [ ] Images are saved
- [ ] SOPs are created

**Web App:**
- [ ] Login/register works
- [ ] Dashboard loads SOPs
- [ ] SOP viewer displays steps
- [ ] Share link generation works
- [ ] Export works

### Test URLs

```bash
# Backend health check
curl http://localhost:8000/api/

# Login test
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

# List SOPs (needs token)
curl http://localhost:8000/api/sops/list \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🐛 Common Issues

### "Database connection failed"
- Check MySQL is running: `sudo service mysql status`
- Verify credentials in `backend/config/database.php`
- Test connection: `mysql -u root -p`

### "Permission denied" on uploads
```bash
chmod 777 backend/uploads
chmod 777 backend/uploads/sessions
```

### Extension not capturing
- Reload extension in `chrome://extensions/`
- Check console for errors
- Verify API_BASE_URL is correct

### CORS errors
- Check `backend/api/index.php` has CORS headers
- Verify `.htaccess` is loaded (Apache)
- Check browser console for exact error

---

## 📊 Monitoring

### Check Database Size
```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = "sop_recorder"
ORDER BY (data_length + index_length) DESC;
```

### Check Upload Directory Size
```bash
du -sh backend/uploads/sessions/
```

### Monitor API Requests
```bash
tail -f /var/log/apache2/access.log | grep "/api/"
```

---

## 🔄 Updates

### Database Migrations

When schema changes:
```sql
-- Example migration
ALTER TABLE sops ADD COLUMN new_field VARCHAR(255) NULL;
```

### Backup Before Update
```bash
# Backup database
mysqldump -u root -p sop_recorder > backup_$(date +%Y%m%d).sql

# Backup uploads
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz backend/uploads/
```

---

## 💡 Tips

1. **Use test account for development**: Don't delete the pre-created test user
2. **Monitor disk space**: Screenshots can accumulate quickly
3. **Regular backups**: Backup database and uploads weekly
4. **Set upload limits**: Configure in `backend/config/config.php`
5. **Use environment variables**: For production secrets

---

## 🎯 Next Steps

After setup:
1. Create your first SOP
2. Test sharing functionality
3. Try different export formats
4. Invite team members
5. Customize branding

---

## 📞 Need Help?

- Read the full [README.md](README.md)
- Check browser console for errors
- Check PHP error logs
- Verify all configuration files

**Happy documenting! 🎉**
