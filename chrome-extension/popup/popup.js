// Popup UI Controller
let recordingState = null;
let durationInterval = null;
let currentSopUrl = null;

// Initialize popup
document.addEventListener('DOMContentLoaded', () => {
  checkAuthentication();
  attachEventListeners();
});

// Check if user is authenticated
async function checkAuthentication() {
  showLoading('Checking authentication...');

  try {
    const response = await chrome.runtime.sendMessage({ type: 'CHECK_AUTH' });

    if (response.isAuthenticated) {
      // Load user info
      const userData = await chrome.storage.local.get(['user']);
      showRecordingSection(userData.user);

      // Check recording state
      checkRecordingState();
    } else {
      showAuthSection();
    }
  } catch (error) {
    console.error('Error checking auth:', error);
    showAuthSection();
  }
}

// Check current recording state
async function checkRecordingState() {
  try {
    const response = await chrome.runtime.sendMessage({ type: 'GET_RECORDING_STATE' });
    recordingState = response.state;

    if (recordingState.isRecording) {
      showRecordingState();
      startDurationTimer();
    } else {
      showIdleState();
    }
  } catch (error) {
    console.error('Error checking recording state:', error);
    showIdleState();
  }
}

// Attach event listeners
function attachEventListeners() {
  // Login form
  document.getElementById('login-form')?.addEventListener('submit', handleLogin);

  // Start recording
  document.getElementById('start-btn')?.addEventListener('click', handleStartRecording);

  // Stop recording
  document.getElementById('stop-btn')?.addEventListener('click', handleStopRecording);

  // View SOP
  document.getElementById('view-sop-btn')?.addEventListener('click', handleViewSop);

  // New recording
  document.getElementById('new-recording-btn')?.addEventListener('click', handleNewRecording);

  // Logout
  document.getElementById('logout-btn')?.addEventListener('click', handleLogout);

  // Open web app
  document.getElementById('open-webapp')?.addEventListener('click', (e) => {
    e.preventDefault();
    chrome.tabs.create({ url: 'http://localhost:8000/register' });
  });
}

// Handle login
async function handleLogin(e) {
  e.preventDefault();

  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;

  showLoading('Logging in...');
  hideError();

  try {
    const response = await chrome.runtime.sendMessage({
      type: 'LOGIN',
      credentials: { email, password }
    });

    if (response.success) {
      showRecordingSection(response.user);
      checkRecordingState();
    } else {
      showAuthSection();
      showError(response.error || 'Login failed');
    }
  } catch (error) {
    showAuthSection();
    showError('Connection error. Please try again.');
  }
}

// Handle logout
async function handleLogout() {
  if (recordingState?.isRecording) {
    if (!confirm('You have an active recording. Stop recording before logging out?')) {
      return;
    }
    await handleStopRecording();
  }

  await chrome.runtime.sendMessage({ type: 'LOGOUT' });
  showAuthSection();
}

// Handle start recording
async function handleStartRecording() {
  const options = {
    captureKeystrokes: document.getElementById('capture-keystrokes').checked,
    privacyMode: document.getElementById('privacy-mode').checked,
    currentTabOnly: document.getElementById('current-tab-only').checked
  };

  showLoading('Starting recording...');

  try {
    const response = await chrome.runtime.sendMessage({
      type: 'START_RECORDING',
      options: options
    });

    if (response.success) {
      recordingState = {
        isRecording: true,
        sessionId: response.sessionId,
        startTime: Date.now(),
        currentStepNumber: 0
      };

      showRecordingState();
      startDurationTimer();
    } else {
      showIdleState();
      showError(response.error || 'Failed to start recording');

      if (response.requiresAuth) {
        setTimeout(() => {
          showAuthSection();
        }, 2000);
      }
    }
  } catch (error) {
    showIdleState();
    showError('Error starting recording. Please try again.');
  }
}

// Handle stop recording
async function handleStopRecording() {
  showLoading('Stopping recording...');
  stopDurationTimer();

  try {
    const response = await chrome.runtime.sendMessage({ type: 'STOP_RECORDING' });

    if (response.success) {
      recordingState.isRecording = false;
      currentSopUrl = response.viewUrl;

      showSuccessState();
    } else {
      showRecordingState();
      startDurationTimer();
      showError(response.error || 'Failed to stop recording');
    }
  } catch (error) {
    showRecordingState();
    startDurationTimer();
    showError('Error stopping recording. Please try again.');
  }
}

// Handle view SOP
function handleViewSop() {
  if (currentSopUrl) {
    chrome.tabs.create({ url: currentSopUrl });
    window.close();
  }
}

// Handle new recording
function handleNewRecording() {
  showIdleState();
  currentSopUrl = null;
}

// Start duration timer
function startDurationTimer() {
  if (durationInterval) {
    clearInterval(durationInterval);
  }

  durationInterval = setInterval(() => {
    if (recordingState?.startTime) {
      const elapsed = Date.now() - recordingState.startTime;
      const minutes = Math.floor(elapsed / 60000);
      const seconds = Math.floor((elapsed % 60000) / 1000);

      document.getElementById('duration').textContent =
        `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

      // Update step count (would come from background in real implementation)
      document.getElementById('step-count').textContent = recordingState.currentStepNumber || 0;

      // Update session ID
      const shortId = recordingState.sessionId ? recordingState.sessionId.split('-')[0] : '-';
      document.getElementById('session-id').textContent = shortId;
    }
  }, 1000);
}

// Stop duration timer
function stopDurationTimer() {
  if (durationInterval) {
    clearInterval(durationInterval);
    durationInterval = null;
  }
}

// UI State Functions
function showLoading(text = 'Loading...') {
  hideAll();
  document.getElementById('loading-section').style.display = 'block';
  document.getElementById('loading-text').textContent = text;
}

function showAuthSection() {
  hideAll();
  document.getElementById('auth-section').style.display = 'block';
}

function showRecordingSection(user) {
  hideAll();
  document.getElementById('recording-section').style.display = 'block';

  if (user?.email) {
    document.getElementById('user-email').textContent = user.email;
  }
}

function showIdleState() {
  showRecordingSection();
  document.getElementById('idle-state').style.display = 'block';
  document.getElementById('recording-state').style.display = 'none';
  document.getElementById('success-state').style.display = 'none';
}

function showRecordingState() {
  showRecordingSection();
  document.getElementById('idle-state').style.display = 'none';
  document.getElementById('recording-state').style.display = 'block';
  document.getElementById('success-state').style.display = 'none';
}

function showSuccessState() {
  showRecordingSection();
  document.getElementById('idle-state').style.display = 'none';
  document.getElementById('recording-state').style.display = 'none';
  document.getElementById('success-state').style.display = 'block';
}

function hideAll() {
  document.getElementById('loading-section').style.display = 'none';
  document.getElementById('auth-section').style.display = 'none';
  document.getElementById('recording-section').style.display = 'none';
  hideError();
}

function showError(message) {
  const authError = document.getElementById('auth-error');
  const generalError = document.getElementById('error-message');

  if (document.getElementById('auth-section').style.display !== 'none') {
    authError.textContent = message;
    authError.style.display = 'block';
  } else {
    generalError.textContent = message;
    generalError.style.display = 'block';
  }
}

function hideError() {
  document.getElementById('auth-error').style.display = 'none';
  document.getElementById('error-message').style.display = 'none';
}
