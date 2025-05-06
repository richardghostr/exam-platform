/**
 * WebGazer.js - Eye tracking library
 * Simplified version for exam proctoring system
 * Based on the original WebGazer.js by Brown University
 * @link https://webgazer.cs.brown.edu/
 */

;((window, undefined) => {
  // Main object
  var webgazer = {}

  // Private variables
  var _isReady = false
  var _isCalibrated = false
  var _isRunning = false
  var _videoElement = null
  var _videoStream = null
  var _canvas = null
  var _context = null
  var _currentPrediction = { x: 0, y: 0 }
  var _gazeHistory = []
  var _maxGazeHistory = 30
  var _callbackFunc = null
  var _calibrationPoints = []
  var _minCalibrationPoints = 5
  var _screenBounds = {
    width: window.innerWidth,
    height: window.innerHeight,
  }

  // Configuration
  var _settings = {
    showVideo: true,
    showPredictions: true,
    showFaceOverlay: true,
    showGazeDot: true,
    gazeErrorThreshold: 50, // pixels
    offScreenThreshold: 100, // pixels
    predictionInterval: 100, // ms
    calibrationDuration: 1000, // ms per point
    stabilizationInterval: 250, // ms
  }

  // Event listeners
  var _eventListeners = {
    ready: [],
    calibrationProgress: [],
    calibrationComplete: [],
    gazeUpdate: [],
    gazeOffScreen: [],
    gazeOnScreen: [],
    gazeError: [],
  }

  // Private methods
  function _setupVideo() {
    return new Promise((resolve, reject) => {
      if (_videoElement) {
        resolve(_videoElement)
        return
      }

      _videoElement = document.createElement("video")
      _videoElement.id = "webgazerVideoFeed"
      _videoElement.autoplay = true
      _videoElement.style.display = _settings.showVideo ? "block" : "none"
      _videoElement.style.position = "absolute"
      _videoElement.style.top = "0px"
      _videoElement.style.left = "0px"
      _videoElement.style.width = "320px"
      _videoElement.style.height = "240px"
      _videoElement.style.zIndex = "10"

      // Request camera access
      navigator.mediaDevices
        .getUserMedia({
          video: true,
          audio: false,
        })
        .then((stream) => {
          _videoStream = stream
          _videoElement.srcObject = stream

          // Wait for video to be ready
          _videoElement.addEventListener("loadedmetadata", () => {
            document.body.appendChild(_videoElement)

            // Create canvas for drawing
            _canvas = document.createElement("canvas")
            _canvas.id = "webgazerCanvas"
            _canvas.width = _videoElement.videoWidth
            _canvas.height = _videoElement.videoHeight
            _canvas.style.position = "absolute"
            _canvas.style.top = "0px"
            _canvas.style.left = "0px"
            _canvas.style.zIndex = "11"
            document.body.appendChild(_canvas)

            _context = _canvas.getContext("2d")

            // Mark as ready
            _isReady = true
            _triggerEvent("ready")

            resolve(_videoElement)
          })
        })
        .catch((err) => {
          console.error("Error accessing webcam:", err)
          reject(err)
        })
    })
  }

  function _startPrediction() {
    if (!_isReady || _isRunning) return

    _isRunning = true

    // Start prediction loop
    _predictionLoop()
  }

  function _stopPrediction() {
    _isRunning = false
  }

  function _predictionLoop() {
    if (!_isRunning) return

    // Simulate eye tracking prediction
    _predictGaze()

    // Draw visualization if needed
    if (_settings.showPredictions) {
      _drawVisualization()
    }

    // Call callback if set
    if (_callbackFunc) {
      _callbackFunc(_currentPrediction)
    }

    // Trigger event
    _triggerEvent("gazeUpdate", _currentPrediction)

    // Check if gaze is off screen
    _checkGazePosition()

    // Continue loop
    setTimeout(_predictionLoop, _settings.predictionInterval)
  }

  function _predictGaze() {
    // In a real implementation, this would use machine learning models
    // to predict gaze position based on face landmarks

    // For this simplified version, we'll simulate gaze with some randomness
    // but generally following mouse position if available

    const mouseX = window.mouseX || window.innerWidth / 2
    const mouseY = window.mouseY || window.innerHeight / 2

    // Add some randomness to simulate prediction errors
    const randomX = (Math.random() - 0.5) * 50
    const randomY = (Math.random() - 0.5) * 50

    // Calculate new prediction
    const newX = mouseX + randomX
    const newY = mouseY + randomY

    // Stabilize prediction using history
    _gazeHistory.push({ x: newX, y: newY })
    if (_gazeHistory.length > _maxGazeHistory) {
      _gazeHistory.shift()
    }

    // Average the last few predictions for stability
    let sumX = 0,
      sumY = 0
    for (let i = 0; i < _gazeHistory.length; i++) {
      sumX += _gazeHistory[i].x
      sumY += _gazeHistory[i].y
    }

    _currentPrediction = {
      x: sumX / _gazeHistory.length,
      y: sumY / _gazeHistory.length,
    }

    return _currentPrediction
  }

  function _drawVisualization() {
    if (!_context) return

    // Clear canvas
    _context.clearRect(0, 0, _canvas.width, _canvas.height)

    // Draw face overlay if enabled
    if (_settings.showFaceOverlay && _videoElement) {
      // Simulate face detection with a rectangle
      _context.strokeStyle = "green"
      _context.lineWidth = 2
      _context.strokeRect(_canvas.width / 4, _canvas.height / 4, _canvas.width / 2, _canvas.height / 2)

      // Simulate eye detection
      _context.fillStyle = "rgba(0, 255, 0, 0.5)"
      // Left eye
      _context.beginPath()
      _context.arc(_canvas.width * 0.33, _canvas.height * 0.4, 10, 0, 2 * Math.PI)
      _context.fill()

      // Right eye
      _context.beginPath()
      _context.arc(_canvas.width * 0.66, _canvas.height * 0.4, 10, 0, 2 * Math.PI)
      _context.fill()
    }

    // Draw gaze dot if enabled
    if (_settings.showGazeDot) {
      // Convert screen coordinates to video coordinates
      const videoX = (_currentPrediction.x / _screenBounds.width) * _canvas.width
      const videoY = (_currentPrediction.y / _screenBounds.height) * _canvas.height

      _context.fillStyle = "red"
      _context.beginPath()
      _context.arc(videoX, videoY, 5, 0, 2 * Math.PI)
      _context.fill()
    }
  }

  function _checkGazePosition() {
    // Check if gaze is off screen
    const isOffScreen =
      _currentPrediction.x < -_settings.offScreenThreshold ||
      _currentPrediction.y < -_settings.offScreenThreshold ||
      _currentPrediction.x > _screenBounds.width + _settings.offScreenThreshold ||
      _currentPrediction.y > _screenBounds.height + _settings.offScreenThreshold

    // Static variable to track previous state
    if (!_checkGazePosition.wasOffScreen && isOffScreen) {
      _triggerEvent("gazeOffScreen", _currentPrediction)
    } else if (_checkGazePosition.wasOffScreen && !isOffScreen) {
      _triggerEvent("gazeOnScreen", _currentPrediction)
    }

    _checkGazePosition.wasOffScreen = isOffScreen
  }

  // Initialize static variable
  _checkGazePosition.wasOffScreen = false

  function _calibrate(x, y, duration) {
    return new Promise((resolve, reject) => {
      if (!_isReady) {
        reject(new Error("WebGazer is not ready"))
        return
      }

      duration = duration || _settings.calibrationDuration

      // Add calibration point
      _calibrationPoints.push({ x, y })

      // Trigger progress event
      _triggerEvent("calibrationProgress", {
        point: { x, y },
        progress: _calibrationPoints.length / _minCalibrationPoints,
      })

      // Simulate calibration process
      setTimeout(() => {
        // Check if we have enough calibration points
        if (_calibrationPoints.length >= _minCalibrationPoints) {
          _isCalibrated = true
          _triggerEvent("calibrationComplete", {
            points: _calibrationPoints,
          })
        }

        resolve({
          point: { x, y },
          progress: _calibrationPoints.length / _minCalibrationPoints,
          isComplete: _isCalibrated,
        })
      }, duration)
    })
  }

  function _triggerEvent(eventName, data) {
    if (!_eventListeners[eventName]) return

    _eventListeners[eventName].forEach((callback) => {
      try {
        callback(data)
      } catch (err) {
        console.error(`Error in ${eventName} event handler:`, err)
      }
    })
  }

  // Public API
  webgazer.begin = function () {
    return _setupVideo().then(() => {
      _startPrediction()
      return this
    })
  }

  webgazer.pause = function () {
    _stopPrediction()
    return this
  }

  webgazer.resume = function () {
    _startPrediction()
    return this
  }

  webgazer.end = function () {
    _stopPrediction()

    // Stop video stream
    if (_videoStream) {
      _videoStream.getTracks().forEach((track) => track.stop())
      _videoStream = null
    }

    // Remove elements
    if (_videoElement && _videoElement.parentNode) {
      _videoElement.parentNode.removeChild(_videoElement)
      _videoElement = null
    }

    if (_canvas && _canvas.parentNode) {
      _canvas.parentNode.removeChild(_canvas)
      _canvas = null
      _context = null
    }

    // Reset state
    _isReady = false
    _isRunning = false
    _isCalibrated = false
    _gazeHistory = []
    _calibrationPoints = []

    return this
  }

  webgazer.setGazeCallback = function (callback) {
    _callbackFunc = callback
    return this
  }

  webgazer.getCurrentPrediction = () => _currentPrediction

  webgazer.showVideo = function (show) {
    _settings.showVideo = show !== false
    if (_videoElement) {
      _videoElement.style.display = _settings.showVideo ? "block" : "none"
    }
    return this
  }

  webgazer.showPredictions = function (show) {
    _settings.showPredictions = show !== false
    return this
  }

  webgazer.showFaceOverlay = function (show) {
    _settings.showFaceOverlay = show !== false
    return this
  }

  webgazer.showGazeDot = function (show) {
    _settings.showGazeDot = show !== false
    return this
  }

  webgazer.setTracker = function (tracker) {
    // In this simplified version, we don't actually change trackers
    console.log("Setting tracker to:", tracker)
    return this
  }

  webgazer.setRegressor = function (regressor) {
    // In this simplified version, we don't actually change regressors
    console.log("Setting regressor to:", regressor)
    return this
  }

  webgazer.addCalibrationPoint = (x, y, duration) => _calibrate(x, y, duration)

  webgazer.clearCalibration = function () {
    _calibrationPoints = []
    _isCalibrated = false
    return this
  }

  webgazer.isReady = () => _isReady

  webgazer.isCalibrated = () => _isCalibrated

  webgazer.on = function (event, callback) {
    if (!_eventListeners[event]) {
      _eventListeners[event] = []
    }

    _eventListeners[event].push(callback)
    return this
  }

  webgazer.off = function (event, callback) {
    if (!_eventListeners[event]) return this

    if (!callback) {
      // Remove all callbacks for this event
      _eventListeners[event] = []
    } else {
      // Remove specific callback
      _eventListeners[event] = _eventListeners[event].filter((cb) => cb !== callback)
    }

    return this
  }

  webgazer.getVideoElementCanvas = () => ({
    video: _videoElement,
    canvas: _canvas,
  })

  webgazer.setGazeErrorThreshold = function (threshold) {
    _settings.gazeErrorThreshold = threshold
    return this
  }

  webgazer.setOffScreenThreshold = function (threshold) {
    _settings.offScreenThreshold = threshold
    return this
  }

  webgazer.setPredictionInterval = function (interval) {
    _settings.predictionInterval = interval
    return this
  }

  webgazer.setCalibrationDuration = function (duration) {
    _settings.calibrationDuration = duration
    return this
  }

  webgazer.setStabilizationInterval = function (interval) {
    _settings.stabilizationInterval = interval
    return this
  }

  webgazer.getScreenBounds = () => _screenBounds

  webgazer.updateScreenBounds = function () {
    _screenBounds = {
      width: window.innerWidth,
      height: window.innerHeight,
    }
    return this
  }

  // Expose to window
  window.webgazer = webgazer
})(window);

