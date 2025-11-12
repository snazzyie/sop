// Content Script - Captures user actions on web pages
let isRecording = false;
let sessionId = null;
let recordingOptions = {};
let lastCapturedElement = null;
let highlightOverlay = null;

// Listen for messages from background script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  console.log('Content script received:', message.type);

  switch (message.type) {
    case 'START_RECORDING':
      startRecording(message.sessionId, message.options);
      sendResponse({ success: true });
      break;

    case 'STOP_RECORDING':
      stopRecording();
      sendResponse({ success: true });
      break;

    case 'PAGE_LOADED':
      if (isRecording) {
        captureNavigationStep(message.url, message.title);
      }
      sendResponse({ success: true });
      break;
  }
});

// Start recording on this page
function startRecording(sid, options) {
  isRecording = true;
  sessionId = sid;
  recordingOptions = options;

  console.log('Recording started on this page');

  // Show recording indicator
  showRecordingIndicator();

  // Add event listeners
  attachEventListeners();
}

// Stop recording on this page
function stopRecording() {
  isRecording = false;
  sessionId = null;

  console.log('Recording stopped on this page');

  // Remove recording indicator
  hideRecordingIndicator();

  // Remove event listeners
  detachEventListeners();
}

// Attach event listeners to capture actions
function attachEventListeners() {
  document.addEventListener('click', handleClick, true);
  document.addEventListener('input', handleInput, true);
  document.addEventListener('change', handleChange, true);
  document.addEventListener('submit', handleSubmit, true);

  if (recordingOptions.captureKeystrokes) {
    document.addEventListener('keydown', handleKeyDown, true);
  }
}

// Detach event listeners
function detachEventListeners() {
  document.removeEventListener('click', handleClick, true);
  document.removeEventListener('input', handleInput, true);
  document.removeEventListener('change', handleChange, true);
  document.removeEventListener('submit', handleSubmit, true);
  document.removeEventListener('keydown', handleKeyDown, true);
}

// Handle click events
function handleClick(event) {
  if (!isRecording) return;

  const element = event.target;

  // Ignore clicks on recording indicator
  if (element.closest('#sop-recording-indicator')) {
    return;
  }

  // Don't capture rapid repeated clicks on same element
  if (lastCapturedElement === element) {
    const now = Date.now();
    if (now - (element._lastCaptureTime || 0) < 1000) {
      return;
    }
  }

  element._lastCaptureTime = Date.now();
  lastCapturedElement = element;

  // Capture the click
  const stepData = {
    actionType: 'click',
    element: extractElementData(element),
    metadata: {
      mousePosition: { x: event.clientX, y: event.clientY },
      viewportSize: { width: window.innerWidth, height: window.innerHeight },
      scrollPosition: { x: window.scrollX, y: window.scrollY }
    }
  };

  // Highlight the element briefly
  highlightElement(element);

  // Send to background script
  sendStepToBackground(stepData);
}

// Handle input events (text fields, textareas)
function handleInput(event) {
  if (!isRecording) return;

  const element = event.target;

  // Only capture meaningful inputs (not every keystroke)
  clearTimeout(element._inputTimeout);
  element._inputTimeout = setTimeout(() => {
    const stepData = {
      actionType: 'input',
      element: extractElementData(element, true),
      metadata: {
        inputLength: element.value.length,
        viewportSize: { width: window.innerWidth, height: window.innerHeight },
        scrollPosition: { x: window.scrollX, y: window.scrollY }
      }
    };

    highlightElement(element);
    sendStepToBackground(stepData);
  }, 1000); // Wait 1 second after last keystroke
}

// Handle change events (dropdowns, checkboxes, radio buttons)
function handleChange(event) {
  if (!isRecording) return;

  const element = event.target;

  let actionType = 'change';
  if (element.type === 'checkbox') actionType = 'checkbox';
  if (element.type === 'radio') actionType = 'radio';
  if (element.tagName === 'SELECT') actionType = 'select';

  const stepData = {
    actionType: actionType,
    element: extractElementData(element, true),
    metadata: {
      viewportSize: { width: window.innerWidth, height: window.innerHeight },
      scrollPosition: { x: window.scrollX, y: window.scrollY }
    }
  };

  highlightElement(element);
  sendStepToBackground(stepData);
}

