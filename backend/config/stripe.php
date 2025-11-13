<?php
// Stripe Configuration

// Stripe API Keys (Get from https://dashboard.stripe.com/apikeys)
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE'); // Replace with your secret key
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY_HERE'); // Replace with your publishable key
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE'); // Replace with your webhook secret

// Stripe Price IDs (Create products in Stripe dashboard first)
define('STRIPE_PRICE_PRO_MONTHLY', 'price_YOUR_PRO_MONTHLY_ID');
define('STRIPE_PRICE_PRO_YEARLY', 'price_YOUR_PRO_YEARLY_ID');
define('STRIPE_PRICE_ENTERPRISE_MONTHLY', 'price_YOUR_ENTERPRISE_MONTHLY_ID');
define('STRIPE_PRICE_ENTERPRISE_YEARLY', 'price_YOUR_ENTERPRISE_YEARLY_ID');

// Stripe Settings
define('STRIPE_CURRENCY', 'USD');
define('STRIPE_TRIAL_PERIOD_DAYS', 14);
define('STRIPE_SUCCESS_URL', BASE_URL . '/pages/subscription-success.html');
define('STRIPE_CANCEL_URL', BASE_URL . '/pages/subscription-cancel.html');

// Initialize Stripe (requires Stripe PHP library)
// Install with: composer require stripe/stripe-php
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
}

class StripeHelper {

    /**
     * Create a Stripe customer
     */
    public static function createCustomer($email, $name = null, $metadata = []) {
        try {
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'name' => $name,
                'metadata' => $metadata
            ]);

            return $customer;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Create a subscription checkout session
     */
    public static function createCheckoutSession($customerId, $priceId, $metadata = []) {
        try {
            $session = \Stripe\Checkout\Session::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => STRIPE_SUCCESS_URL . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => STRIPE_CANCEL_URL,
                'subscription_data' => [
                    'trial_period_days' => STRIPE_TRIAL_PERIOD_DAYS,
                    'metadata' => $metadata
                ]
            ]);

            return $session;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a subscription
     */
    public static function cancelSubscription($subscriptionId, $atPeriodEnd = true) {
        try {
            $subscription = \Stripe\Subscription::update($subscriptionId, [
                'cancel_at_period_end' => $atPeriodEnd
            ]);

            if (!$atPeriodEnd) {
                $subscription->cancel();
            }

            return $subscription;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Create a billing portal session
     */
    public static function createPortalSession($customerId, $returnUrl) {
        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);

            return $session;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Failed to create portal session: ' . $e->getMessage());
        }
    }
}
