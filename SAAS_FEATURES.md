# 💳 SaaS Features - Stripe Payments, OAuth & External API

This document covers all the SaaS features added to the SOP Recorder platform.

---

## 🎯 What's New

Your SOP Recorder is now a **full-featured SaaS platform** with:

1. **💰 Stripe Payment Integration** - Subscriptions at team level
2. **📊 Subscription Plans** - Free, Pro, Enterprise with feature gates
3. **🔐 Google OAuth** - Social login for easy onboarding
4. **🔑 External API** - Allow other apps to access SOPs via API keys
5. **📈 Usage Tracking** - Monitor and limit usage based on plan
6. **⚡ Feature Restrictions** - Automatically enforce plan limits

---

## 📦 What Was Added

### Backend Components (9 new files)

1. **database/saas_migration.sql** - Enhanced schema with:
   - `subscription_plans` - Define pricing tiers
   - `team_subscriptions` - Track team subscriptions
   - `payment_history` - Log all payments
   - `oauth_providers` - Store OAuth connections
   - `api_keys` - External API access
   - `usage_tracking` - Monitor resource usage
   - `stripe_webhook_events` - Process Stripe events

2. **backend/config/stripe.php** - Stripe integration helper
3. **backend/config/oauth.php** - Google OAuth helper
4. **backend/classes/Subscription.php** - Subscription management logic
5. **backend/api/subscriptions.php** - Subscription endpoints
6. **backend/api/oauth.php** - OAuth authentication endpoints
7. **backend/api/external.php** - External API for third-party apps
8. **backend/api/api-keys.php** - API key management

### Frontend Components (3 new files)

1. **web-app/pages/pricing.html** - Beautiful pricing page
2. **web-app/pages/oauth-callback.html** - OAuth redirect handler
3. **web-app/js/pricing.js** - Pricing page logic

### Updated Files

- **backend/api/index.php** - Added new routes
- **web-app/pages/login.html** - Added Google OAuth button
- **web-app/js/auth-page.js** - OAuth integration

---

## 🚀 Quick Setup Guide

### Step 1: Install Stripe PHP Library

```bash
cd backend
composer require stripe/stripe-php
```

If you don't have Composer:
```bash
# Download Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Install Stripe
php composer.phar require stripe/stripe-php
```

### Step 2: Run Database Migration

```bash
mysql -u root -p sop_recorder < database/saas_migration.sql
```

This adds:
- 7 new tables
- 3 default subscription plans (Free, Pro, Enterprise)
- Updates to existing tables

### Step 3: Configure Stripe

1. **Create Stripe Account**: https://dashboard.stripe.com/register

2. **Get API Keys**: https://dashboard.stripe.com/apikeys

3. **Update `backend/config/stripe.php`**:
```php
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY_HERE');
```

4. **Create Products in Stripe Dashboard**:
   - Go to Products → Add Product
   - Create "Pro" plan: $29/month, $290/year
   - Create "Enterprise" plan: $99/month, $990/year
   - Copy Price IDs and update in `stripe.php`

5. **Set Up Webhook**:
   - Go to Developers → Webhooks
   - Add endpoint: `https://your-domain.com/api/subscriptions/webhook`
   - Select events: `checkout.session.completed`, `customer.subscription.*`, `invoice.*`
   - Copy webhook secret to `stripe.php`

### Step 4: Configure Google OAuth

1. **Create Google Cloud Project**: https://console.cloud.google.com/

2. **Enable Google+ API**:
   - APIs & Services → Enable APIs → Google+ API

3. **Create OAuth Credentials**:
   - APIs & Services → Credentials → Create Credentials → OAuth Client ID
   - Application type: Web application
   - Authorized redirect URIs: `http://localhost:8000/api/oauth/google/callback`

4. **Update `backend/config/oauth.php`**:
```php
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
```

### Step 5: Test It!

1. **Start servers**:
```bash
cd backend && php -S localhost:8000 &
cd web-app && php -S localhost:8080 &
```

2. **Test Pricing Page**: http://localhost:8080/pages/pricing.html

3. **Test Google OAuth**: Click "Sign in with Google" on login page

---

## 💰 Subscription Plans

### Free Plan ($0/month)
- 5 SOPs per month
- 50 steps per SOP
- 1 team member
- 100 API calls per day
- 100 MB storage
- Export to HTML/Markdown
- Community support

