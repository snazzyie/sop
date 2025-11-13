// Pricing page functionality
let plans = [];
let billingCycle = 'monthly';
let currentPlan = null;

document.addEventListener('DOMContentLoaded', () => {
    if (!requireAuth()) return;

    setupBillingToggle();
    loadPlans();
    loadCurrentSubscription();
});

function setupBillingToggle() {
    const toggle = document.getElementById('billing-cycle-toggle');
    const monthlyLabel = document.getElementById('monthly-label');
    const yearlyLabel = document.getElementById('yearly-label');

    toggle?.addEventListener('change', (e) => {
        billingCycle = e.target.checked ? 'yearly' : 'monthly';

        monthlyLabel.classList.toggle('active');
        yearlyLabel.classList.toggle('active');

        renderPlans();
    });
}

async function loadPlans() {
    try {
        const data = await apiRequest('/subscriptions/plans');
        plans = data.data.plans;

        renderPlans();

    } catch (error) {
        console.error('Failed to load plans:', error);
    }
}

async function loadCurrentSubscription() {
    try {
        const data = await apiRequest('/subscriptions/current');
        currentPlan = data.data.subscription.plan_slug;

        renderPlans();

    } catch (error) {
        console.error('Failed to load current subscription:', error);
    }
}

function renderPlans() {
    const grid = document.getElementById('pricing-grid');
    grid.innerHTML = '';

    plans.forEach(plan => {
        const card = createPlanCard(plan);
        grid.appendChild(card);
    });
}

function createPlanCard(plan) {
    const card = document.createElement('div');
    card.className = 'pricing-card';

    if (plan.plan_slug === 'pro') {
        card.classList.add('featured');
    }

    const price = billingCycle === 'yearly' ? plan.price_yearly : plan.price_monthly;
    const yearlyPrice = plan.price_yearly;
    const monthlySavings = plan.price_monthly * 12 - yearlyPrice;

    const isCurrent = currentPlan === plan.plan_slug;

    card.innerHTML = `
        ${plan.plan_slug === 'pro' ? '<div class="pricing-badge">Most Popular</div>' : ''}

        <div class="plan-name">${plan.plan_name}</div>
        <div class="plan-description">${plan.description}</div>

        <div class="plan-price">
            <span class="price-currency">$</span>
            <span class="price-amount">${price}</span>
            <span class="price-period">/${billingCycle === 'yearly' ? 'year' : 'month'}</span>
            ${billingCycle === 'yearly' && monthlySavings > 0 ?
                `<div style="font-size: 14px; color: #10b981; margin-top: 5px;">Save $${monthlySavings}/year</div>`
                : ''}
        </div>

        <ul class="features-list">
            ${plan.features.map(feature => `
                <li>
                    <span class="feature-icon">✓</span>
                    <span>${feature}</span>
                </li>
            `).join('')}
        </ul>

        <button
            class="select-plan-btn ${isCurrent ? 'current' : ''}"
            data-plan-slug="${plan.plan_slug}"
            ${isCurrent ? 'disabled' : ''}
        >
            ${isCurrent ? 'Current Plan' : (plan.plan_slug === 'free' ? 'Get Started' : 'Upgrade')}
        </button>
    `;

    const button = card.querySelector('.select-plan-btn');
    if (!isCurrent) {
        button.addEventListener('click', () => selectPlan(plan.plan_slug));
    }

    return card;
}

async function selectPlan(planSlug) {
    if (planSlug === 'free') {
        alert('You are already on the free plan. Choose a paid plan to upgrade.');
        return;
    }

    try {
        const data = await apiRequest('/subscriptions/checkout', {
            method: 'POST',
            body: JSON.stringify({
                plan_slug: planSlug,
                billing_cycle: billingCycle
            })
        });

        // Redirect to Stripe checkout
        window.location.href = data.data.url;

    } catch (error) {
        alert('Failed to create checkout session: ' + error.message);
    }
}
