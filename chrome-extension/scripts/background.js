// Background Service Worker - Manages recording sessions
const API_BASE_URL = 'http://localhost:8000/api'; // Change this to your backend URL

// State management
let recordingState = {
  isRecording: false,
  sessionId: null,
  startTime: null,
  currentStepNumber: 0,
  steps: [],
  authToken: null
};

// Initialize extension
chrome.runtime.onInstalled.addListener(() => {
  console.log('SOP Recorder installed');
  loadAuthToken();
});

// Load auth token from storage
async function loadAuthToken() {
  const result = await chrome.storage.local.get(['authToken']);
  if (result.authToken) {
    recordingState.authToken = result.authToken;
  }
}

// Listen for messages from popup and content scripts
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  console.log('Background received message:', message.type);

  switch (message.type) {
    case 'START_RECORDING':
      handleStartRecording(message.options).then(sendResponse);
      return true; // Keep channel open for async response

    case 'STOP_RECORDING':
      handleStopRecording().then(sendResponse);
      return true;

    case 'GET_RECORDING_STATE':
      sendResponse({ state: recordingState });
      break;

    case 'CAPTURE_STEP':
      handleCaptureStep(message.stepData, sender.tab).then(sendResponse);
      return true;

    case 'LOGIN':
      handleLogin(message.credentials).then(sendResponse);
      return true;

    case 'LOGOUT':
      handleLogout().then(sendResponse);
      return true;

    case 'CHECK_AUTH':
      sendResponse({
        isAuthenticated: !!recordingState.authToken,
        token: recordingState.authToken
      });
      break;
  }
});

// Start recording session
async function handleStartRecording(options = {}) {
  if (recordingState.isRecording) {
    return { success: false, error: 'Already recording' };
  }

  try {
    // Check authentication
    if (!recordingState.authToken) {
      return { success: false, error: 'Not authenticated', requiresAuth: true };
    }

    // Get current tab info
    const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
    const currentTab = tabs[0];

    // Generate session ID
    const sessionId = generateUUID();

    // Initialize recording state
    recordingState = {
      isRecording: true,
      sessionId: sessionId,
      startTime: Date.now(),
      currentStepNumber: 0,
      steps: [],
      authToken: recordingState.authToken,
      options: {
        captureKeystrokes: options.captureKeystrokes || false,
        privacyMode: options.privacyMode || true,
        currentTabOnly: options.currentTabOnly || true
      }
    };

    // Create session on backend
    const response = await fetch(`${API_BASE_URL}/sessions/create`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${recordingState.authToken}`
      },
      body: JSON.stringify({
        sessionId: sessionId,
        startUrl: currentTab.url,
        startTitle: currentTab.title,
        recordingOptions: recordingState.options
      })
    });

    if (!response.ok) {
      throw new Error('Failed to create session on backend');
    }

    const data = await response.json();
    console.log('Session created:', data);

    // Inject content script and start recording
    await chrome.tabs.sendMessage(currentTab.id, {
      type: 'START_RECORDING',
      sessionId: sessionId,
      options: recordingState.options
    });

    // Update badge
    chrome.action.setBadgeText({ text: 'REC' });
    chrome.action.setBadgeBackgroundColor({ color: '#FF0000' });

    return {
      success: true,
      sessionId: sessionId,
      message: 'Recording started'
    };

  } catch (error) {
    console.error('Error starting recording:', error);
    recordingState.isRecording = false;
    return { success: false, error: error.message };
  }
}

// Stop recording session
async function handleStopRecording() {
  if (!recordingState.isRecording) {
    return { success: false, error: 'Not recording' };
  }

  try {
    // Send stop message to all tabs
    const tabs = await chrome.tabs.query({});
    for (const tab of tabs) {
      try {
        await chrome.tabs.sendMessage(tab.id, { type: 'STOP_RECORDING' });
      } catch (e) {
        // Tab might not have content script, ignore
      }
    }

    // Send any remaining steps to backend
    if (recordingState.steps.length > 0) {
      await sendStepsBatch(recordingState.steps);
    }

    // Finalize session on backend
    const response = await fetch(`${API_BASE_URL}/sessions/${recordingState.sessionId}/finalize`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${recordingState.authToken}`
      },
      body: JSON.stringify({
        endTime: Date.now(),
        totalSteps: recordingState.currentStepNumber
      })
    });

    if (!response.ok) {
      throw new Error('Failed to finalize session');
    }

    const data = await response.json();
    console.log('Session finalized:', data);

    // Clear badge
    chrome.action.setBadgeText({ text: '' });

    // Store session ID for opening
    const completedSessionId = recordingState.sessionId;
    const sopId = data.sopId;

    // Reset state
    recordingState = {
      isRecording: false,
      sessionId: null,
      startTime: null,
      currentStepNumber: 0,
      steps: [],
      authToken: recordingState.authToken
    };

    return {
      success: true,
      message: 'Recording stopped',
      sopId: sopId,
      viewUrl: `${API_BASE_URL.replace('/api', '')}/sop/view/${sopId}`
    };

  } catch (error) {
    console.error('Error stopping recording:', error);
    return { success: false, error: error.message };
  }
}