// Handle form submit
function handleSubmit(event) {
  if (!isRecording) return;

  const form = event.target;

  const stepData = {
    actionType: 'submit',
    element: extractElementData(form),
    metadata: {
      formAction: form.action,
      formMethod: form.method,
      viewportSize: { width: window.innerWidth, height: window.innerHeight },
      scrollPosition: { x: window.scrollX, y: window.scrollY }
    }
  };

  highlightElement(form);
  sendStepToBackground(stepData);
}

// Handle keydown (if enabled)
function handleKeyDown(event) {
  if (!isRecording) return;

  // Only capture special keys (Enter, Tab, Escape, etc.)
  const specialKeys = ['Enter', 'Tab', 'Escape', 'Delete'];
  if (!specialKeys.includes(event.key)) return;

  const stepData = {
    actionType: 'keypress',
    element: extractElementData(event.target),
    metadata: {
      key: event.key,
      viewportSize: { width: window.innerWidth, height: window.innerHeight },
      scrollPosition: { x: window.scrollX, y: window.scrollY }
    }
  };

  sendStepToBackground(stepData);
}

// Capture navigation step
function captureNavigationStep(url, title) {
  const stepData = {
    actionType: 'navigate',
    element: {
      tagName: 'page',
      text: title,
      attributes: {}
    },
    metadata: {
      url: url,
      viewportSize: { width: window.innerWidth, height: window.innerHeight }
    }
  };

  sendStepToBackground(stepData);
}

// Extract data from DOM element
function extractElementData(element, includeValue = false) {
  const rect = element.getBoundingClientRect();

  // Get element text
  let text = '';
  if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
    text = element.placeholder || element.name || element.id || '';
  } else if (element.tagName === 'SELECT') {
    text = element.options[element.selectedIndex]?.text || '';
  } else {
    text = element.innerText || element.textContent || element.value || '';
  }

  // Limit text length
  if (text.length > 100) {
    text = text.substring(0, 100) + '...';
  }

  // Get unique selector
  const selector = generateUniqueSelector(element);

  // Build element data
  const elementData = {
    tagName: element.tagName.toLowerCase(),
    text: text.trim(),
    selector: selector,
    xpath: getXPath(element),
    position: {
      x: Math.round(rect.left + window.scrollX),
      y: Math.round(rect.top + window.scrollY),
      width: Math.round(rect.width),
      height: Math.round(rect.height)
    },
    attributes: {
      id: element.id || null,
      class: element.className || null,
      name: element.name || null,
      type: element.type || null,
      href: element.href || null,
      placeholder: element.placeholder || null
    }
  };

  // Include value if requested and privacy mode allows
  if (includeValue && !recordingOptions.privacyMode) {
    elementData.value = element.value || null;
  } else if (includeValue) {
    // In privacy mode, check if it's a sensitive field
    const isSensitive = isSensitiveField(element);
    elementData.value = isSensitive ? '[REDACTED]' : element.value;
  }

  return elementData;
}

// Check if field is sensitive (password, credit card, etc.)
function isSensitiveField(element) {
  // Check input type
  if (element.type === 'password') return true;

  // Check for common sensitive field patterns
  const sensitivePatterns = [
    /password/i,
    /passwd/i,
    /pwd/i,
    /credit.*card/i,
    /card.*number/i,
    /cvv/i,
    /cvc/i,
    /ssn/i,
    /social.*security/i
  ];

  const fieldName = (element.name || element.id || element.placeholder || '').toLowerCase();

  return sensitivePatterns.some(pattern => pattern.test(fieldName));
}