<script src="https://webgazer.cs.brown.edu/webgazer.js"></script>

// Votre c// Variables globales
let gazeData = [];
let isTracking = false;
let trackingInterval;
// Ajoutez ceci dans la fonction d'initialisation
webgazer
.showVideoPreview(true)
.showPredictionPoints(true)
.applyKalmanFilter(true)
.setGazeListener(function(data, elapsedTime) {
    // ... code existant ...
    checkGazeOnElements(x, y); // Vérifier les éléments regardés
});

document.addEventListener('DOMContentLoaded', function() {
const startBtn = document.getElementById('startTracking');
const stopBtn = document.getElementById('stopTracking');
const saveBtn = document.getElementById('saveData');
const statusDiv = document.getElementById('trackingStatus');
const videoContainer = document.getElementById('webgazerVideoContainer');
const gazeDot = document.getElementById('gazeDot');
const logContent = document.getElementById('logContent');

// Démarrer le suivi
startBtn.addEventListener('click', function() {
    startBtn.disabled = true;
    stopBtn.disabled = false;
    saveBtn.disabled = true;
    statusDiv.textContent = "Statut: Initialisation...";
    videoContainer.style.display = "block";
    gazeDot.style.display = "block";
    
    // Initialiser WebGazer
    webgazer.setRegression('ridge') // ou 'weightedRidge'
        .setTracker('clmtrackr')
        .setGazeListener(function(data, elapsedTime) {
            if (data == null) return;
            
            const x = data.x;
            const y = data.y;
            
            // Mettre à jour la position du point de regard
            gazeDot.style.left = (x - 5) + 'px';
            gazeDot.style.top = (y - 5) + 'px';
            
            // Enregistrer les données
            if (isTracking) {
                gazeData.push({
                    x: x,
                    y: y,
                    time: elapsedTime,
                    timestamp: new Date().toISOString()
                });
            }
        })
        .begin()
        .showPredictionPoints(true);
    
    // Vérifier si le suivi est actif
    trackingInterval = setInterval(function() {
        if (webgazer.getTracker().getCurrentPosition()) {
            isTracking = true;
            statusDiv.textContent = "Statut: Suivi actif";
            saveBtn.disabled = false;
        } else {
            isTracking = false;
            statusDiv.textContent = "Statut: Visage non détecté";
        }
    }, 500);
    
    logContent.innerHTML += "<p>Suivi oculaire initialisé.</p>";
});

// Arrêter le suivi
stopBtn.addEventListener('click', function() {
    startBtn.disabled = false;
    stopBtn.disabled = true;
    isTracking = false;
    clearInterval(trackingInterval);
    webgazer.end();
    videoContainer.style.display = "none";
    gazeDot.style.display = "none";
    statusDiv.textContent = "Statut: Suivi arrêté";
    logContent.innerHTML += "<p>Suivi oculaire arrêté.</p>";
});

// Enregistrer les données dans PHP
saveBtn.addEventListener('click', function() {
    if (gazeData.length === 0) {
        logContent.innerHTML += "<p>Aucune donnée à enregistrer.</p>";
        return;
    }
    
    // Envoyer les données au serveur via AJAX
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "save_data.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                logContent.innerHTML += `<p>${response.message} ${gazeData.length} points enregistrés.</p>`;
                gazeData = []; // Vider le buffer
            } else {
                logContent.innerHTML += "<p>Erreur lors de l'enregistrement.</p>";
            }
        }
    };
    
    xhr.send(JSON.stringify({ gazeData: gazeData }));
});
});
function checkGazeOnElements(x, y) {
const elements = document.querySelectorAll('.trackable'); // Ajoutez la classe 'trackable' aux éléments à suivre

elements.forEach(el => {
    const rect = el.getBoundingClientRect();
    if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
        el.style.backgroundColor = 'rgba(255, 255, 0, 0.3)';
        // Enregistrer l'interaction
        gazeData.push({
            type: 'element_hover',
            element: el.id || el.className,
            x: x,
            y: y,
            timestamp: new Date().toISOString()
        });
    } else {
        el.style.backgroundColor = '';
    }
});
}
