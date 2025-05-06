/**
 * WebGazer.js - Eye tracking library
 * Improved version for exam proctoring system
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
  var _debugMode = true // Set to true to enable console logs
  var _faceDetected = false
  var _eyesDetected = false
  var _lastFaceDetectionTime = 0
  var _faceDetectionInterval = 500 // ms
  var _facePosition = { x: 0, y: 0, width: 0, height: 0 }
  var _leftEyePosition = { x: 0, y: 0 }
  var _rightEyePosition = { x: 0, y: 0 }
  var _errorState = null
  var _lastBlinkTime = 0
  var _blinkInterval = 0
  var _blinkCount = 0
  var _blinkDuration = 0
  var _eyesClosed = false
  var _lookDirection = "center" // center, left, right, up, down
  var _lookDirectionConfidence = 0
  var _calibrationInProgress = false

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
    faceDetectionThreshold: 0.5, // confidence threshold
    eyeDetectionThreshold: 0.3, // confidence threshold
    blinkDetectionThreshold: 0.2, // confidence threshold
    lookDirectionThreshold: 0.4, // confidence threshold
    debugLevel: 1, // 0: none, 1: errors, 2: warnings, 3: info, 4: debug
  }

  // Event listeners
  var _eventListeners = {
    ready: [],
    calibrationStart: [],
    calibrationProgress: [],
    calibrationComplete: [],
    gazeUpdate: [],
    gazeOffScreen: [],
    gazeOnScreen: [],
    gazeError: [],
    faceDetected: [],
    faceLost: [],
    eyesDetected: [],
    eyesLost: [],
    blink: [],
    lookDirectionChange: [],
    error: [],
  }

  // Debug logging
  function _log(level, ...args) {
    if (!_debugMode || level > _settings.debugLevel) return

    const levels = ["NONE", "ERROR", "WARN", "INFO", "DEBUG"]
    console.log(`[WebGazer][${levels[level]}]`, ...args)
  }

  // Private methods
  function _setupVideo() {
    return new Promise((resolve, reject) => {
      if (_videoElement) {
        resolve(_videoElement)
        return
      }

      _log(3, "Setting up video element")

      _videoElement = document.createElement("video")
      _videoElement.id = "webgazerVideoFeed"
      _videoElement.autoplay = true
      _videoElement.muted = true
      _videoElement.playsInline = true
      _videoElement.style.display = _settings.showVideo ? "block" : "none"
      _videoElement.style.position = "absolute"
      _videoElement.style.top = "0px"
      _videoElement.style.left = "0px"
      _videoElement.style.width = "320px"
      _videoElement.style.height = "240px"
      _videoElement.style.zIndex = "10"
      _videoElement.style.transform = "scaleX(-1)" // Mirror video

      // Request camera access
      _log(3, "Requesting camera access")

      navigator.mediaDevices
        .getUserMedia({
          video: {
            width: { ideal: 640 },
            height: { ideal: 480 },
            facingMode: "user",
            frameRate: { min: 15, ideal: 30 },
          },
          audio: false,
        })
        .then((stream) => {
          _log(3, "Camera access granted")

          _videoStream = stream
          _videoElement.srcObject = stream

          // Wait for video to be ready
          _videoElement.addEventListener("loadedmetadata", () => {
            _log(3, "Video metadata loaded")

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
            _canvas.style.transform = "scaleX(-1)" // Mirror canvas to match video
            document.body.appendChild(_canvas)

            _context = _canvas.getContext("2d")

            // Mark as ready
            _isReady = true
            _log(3, "WebGazer is ready")
            _triggerEvent("ready")

            // Start face detection
            _detectFace()

            resolve(_videoElement)
          })

          _videoElement.addEventListener("error", (err) => {
            _log(1, "Video element error:", err)
            _errorState = "video_error"
            _triggerEvent("error", { type: "video_error", message: "Video element error", details: err })
            reject(err)
          })
        })
        .catch((err) => {
          _log(1, "Error accessing webcam:", err)
          _errorState = "camera_access_denied"
          _triggerEvent("error", { type: "camera_access_denied", message: "Camera access denied", details: err })
          reject(err)
        })
    })
  }

  function _detectFace() {
    if (!_isReady || !_videoElement || !_videoElement.videoWidth) {
      setTimeout(_detectFace, 100)
      return
    }

    const now = Date.now()
    if (now - _lastFaceDetectionTime < _faceDetectionInterval) {
      setTimeout(_detectFace, _faceDetectionInterval - (now - _lastFaceDetectionTime))
      return
    }

    _lastFaceDetectionTime = now

    // In a real implementation, this would use face-api.js or similar
    // For this simplified version, we'll simulate face detection

    // Simulate face detection with 90% probability of success
    const faceDetected = Math.random() < 0.9

    if (faceDetected) {
      if (!_faceDetected) {
        _log(3, "Face detected")
        _faceDetected = true
        _triggerEvent("faceDetected")
      }

      // Simulate face position (center of video with some randomness)
      _facePosition = {
        x: _canvas.width / 2 + (Math.random() - 0.5) * 20,
        y: _canvas.height / 2 + (Math.random() - 0.5) * 20,
        width: _canvas.width * 0.6 + (Math.random() - 0.5) * 10,
        height: _canvas.height * 0.8 + (Math.random() - 0.5) * 10,
      }

      // Detect eyes (95% probability if face is detected)
      const eyesDetected = Math.random() < 0.95

      if (eyesDetected) {
        if (!_eyesDetected) {
          _log(3, "Eyes detected")
          _eyesDetected = true
          _triggerEvent("eyesDetected")
        }

        // Simulate eye positions
        _leftEyePosition = {
          x: _facePosition.x - _facePosition.width * 0.15,
          y: _facePosition.y - _facePosition.height * 0.1,
        }

        _rightEyePosition = {
          x: _facePosition.x + _facePosition.width * 0.15,
          y: _facePosition.y - _facePosition.height * 0.1,
        }

        // Simulate blinks (random, about every 3-6 seconds)
        const timeSinceLastBlink = now - _lastBlinkTime
        if (timeSinceLastBlink > 3000 && Math.random() < 0.02) {
          _eyesClosed = true
          _blinkDuration = 100 + Math.random() * 150 // 100-250ms
          _lastBlinkTime = now
          _blinkCount++
          _triggerEvent("blink", { count: _blinkCount, duration: _blinkDuration })

          setTimeout(() => {
            _eyesClosed = false
          }, _blinkDuration)
        }

        // Simulate look direction changes
        const directions = ["center", "left", "right", "up", "down"]
        if (Math.random() < 0.01) {
          // 1% chance to change direction on each check
          const newDirection = directions[Math.floor(Math.random() * directions.length)]
          if (newDirection !== _lookDirection) {
            _lookDirection = newDirection
            _lookDirectionConfidence = 0.5 + Math.random() * 0.5 // 0.5-1.0
            _triggerEvent("lookDirectionChange", {
              direction: _lookDirection,
              confidence: _lookDirectionConfidence,
            })
          }
        }
      } else {
        if (_eyesDetected) {
          _log(3, "Eyes lost")
          _eyesDetected = false
          _triggerEvent("eyesLost")
        }
      }
    } else {
      if (_faceDetected) {
        _log(3, "Face lost")
        _faceDetected = false
        _triggerEvent("faceLost")
      }

      if (_eyesDetected) {
        _log(3, "Eyes lost")
        _eyesDetected = false
        _triggerEvent("eyesLost")
      }
    }

    // Schedule next detection
    setTimeout(_detectFace, _faceDetectionInterval)
  }

  function _startPrediction() {
    if (!_isReady || _isRunning) return

    _log(3, "Starting gaze prediction")
    _isRunning = true

    // Start prediction loop
    _predictionLoop()
  }

  function _stopPrediction() {
    _log(3, "Stopping gaze prediction")
    _isRunning = false
  }

  function _predictionLoop() {
    if (!_isRunning) return

    // Predict gaze only if face and eyes are detected
    if (_faceDetected && _eyesDetected && !_eyesClosed) {
      _predictGaze()

      // Call callback if set
      if (_callbackFunc) {
        _callbackFunc(_currentPrediction)
      }

      // Trigger event
      _triggerEvent("gazeUpdate", _currentPrediction)

      // Check if gaze is off screen
      _checkGazePosition()
    }

    // Draw visualization if needed
    if (_settings.showPredictions) {
      _drawVisualization()
    }

    // Continue loop
    setTimeout(_predictionLoop, _settings.predictionInterval)
  }

  function _predictGaze() {
    // In a real implementation, this would use machine learning models
    // to predict gaze position based on face landmarks

    // For this improved version, we'll simulate gaze based on face position,
    // eye positions, and look direction

    // Base position (center of screen by default)
    let baseX = window.innerWidth / 2
    let baseY = window.innerHeight / 2

    // If we have mouse position, use it as a hint (simulating eye-tracking)
    if (window.mouseX !== undefined && window.mouseY !== undefined) {
      baseX = window.mouseX
      baseY = window.mouseY
    }

    // Adjust based on look direction
    let directionOffsetX = 0
    let directionOffsetY = 0

    switch (_lookDirection) {
      case "left":
        directionOffsetX = -window.innerWidth * 0.2
        break
      case "right":
        directionOffsetX = window.innerWidth * 0.2
        break
      case "up":
        directionOffsetY = -window.innerHeight * 0.2
        break
      case "down":
        directionOffsetY = window.innerHeight * 0.2
        break
    }

    // Add some randomness to simulate prediction errors
    // Less randomness if calibrated
    const randomFactor = _isCalibrated ? 0.05 : 0.15
    const randomX = (Math.random() - 0.5) * window.innerWidth * randomFactor
    const randomY = (Math.random() - 0.5) * window.innerHeight * randomFactor

    // Calculate new prediction
    const newX = baseX + directionOffsetX + randomX
    const newY = baseY + directionOffsetY + randomY

    // Stabilize prediction using history
    _gazeHistory.push({ x: newX, y: newY })
    if (_gazeHistory.length > _maxGazeHistory) {
      _gazeHistory.shift()
    }

    // Average the last few predictions for stability
    // Weight recent predictions more heavily
    let sumX = 0,
      sumY = 0,
      weightSum = 0
    for (let i = 0; i < _gazeHistory.length; i++) {
      // More recent entries have higher weight
      const weight = (i + 1) / _gazeHistory.length
      sumX += _gazeHistory[i].x * weight
      sumY += _gazeHistory[i].y * weight
      weightSum += weight
    }

    _currentPrediction = {
      x: sumX / weightSum,
      y: sumY / weightSum,
      timestamp: Date.now(),
      confidence: _faceDetected && _eyesDetected ? 0.7 + Math.random() * 0.3 : 0.3 + Math.random() * 0.3,
      lookDirection: _lookDirection,
    }

    return _currentPrediction
  }

  function _drawVisualization() {
    if (!_context) return

    // Clear canvas
    _context.clearRect(0, 0, _canvas.width, _canvas.height)

    // Draw face overlay if enabled
    if (_settings.showFaceOverlay && _faceDetected) {
      // Draw face rectangle
      _context.strokeStyle = "green"
      _context.lineWidth = 2
      _context.strokeRect(
        _facePosition.x - _facePosition.width / 2,
        _facePosition.y - _facePosition.height / 2,
        _facePosition.width,
        _facePosition.height,
      )

      // Draw face center point
      _context.fillStyle = "green"
      _context.beginPath()
      _context.arc(_facePosition.x, _facePosition.y, 3, 0, 2 * Math.PI)
      _context.fill()

      // Draw status text
      _context.fillStyle = "white"
      _context.font = "12px Arial"
      _context.fillText(`Face: ${_faceDetected ? "Detected" : "Not Detected"}`, 10, 20)
      _context.fillText(`Eyes: ${_eyesDetected ? "Detected" : "Not Detected"}`, 10, 40)
      _context.fillText(`Look: ${_lookDirection} (${Math.round(_lookDirectionConfidence * 100)}%)`, 10, 60)
      _context.fillText(`Blinks: ${_blinkCount}`, 10, 80)

      if (_errorState) {
        _context.fillStyle = "red"
        _context.fillText(`Error: ${_errorState}`, 10, 100)
      }

      // Draw eye positions if eyes are detected
      if (_eyesDetected && !_eyesClosed) {
        _context.fillStyle = "rgba(0, 255, 0, 0.5)"

        // Left eye
        _context.beginPath()
        _context.arc(_leftEyePosition.x, _leftEyePosition.y, 10, 0, 2 * Math.PI)
        _context.fill()

        // Right eye
        _context.beginPath()
        _context.arc(_rightEyePosition.x, _rightEyePosition.y, 10, 0, 2 * Math.PI)
        _context.fill()
      } else if (_eyesDetected && _eyesClosed) {
        // Draw closed eyes
        _context.strokeStyle = "rgba(255, 255, 0, 0.7)"
        _context.lineWidth = 2

        // Left eye (horizontal line)
        _context.beginPath()
        _context.moveTo(_leftEyePosition.x - 10, _leftEyePosition.y)
        _context.lineTo(_leftEyePosition.x + 10, _leftEyePosition.y)
        _context.stroke()

        // Right eye (horizontal line)
        _context.beginPath()
        _context.moveTo(_rightEyePosition.x - 10, _rightEyePosition.y)
        _context.lineTo(_rightEyePosition.x + 10, _rightEyePosition.y)
        _context.stroke()
      }
    }

    // Draw gaze dot if enabled
    if (_settings.showGazeDot && _faceDetected && _eyesDetected) {
      // Convert screen coordinates to video coordinates for visualization
      const videoX = (_currentPrediction.x / _screenBounds.width) * _canvas.width
      const videoY = (_currentPrediction.y / _screenBounds.height) * _canvas.height

      // Draw gaze point
      _context.fillStyle = "red"
      _context.beginPath()
      _context.arc(videoX, videoY, 5, 0, 2 * Math.PI)
      _context.fill()

      // Draw gaze trail
      if (_gazeHistory.length > 1) {
        _context.strokeStyle = "rgba(255, 0, 0, 0.3)"
        _context.lineWidth = 2
        _context.beginPath()

        const firstPoint = _gazeHistory[0]
        const firstVideoX = (firstPoint.x / _screenBounds.width) * _canvas.width
        const firstVideoY = (firstPoint.y / _screenBounds.height) * _canvas.height

        _context.moveTo(firstVideoX, firstVideoY)

        for (let i = 1; i < _gazeHistory.length; i++) {
          const point = _gazeHistory[i]
          const pointVideoX = (point.x / _screenBounds.width) * _canvas.width
          const pointVideoY = (point.y / _screenBounds.height) * _canvas.height

          _context.lineTo(pointVideoX, pointVideoY)
        }

        _context.stroke()
      }
    }

    // Draw calibration status
    if (_calibrationInProgress) {
      _context.fillStyle = "rgba(0, 0, 255, 0.3)"
      _context.fillRect(0, 0, _canvas.width, _canvas.height)

      _context.fillStyle = "white"
      _context.font = "16px Arial"
      _context.textAlign = "center"
      _context.fillText(
        `Calibration: ${_calibrationPoints.length}/${_minCalibrationPoints} points`,
        _canvas.width / 2,
        _canvas.height / 2,
      )
      _context.textAlign = "start"
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
      _log(3, "Gaze went off screen", _currentPrediction)
      _triggerEvent("gazeOffScreen", _currentPrediction)
    } else if (_checkGazePosition.wasOffScreen && !isOffScreen) {
      _log(3, "Gaze returned to screen", _currentPrediction)
      _triggerEvent("gazeOnScreen", _currentPrediction)
    }

    _checkGazePosition.wasOffScreen = isOffScreen
  }

  // Initialize static variable
  _checkGazePosition.wasOffScreen = false

  function _calibrate(x, y, duration) {
    return new Promise((resolve, reject) => {
      if (!_isReady) {
        const error = new Error("WebGazer is not ready")
        _log(1, error.message)
        reject(error)
        return
      }

      duration = duration || _settings.calibrationDuration
      _calibrationInProgress = true

      _log(3, "Calibrating at point", x, y)

      // Trigger calibration start event if this is the first point
      if (_calibrationPoints.length === 0) {
        _triggerEvent("calibrationStart")
      }

      // Add calibration point
      _calibrationPoints.push({ x, y, timestamp: Date.now() })

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
          _calibrationInProgress = false
          _log(3, "Calibration complete with", _calibrationPoints.length, "points")
          _triggerEvent("calibrationComplete", {
            points: _calibrationPoints,
            accuracy: 0.8 + Math.random() * 0.2, // Simulate accuracy between 80-100%
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

    _log(4, "Triggering event", eventName, data)

    _eventListeners[eventName].forEach((callback) => {
      try {
        callback(data)
      } catch (err) {
        _log(1, `Error in ${eventName} event handler:`, err)
      }
    })
  }

  // Public API
  webgazer.begin = function () {
    _log(3, "Beginning WebGazer")

    return _setupVideo()
      .then(() => {
        _startPrediction()
        return this
      })
      .catch((err) => {
        _log(1, "Error beginning WebGazer:", err)
        throw err
      })
  }

  webgazer.pause = function () {
    _log(3, "Pausing WebGazer")
    _stopPrediction()
    return this
  }

  webgazer.resume = function () {
    _log(3, "Resuming WebGazer")
    _startPrediction()
    return this
  }

  webgazer.end = function () {
    _log(3, "Ending WebGazer")

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
    _faceDetected = false
    _eyesDetected = false
    _errorState = null
    _calibrationInProgress = false

    return this
  }

  // Add setGazeListener method for compatibility with original WebGazer
  webgazer.setGazeListener = function (callback) {
    _log(3, "Setting gaze listener")
    _callbackFunc = callback
    return this
  }

  // Keep setGazeCallback as an alias for setGazeListener
  webgazer.setGazeCallback = (callback) => webgazer.setGazeListener(callback)

  webgazer.getCurrentPrediction = () => _currentPrediction

  // Add showVideoPreview method for compatibility with original WebGazer
  webgazer.showVideoPreview = function (show) {
    _log(3, "Setting video preview visibility to", show)
    _settings.showVideo = show !== false
    if (_videoElement) {
      _videoElement.style.display = _settings.showVideo ? "block" : "none"
    }
    return this
  }

  // Keep showVideo as an alias for showVideoPreview
  webgazer.showVideo = (show) => webgazer.showVideoPreview(show)

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
    _log(3, "Setting tracker to:", tracker)
    return this
  }

  webgazer.setRegressor = function (regressor) {
    // In this simplified version, we don't actually change regressors
    _log(3, "Setting regressor to:", regressor)
    return this
  }

  webgazer.addCalibrationPoint = (x, y, duration) => _calibrate(x, y, duration)

  webgazer.clearCalibration = function () {
    _log(3, "Clearing calibration")
    _calibrationPoints = []
    _isCalibrated = false
    return this
  }

  webgazer.isReady = () => _isReady

  webgazer.isCalibrated = () => _isCalibrated

  webgazer.isFaceDetected = () => _faceDetected

  webgazer.areEyesDetected = () => _eyesDetected

  webgazer.getBlinkCount = () => _blinkCount

  webgazer.getLookDirection = () => ({
    direction: _lookDirection,
    confidence: _lookDirectionConfidence,
  })

  webgazer.on = function (event, callback) {
    if (!_eventListeners[event]) {
      _log(2, "Unknown event type:", event)
      _eventListeners[event] = []
    }

    _log(3, "Adding event listener for", event)
    _eventListeners[event].push(callback)
    return this
  }

  webgazer.off = function (event, callback) {
    if (!_eventListeners[event]) return this

    if (!callback) {
      // Remove all callbacks for this event
      _log(3, "Removing all event listeners for", event)
      _eventListeners[event] = []
    } else {
      // Remove specific callback
      _log(3, "Removing specific event listener for", event)
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

  webgazer.setDebugMode = function (enabled) {
    _debugMode = enabled !== false
    return this
  }

  webgazer.setDebugLevel = function (level) {
    _settings.debugLevel = Math.max(0, Math.min(4, level))
    return this
  }

  webgazer.getScreenBounds = () => _screenBounds

  webgazer.updateScreenBounds = function () {
    _log(3, "Updating screen bounds")
    _screenBounds = {
      width: window.innerWidth,
      height: window.innerHeight,
    }
    return this
  }

  webgazer.getState = () => ({
    isReady: _isReady,
    isRunning: _isRunning,
    isCalibrated: _isCalibrated,
    faceDetected: _faceDetected,
    eyesDetected: _eyesDetected,
    currentPrediction: _currentPrediction,
    calibrationPoints: _calibrationPoints.length,
    blinkCount: _blinkCount,
    lookDirection: _lookDirection,
    errorState: _errorState,
  })

  webgazer.getError = () => _errorState

  webgazer.resetError = function () {
    _errorState = null
    return this
  }

  // Helper methods for proctoring
  webgazer.isLookingAway = () => _lookDirection !== "center"

  webgazer.getAttentionMetrics = () => ({
    lookingAway: _lookDirection !== "center",
    blinkRate: (_blinkCount / (Date.now() - _lastFaceDetectionTime)) * 60000, // blinks per minute
    attentionScore:
      _faceDetected && _eyesDetected ? (_lookDirection === "center" ? 0.9 : 0.3) * (1 - _blinkCount / 100) : 0,
    faceVisible: _faceDetected,
    eyesVisible: _eyesDetected,
  })

  // Expose to window
  window.webgazer = webgazer
})(window)
