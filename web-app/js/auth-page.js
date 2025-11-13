// Login/Register page functionality
const API_BASE_URL = 'http://localhost:8000/api';

document.addEventListener('DOMContentLoaded', () => {
    // Check if already authenticated
    if (localStorage.getItem('authToken')) {
        window.location.href = '../index.html';
        return;
    }

    // Check for error in URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    if (error) {
        showError(decodeURIComponent(error));
    }

    setupForms();
    setupOAuth();
});

function setupForms() {
    // Form switching
    document.getElementById('show-register')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('login-form-container').style.display = 'none';
        document.getElementById('register-form-container').style.display = 'block';
        hideMessages();
    });

    document.getElementById('show-login')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('register-form-container').style.display = 'none';
        document.getElementById('login-form-container').style.display = 'block';
        hideMessages();
    });

    // Login form submission
    document.getElementById('login-form')?.addEventListener('submit', handleLogin);

    // Register form submission
    document.getElementById('register-form')?.addEventListener('submit', handleRegister);
}

async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    hideMessages();

    try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Login failed');
        }

        // Store auth data
        localStorage.setItem('authToken', data.data.token);
        localStorage.setItem('userData', JSON.stringify(data.data.user));

        // Show success and redirect
        showSuccess('Login successful! Redirecting...');

        setTimeout(() => {
            window.location.href = '../index.html';
        }, 1000);

    } catch (error) {
        showError(error.message);
    }
}

async function handleRegister(e) {
    e.preventDefault();

    const name = document.getElementById('register-name').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;

    hideMessages();

    try {
        const response = await fetch(`${API_BASE_URL}/auth/register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name, email, password })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Registration failed');
        }

        // Store auth data
        localStorage.setItem('authToken', data.data.token);
        localStorage.setItem('userData', JSON.stringify(data.data.user));

        // Show success and redirect
        showSuccess('Account created! Redirecting...');

        setTimeout(() => {
            window.location.href = '../index.html';
        }, 1000);

    } catch (error) {
        showError(error.message);
    }
}

function showError(message) {
    const errorDiv = document.getElementById('auth-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

function showSuccess(message) {
    const successDiv = document.getElementById('auth-success');
    successDiv.textContent = message;
    successDiv.style.display = 'block';
}

function hideMessages() {
    document.getElementById('auth-error').style.display = 'none';
    document.getElementById('auth-success').style.display = 'none';
}

function setupOAuth() {
    document.getElementById('google-login-btn')?.addEventListener('click', () => {
        // Redirect to Google OAuth
        window.location.href = `${API_BASE_URL}/oauth/google/login`;
    });
}