// Generate unique CSS selector for element
function generateUniqueSelector(element) {
  if (element.id) {
    return `#${element.id}`;
  }

  let selector = element.tagName.toLowerCase();

  if (element.className) {
    const classes = element.className.split(' ').filter(c => c.trim());
    if (classes.length > 0) {
      selector += '.' + classes.join('.');
    }
  }

  // Add nth-child if needed for uniqueness
  const parent = element.parentElement;
  if (parent) {
    const siblings = Array.from(parent.children);
    const index = siblings.indexOf(element);
    if (siblings.filter(s => s.tagName === element.tagName).length > 1) {
      selector += `:nth-child(${index + 1})`;
    }
  }

  return selector;
}

// Generate XPath for element
function getXPath(element) {
  if (element.id) {
    return `//*[@id="${element.id}"]`;
  }

  const paths = [];
  for (; element && element.nodeType === 1; element = element.parentNode) {
    let index = 0;
    let hasFollowingSiblings = false;

    for (let sibling = element.previousSibling; sibling; sibling = sibling.previousSibling) {
      if (sibling.nodeType === 10) continue; // Skip DocumentType
      if (sibling.nodeName === element.nodeName) index++;
    }

    for (let sibling = element.nextSibling; sibling && !hasFollowingSiblings; sibling = sibling.nextSibling) {
      if (sibling.nodeName === element.nodeName) hasFollowingSiblings = true;
    }

    const tagName = element.nodeName.toLowerCase();
    const pathIndex = (index || hasFollowingSiblings) ? `[${index + 1}]` : '';
    paths.unshift(tagName + pathIndex);
  }

  return paths.length ? '/' + paths.join('/') : '';
}

// Highlight element temporarily
function highlightElement(element) {
  // Remove previous highlight
  removeHighlight();

  // Create highlight overlay
  const rect = element.getBoundingClientRect();

  highlightOverlay = document.createElement('div');
  highlightOverlay.id = 'sop-element-highlight';
  highlightOverlay.style.cssText = `
    position: absolute;
    left: ${rect.left + window.scrollX}px;
    top: ${rect.top + window.scrollY}px;
    width: ${rect.width}px;
    height: ${rect.height}px;
    border: 3px solid #FF0000;
    background: rgba(255, 0, 0, 0.1);
    pointer-events: none;
    z-index: 999999;
    box-sizing: border-box;
    transition: opacity 0.3s;
  `;

  document.body.appendChild(highlightOverlay);

  // Remove after 1 second
  setTimeout(() => {
    removeHighlight();
  }, 1000);
}

// Remove highlight
function removeHighlight() {
  if (highlightOverlay) {
    highlightOverlay.remove();
    highlightOverlay = null;
  }
}

// Show recording indicator
function showRecordingIndicator() {
  const indicator = document.createElement('div');
  indicator.id = 'sop-recording-indicator';
  indicator.innerHTML = `
    <div style="
      position: fixed;
      top: 10px;
      right: 10px;
      background: #FF0000;
      color: white;
      padding: 8px 15px;
      border-radius: 20px;
      font-family: Arial, sans-serif;
      font-size: 12px;
      font-weight: bold;
      z-index: 999999;
      box-shadow: 0 2px 10px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      gap: 8px;
    ">
      <div style="
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
        animation: pulse 1.5s infinite;
      "></div>
      Recording SOP
    </div>
    <style>
      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
      }
    </style>
  `;

  document.body.appendChild(indicator);
}

// Hide recording indicator
function hideRecordingIndicator() {
  const indicator = document.getElementById('sop-recording-indicator');
  if (indicator) {
    indicator.remove();
  }
}

// Send step data to background script
function sendStepToBackground(stepData) {
  chrome.runtime.sendMessage({
    type: 'CAPTURE_STEP',
    stepData: stepData
  }, response => {
    if (chrome.runtime.lastError) {
      console.error('Error sending step:', chrome.runtime.lastError);
    } else if (response?.success) {
      console.log(`Step ${response.stepNumber} captured`);
    }
  });
}

// Initialize
console.log('SOP Recorder content script loaded');