### Pro Plan ($29/month or $290/year)
- ✅ Unlimited SOPs
- ✅ Unlimited steps
- ✅ 10 team members
- ✅ 10,000 API calls per day
- ✅ 10 GB storage
- ✅ Export to PDF
- ✅ Custom branding
- ✅ API access
- ✅ Version history
- ✅ Priority support

### Enterprise Plan ($99/month or $990/year)
- ✅ Everything in Pro
- ✅ Unlimited team members
- ✅ Unlimited API calls
- ✅ Unlimited storage
- ✅ Advanced analytics
- ✅ SSO integration (ready)
- ✅ Dedicated support
- ✅ SLA guarantee
- ✅ Custom integrations

---

## 🔌 API Endpoints Reference

### Subscription Endpoints

```
GET  /api/subscriptions/plans        - List available plans
GET  /api/subscriptions/current      - Get current subscription
POST /api/subscriptions/checkout     - Create checkout session
POST /api/subscriptions/portal       - Create billing portal session
POST /api/subscriptions/cancel       - Cancel subscription
GET  /api/subscriptions/usage        - Get usage statistics
POST /api/subscriptions/webhook      - Stripe webhook handler
```

### OAuth Endpoints

```
GET  /api/oauth/google/login         - Initiate Google OAuth
GET  /api/oauth/google/callback      - Handle OAuth callback
```

### API Key Management

```
GET    /api/api-keys/list            - List API keys
POST   /api/api-keys/create          - Create new API key
POST   /api/api-keys/revoke/:id      - Revoke API key
DELETE /api/api-keys/delete/:id      - Delete API key
```

### External API (requires API key)

```
GET /api/external/sops               - List published SOPs
GET /api/external/sops/:id           - Get single SOP
GET /api/external/steps/:sopId       - Get steps for SOP
```

**Authentication**: Add header `X-API-Key: your_api_key`

---

## 🔑 Using the External API

### Create API Key

1. Login to web app
2. Go to Settings → API Keys (coming soon) or use API:

```bash
curl -X POST http://localhost:8000/api/api-keys/create \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key_name": "My App Integration",
    "permissions": ["read_sops"],
    "expires_at": null
  }'
```

Response:
```json
{
  "success": true,
  "data": {
    "api_key_id": 1,
    "api_key": "sk_abc123...",
    "api_secret": "secret_xyz789...",
    "message": "Save this API key and secret securely. You won't be able to see the secret again!"
  }
}
```

### Access External API

```bash
# List SOPs
curl http://localhost:8000/api/external/sops \
  -H "X-API-Key: sk_abc123..."

# Get single SOP
curl http://localhost:8000/api/external/sops/1 \
  -H "X-API-Key: sk_abc123..."

# Get steps
curl http://localhost:8000/api/external/steps/1 \
  -H "X-API-Key: sk_abc123..."
```

### API Response Format

```json
{
  "success": true,
  "message": "SOPs retrieved",
  "data": {
    "sops": [
      {
        "sop_id": 1,
        "title": "How to Submit Contact Form",
        "description": "Step-by-step guide...",
        "status": "published",
        "total_steps": 8,
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 50,
      "total_pages": 3
    }
  }
}
```

---

## 🛡️ Feature Restrictions

The system automatically enforces limits based on subscription plan:

### Checking Limits in Your Code

```php
// Check if action is allowed
if (!Subscription::canPerformAction($teamId, 'create_sop')) {
    Response::error('You have reached your SOP limit. Upgrade to create more.', 403);
}

// Check specific limit
$check = Subscription::checkLimit($teamId, 'sops_per_month', $currentCount);
if (!$check['allowed']) {
    Response::error('Limit exceeded: ' . $check['current'] . '/' . $check['limit'], 403);
}

// Track usage
Subscription::trackUsage($teamId, 'sops_created', 1);
```

### Features Restricted by Plan

| Feature | Free | Pro | Enterprise |
|---------|------|-----|------------|
| Create SOPs | 5/month | Unlimited | Unlimited |
| Team Members | 1 | 10 | Unlimited |
| API Access | ❌ | ✅ | ✅ |
| PDF Export | ❌ | ✅ | ✅ |
| Custom Branding | ❌ | ✅ | ✅ |
| API Calls | 100/day | 10,000/day | Unlimited |