// Handle captured step from content script
async function handleCaptureStep(stepData, tab) {
  if (!recordingState.isRecording) {
    return { success: false, error: 'Not recording' };
  }

  try {
    // Increment step number
    recordingState.currentStepNumber++;

    // Capture screenshot
    const screenshot = await chrome.tabs.captureVisibleTab(tab.windowId, {
      format: 'png'
    });

    // Build complete step object
    const step = {
      stepNumber: recordingState.currentStepNumber,
      sessionId: recordingState.sessionId,
      timestamp: Date.now(),
      actionType: stepData.actionType,
      element: stepData.element,
      page: {
        url: tab.url,
        title: tab.title
      },
      screenshot: screenshot, // Base64 data URL
      metadata: stepData.metadata
    };

    // Add to buffer
    recordingState.steps.push(step);

    console.log(`Captured step ${step.stepNumber}: ${step.actionType}`);

    // Send batch if buffer is full (every 5 steps)
    if (recordingState.steps.length >= 5) {
      await sendStepsBatch(recordingState.steps);
      recordingState.steps = [];
    }

    return { success: true, stepNumber: step.stepNumber };

  } catch (error) {
    console.error('Error capturing step:', error);
    return { success: false, error: error.message };
  }
}

// Send batch of steps to backend
async function sendStepsBatch(steps) {
  if (steps.length === 0) return;

  try {
    const response = await fetch(`${API_BASE_URL}/sessions/${recordingState.sessionId}/steps`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${recordingState.authToken}`
      },
      body: JSON.stringify({ steps: steps })
    });

    if (!response.ok) {
      throw new Error('Failed to send steps to backend');
    }

    const data = await response.json();
    console.log(`Sent ${steps.length} steps to backend`);
    return data;

  } catch (error) {
    console.error('Error sending steps:', error);
    throw error;
  }
}

// Handle login
async function handleLogin(credentials) {
  try {
    const response = await fetch(`${API_BASE_URL}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(credentials)
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Login failed');
    }

    const data = await response.json();

    // Store auth token
    recordingState.authToken = data.token;
    await chrome.storage.local.set({
      authToken: data.token,
      user: data.user
    });

    return {
      success: true,
      token: data.token,
      user: data.user
    };

  } catch (error) {
    console.error('Login error:', error);
    return { success: false, error: error.message };
  }
}

// Handle logout
async function handleLogout() {
  recordingState.authToken = null;
  await chrome.storage.local.remove(['authToken', 'user']);

  // Stop recording if active
  if (recordingState.isRecording) {
    await handleStopRecording();
  }

  return { success: true };
}

// Utility: Generate UUID
function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

// Listen for tab updates (navigation)
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (recordingState.isRecording && changeInfo.status === 'complete') {
    // Send navigation step
    chrome.tabs.sendMessage(tabId, {
      type: 'PAGE_LOADED',
      url: tab.url,
      title: tab.title
    }).catch(err => {
      // Content script might not be ready yet
      console.log('Could not send PAGE_LOADED message:', err);
    });
  }
});