---

## 💳 Payment Flow

### Upgrade Flow

1. User clicks "Upgrade" on pricing page
2. Frontend calls `POST /api/subscriptions/checkout`
3. Backend creates Stripe Checkout Session
4. User redirected to Stripe payment page
5. User enters payment details
6. Stripe processes payment
7. Webhook received at `/api/subscriptions/webhook`
8. Subscription activated in database
9. User redirected back to app
10. Features immediately available

### Cancel Flow

1. User clicks "Cancel Subscription"
2. Frontend calls `POST /api/subscriptions/cancel`
3. Backend calls Stripe API to cancel
4. Subscription marked for cancellation
5. User retains access until period end
6. At period end, webhook received
7. Subscription status updated to "canceled"
8. User reverts to Free plan

---

## 📊 Usage Tracking

### Tracked Metrics

- `sops_created` - Number of SOPs created this month
- `api_calls` - Number of API requests this period
- `storage_mb` - Storage used in megabytes

### View Usage

```javascript
// In web app
const data = await apiRequest('/subscriptions/usage');

console.log(data.usage);
/*
{
  sops_created: {
    current: 3,
    limit: 5,
    percentage: 60
  },
  api_calls: {
    current: 45,
    limit: 100,
    percentage: 45
  }
}
*/
```

---

## 🔐 Google OAuth Flow

### How It Works

1. User clicks "Sign in with Google"
2. Redirected to `GET /api/oauth/google/login`
3. Backend generates OAuth URL with state
4. User redirected to Google consent screen
5. User grants permission
6. Google redirects to `GET /api/oauth/google/callback?code=...`
7. Backend exchanges code for access token
8. Backend fetches user info from Google
9. If user exists, login. If new, create account.
10. JWT token generated
11. User redirected to `/pages/oauth-callback.html` with token
12. Frontend saves token to localStorage
13. User redirected to dashboard

### Security Features

- **State Parameter**: Prevents CSRF attacks
- **Token Expiration**: OAuth tokens have 10-minute lifetime
- **Secure Storage**: Refresh tokens stored encrypted
- **Email Verification**: Google-verified emails trusted

---

## 🎨 Frontend Integration Examples

### Check User's Plan

```javascript
async function checkPlan() {
    const data = await apiRequest('/subscriptions/current');
    const plan = data.data.subscription.plan_slug;

    if (plan === 'free') {
        // Show upgrade prompt
        showUpgradeModal();
    } else if (plan === 'pro') {
        // Enable Pro features
        enablePDFExport();
        enableCustomBranding();
    }
}
```

### Enforce Limits

```javascript
async function checkSOPLimit() {
    const data = await apiRequest('/subscriptions/usage');
    const sops = data.data.usage.sops_created;

    if (sops.current >= sops.limit && sops.limit !== -1) {
        alert(`You've reached your limit of ${sops.limit} SOPs. Upgrade to create more!`);
        return false;
    }

    return true;
}

// Before creating SOP
if (await checkSOPLimit()) {
    createSOP();
} else {
    showPricingPage();
}
```

### Create Checkout

```javascript
async function upgradeToPro() {
    const data = await apiRequest('/subscriptions/checkout', {
        method: 'POST',
        body: JSON.stringify({
            plan_slug: 'pro',
            billing_cycle: 'monthly'
        })
    });

    // Redirect to Stripe
    window.location.href = data.data.url;
}
```

---

## 🧪 Testing Stripe Integration

### Test Mode

Stripe provides test cards for development:

**Successful Payment:**
- Card: `4242 4242 4242 4242`
- Expiry: Any future date
- CVC: Any 3 digits
- ZIP: Any 5 digits

**Declined Payment:**
- Card: `4000 0000 0000 0002`

**Requires Authentication:**
- Card: `4000 0027 6000 3184`

### Testing Webhooks Locally

Use Stripe CLI to forward webhooks:

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks
stripe listen --forward-to localhost:8000/api/subscriptions/webhook

# Trigger test event
stripe trigger checkout.session.completed
```

---

## 🔄 Migration Guide

### Migrating Existing Users

If you already have users, run this to create teams and assign free plans:

```sql
-- Create team for each existing user
INSERT INTO teams (name, owner_user_id)
SELECT CONCAT(name, '''s Team'), user_id
FROM users
WHERE NOT EXISTS (
    SELECT 1 FROM teams WHERE owner_user_id = users.user_id
);

-- Assign free plan to all teams without subscription
INSERT INTO team_subscriptions (team_id, plan_id, status)
SELECT t.team_id, p.plan_id, 'active'
FROM teams t
CROSS JOIN subscription_plans p
WHERE p.plan_slug = 'free'
AND NOT EXISTS (
    SELECT 1 FROM team_subscriptions WHERE team_id = t.team_id
);
```

---

## 📈 Analytics & Reporting

### Revenue Metrics

```sql
-- Monthly recurring revenue
SELECT
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as subscriptions,
    SUM(CASE WHEN billing_cycle = 'monthly' THEN 29 WHEN billing_cycle = 'yearly' THEN 290/12 END) as mrr
FROM team_subscriptions ts
JOIN subscription_plans sp ON ts.plan_id = sp.plan_id
WHERE status = 'active' AND sp.plan_slug IN ('pro', 'enterprise')
GROUP BY month;
```

### User Growth

```sql
-- New signups by week
SELECT
    DATE_FORMAT(created_at, '%Y-%u') as week,
    COUNT(*) as signups,
    COUNT(DISTINCT CASE WHEN oauth_provider IS NOT NULL THEN user_id END) as oauth_signups
FROM users
GROUP BY week
ORDER BY week DESC
LIMIT 12;
```

### Conversion Funnel

```sql
-- Free to paid conversion
SELECT
    (SELECT COUNT(*) FROM team_subscriptions WHERE plan_id = (SELECT plan_id FROM subscription_plans WHERE plan_slug = 'free')) as free_users,
    (SELECT COUNT(*) FROM team_subscriptions WHERE plan_id IN (SELECT plan_id FROM subscription_plans WHERE plan_slug IN ('pro', 'enterprise')) AND status = 'active') as paid_users,
    ROUND((SELECT COUNT(*) FROM team_subscriptions WHERE plan_id IN (SELECT plan_id FROM subscription_plans WHERE plan_slug IN ('pro', 'enterprise')) AND status = 'active') * 100.0 / (SELECT COUNT(*) FROM team_subscriptions), 2) as conversion_rate;
```

---

## 🚨 Important Notes

### Security

1. **Never expose Stripe secret keys** in frontend code
2. **Validate webhook signatures** - already implemented
3. **Use HTTPS in production** - required by Stripe
4. **Rotate API keys regularly** - implement expiration

### Testing

1. **Always test webhooks** before going live
2. **Test all payment scenarios** - success, failure, cancellation
3. **Test upgrade/downgrade flows**
4. **Verify feature restrictions** work correctly

### Production Checklist

- [ ] Replace test Stripe keys with live keys
- [ ] Set up production webhook endpoint
- [ ] Configure Google OAuth production credentials
- [ ] Enable HTTPS
- [ ] Set up monitoring for failed payments
- [ ] Configure email notifications for subscriptions
- [ ] Test all payment flows end-to-end
- [ ] Document API for customers
- [ ] Set up customer support system

---

## 💡 Next Steps

### Recommended Enhancements

1. **Email Notifications**
   - Payment successful
   - Payment failed
   - Subscription ending
   - Limit approaching

2. **Admin Dashboard**
   - View all subscriptions
   - Manually upgrade users
   - View revenue analytics
   - Export reports

3. **Customer Portal**
   - View invoices
   - Download receipts
   - Update payment method
   - View usage stats

4. **Additional OAuth Providers**
   - GitHub
   - Microsoft
   - Apple

5. **Enterprise Features**
   - SAML SSO
   - SCIM provisioning
   - Audit logs
   - Custom contracts

---

## 📚 Resources

- **Stripe Documentation**: https://stripe.com/docs/api
- **Google OAuth Guide**: https://developers.google.com/identity/protocols/oauth2
- **Stripe Testing**: https://stripe.com/docs/testing
- **Webhook Best Practices**: https://stripe.com/docs/webhooks/best-practices

---

**You now have a production-ready SaaS platform!** 🎉

All code is committed and ready to deploy. Configure your Stripe and Google credentials, and you're ready to start accepting payments!
