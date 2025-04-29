/**
 * Module de surveillance automatisée pour ExamSafe
 * Ce script gère la reconnaissance faciale, le suivi du regard, la surveillance audio et le verrouillage du navigateur
 */

// Configuration
const proctoringConfig = {
    faceDetection: {
      enabled: true,
      checkInterval: 2000, // ms
      confidenceThreshold: 0.8,
      warningThreshold: 3,
    },
    eyeTracking: {
      enabled: true,
      checkInterval: 1000, // ms
      lookAwayThreshold: 2000, // ms
      warningThreshold: 3,
    },
    audioMonitoring: {
      enabled: true,
      checkInterval: 1000, // ms
      volumeThreshold: 0.2,
      warningThreshold: 3,
    },
    screenMonitoring: {
      enabled: true,
      checkInterval: 1000, // ms
      warningThreshold: 2,
    },
  }
  
  // Variables globales
  let webcamStream = null
  let audioStream = null
  let faceDetectionModel = null
  let eyeTrackingModel = null
  let proctoringStatus = "initializing"
  let lastFaceDetection = null
  let lookAwayStartTime = null
  const warningCount = {
    face: 0,
    eyes: 0,
    audio: 0,
    screen: 0,
  }
  const incidentLog = []
  let audioMonitoringInterval = null
  let faceDetectionInterval = null
  let eyeTrackingInterval = null
  
  // Initialisation de la surveillance
  async function initProctoring() {
    try {
      updateStatus("initializing", "Initialisation de la surveillance...")
  
      // Charger les modèles d'IA
      await loadModels()
  
      // Initialiser la webcam
      await initWebcam()
  
      // Initialiser la surveillance audio
      if (proctoringConfig.audioMonitoring.enabled) {
        await initAudioMonitoring()
      }
  
      // Démarrer les différentes surveillances
      startFaceDetection()
      startEyeTracking()
      startScreenMonitoring()
  
      updateStatus("active", "Surveillance active")
    } catch (error) {
      console.error("Erreur d'initialisation de la surveillance:", error)
      updateStatus("error", "Erreur d'initialisation de la surveillance")
      logIncident("initialization_error", "high", error.message)
    }
  }
  
  // Chargement des modèles d'IA
  async function loadModels() {
    return new Promise((resolve) => {
      // Simulation du chargement des modèles
      setTimeout(() => {
        faceDetectionModel = {
          detect: simulateFaceDetection,
        }
  
        eyeTrackingModel = {
          track: simulateEyeTracking,
        }
  
        resolve()
      }, 2000)
    })
  }
  
  // Initialisation de la webcam
  async function initWebcam() {
    try {
      webcamStream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 1280 },
          height: { ideal: 720 },
          facingMode: "user",
        },
      })
  
      const videoElement = document.getElementById("webcam-video")
      videoElement.srcObject = webcamStream
  
      return new Promise((resolve) => {
        videoElement.onloadedmetadata = () => {
          resolve()
        }
      })
    } catch (error) {
      updateStatus("error", "Impossible d'accéder à la webcam")
      logIncident("webcam_access_denied", "high", error.message)
      throw error
    }
  }
  
  // Initialisation de la surveillance audio
  async function initAudioMonitoring() {
    try {
      audioStream = await navigator.mediaDevices.getUserMedia({
        audio: true,
      })
  
      const audioContext = new (window.AudioContext || window.webkitAudioContext)()
      const analyser = audioContext.createAnalyser()
      const microphone = audioContext.createMediaStreamSource(audioStream)
      microphone.connect(analyser)
  
      analyser.fftSize = 256
      const bufferLength = analyser.frequencyBinCount
      const dataArray = new Uint8Array(bufferLength)
  
      // Démarrer la surveillance audio
      audioMonitoringInterval = setInterval(() => {
        analyser.getByteFrequencyData(dataArray)
  
        // Calculer le volume moyen
        let sum = 0
        for (let i = 0; i < bufferLength; i++) {
          sum += dataArray[i]
        }
        const average = sum / bufferLength / 255 // Normaliser entre 0 et 1
  
        // Détecter les sons suspects
        if (average > proctoringConfig.audioMonitoring.volumeThreshold) {
          handleAudioDetection(average)
        }
      }, proctoringConfig.audioMonitoring.checkInterval)
    } catch (error) {
      console.error("Erreur d'initialisation de la surveillance audio:", error)
      logIncident("audio_access_denied", "medium", error.message)
    }
  }
  
  // Démarrer la détection faciale
  function startFaceDetection() {
    if (!proctoringConfig.faceDetection.enabled) return
  
    faceDetectionInterval = setInterval(async () => {
      if (!webcamStream || !faceDetectionModel) return
  
      try {
        const videoElement = document.getElementById("webcam-video")
        const result = await faceDetectionModel.detect(videoElement)
  
        if (result.length === 0) {
          // Aucun visage détecté
          handleNoFaceDetected()
        } else if (result.length > 1) {
          // Plusieurs visages détectés
          handleMultipleFacesDetected(result.length)
        } else {
          // Un visage détecté
          lastFaceDetection = Date.now()
          warningCount.face = Math.max(0, warningCount.face - 1)
  
          // Vérifier la confiance de la détection
          if (result[0].confidence < proctoringConfig.faceDetection.confidenceThreshold) {
            handleLowConfidenceFaceDetection(result[0].confidence)
          }
        }
      } catch (error) {
        console.error("Erreur de détection faciale:", error)
      }
    }, proctoringConfig.faceDetection.checkInterval)
  }
  
  // Démarrer le suivi du regard
  function startEyeTracking() {
    if (!proctoringConfig.eyeTracking.enabled) return
  
    eyeTrackingInterval = setInterval(async () => {
      if (!webcamStream || !eyeTrackingModel) return
  
      try {
        const videoElement = document.getElementById("webcam-video")
        const result = await eyeTrackingModel.track(videoElement)
  
        if (result.lookingAway) {
          // L'étudiant regarde ailleurs
          handleLookingAway(result)
        } else {
          // L'étudiant regarde l'écran
          lookAwayStartTime = null
          warningCount.eyes = Math.max(0, warningCount.eyes - 1)
        }
      } catch (error) {
        console.error("Erreur de suivi du regard:", error)
      }
    }, proctoringConfig.eyeTracking.checkInterval)
  }
  
  // Démarrer la surveillance de l'écran
  function startScreenMonitoring() {
    if (!proctoringConfig.screenMonitoring.enabled) return
  
    // Surveiller les changements d'onglet
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        handleTabSwitch()
      }
    })
  
    // Surveiller les tentatives de copier-coller
    document.addEventListener("copy", handleCopyPaste)
    document.addEventListener("paste", handleCopyPaste)
    document.addEventListener("cut", handleCopyPaste)
  
    // Surveiller le redimensionnement de la fenêtre
    let originalWidth = window.innerWidth
    let originalHeight = window.innerHeight
  
    window.addEventListener("resize", () => {
      const widthDiff = Math.abs(window.innerWidth - originalWidth)
      const heightDiff = Math.abs(window.innerHeight - originalHeight)
  
      if (widthDiff > 100 || heightDiff > 100) {
        handleWindowResize(widthDiff, heightDiff)
        originalWidth = window.innerWidth
        originalHeight = window.innerHeight
      }
    })
  }
  
  // Gestionnaires d'incidents
  function handleNoFaceDetected() {
    const now = Date.now()
  
    if (lastFaceDetection && now - lastFaceDetection > 3000) {
      warningCount.face++
  
      if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
        updateStatus("warning", "Visage non détecté")
        logIncident("face_not_detected", "high", "Visage non détecté pendant plus de 3 secondes")
        showWarning("Votre visage n'est pas visible. Veuillez vous assurer que vous êtes bien cadré dans la webcam.")
        warningCount.face = 0
      }
    }
  }
  
  function handleMultipleFacesDetected(count) {
    warningCount.face++
  
    if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
      updateStatus("warning", "Plusieurs visages détectés")
      logIncident("multiple_faces", "high", `${count} visages détectés`)
      showWarning(
        "Plusieurs personnes ont été détectées dans le champ de la caméra. Veuillez vous assurer d'être seul pendant l'examen.",
      )
      warningCount.face = 0
    }
  }
  
  function handleLowConfidenceFaceDetection(confidence) {
    warningCount.face++
  
    if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
      updateStatus("warning", "Visage partiellement visible")
      logIncident("low_confidence_face", "medium", `Confiance de détection: ${confidence.toFixed(2)}`)
      showWarning("Votre visage n'est que partiellement visible. Veuillez ajuster votre position face à la caméra.")
      warningCount.face = 0
    }
  }
  
  function handleLookingAway(result) {
    const now = Date.now()
  
    if (!lookAwayStartTime) {
      lookAwayStartTime = now
    } else if (now - lookAwayStartTime > proctoringConfig.eyeTracking.lookAwayThreshold) {
      warningCount.eyes++
  
      if (warningCount.eyes >= proctoringConfig.eyeTracking.warningThreshold) {
        updateStatus("warning", "Regard détourné")
        logIncident(
          "looking_away",
          "medium",
          `Direction du regard: ${result.direction}, durée: ${(now - lookAwayStartTime) / 1000}s`,
        )
        showWarning("Vous semblez regarder ailleurs que votre écran. Veuillez vous concentrer sur votre examen.")
        warningCount.eyes = 0
      }
    }
  }
  
  function handleAudioDetection(volume) {
    warningCount.audio++
  
    if (warningCount.audio >= proctoringConfig.audioMonitoring.warningThreshold) {
      updateStatus("warning", "Son détecté")
      logIncident("audio_detected", "medium", `Volume: ${volume.toFixed(2)}`)
      showWarning(
        "Des sons ont été détectés dans votre environnement. Veuillez vous assurer d'être dans un endroit calme.",
      )
      warningCount.audio = 0
    }
  }
  
  function handleTabSwitch() {
    warningCount.screen++
  
    updateStatus("warning", "Changement d'onglet")
    logIncident("tab_switch", "high", "L'étudiant a changé d'onglet ou de fenêtre")
    showWarning(
      "Vous avez quitté l'onglet de l'examen. Cette action est enregistrée et peut être considérée comme une tentative de triche.",
    )
  }
  
  function handleCopyPaste(event) {
    event.preventDefault()
  
    warningCount.screen++
  
    updateStatus("warning", "Copier-coller détecté")
    logIncident("copy_paste", "medium", `Action: ${event.type}`)
    showWarning("Les actions de copier-coller sont désactivées pendant l'examen.")
  }
  
  function handleWindowResize(widthDiff, heightDiff) {
    warningCount.screen++
  
    if (warningCount.screen >= proctoringConfig.screenMonitoring.warningThreshold) {
      updateStatus("warning", "Redimensionnement de fenêtre")
      logIncident("window_resize", "medium", `Différence de taille: ${widthDiff}x${heightDiff}px`)
      showWarning("Vous avez redimensionné la fenêtre de l'examen. Cette action est enregistrée.")
      warningCount.screen = 0
    }
  }
  
  // Fonctions utilitaires
  function updateStatus(status, message) {
    proctoringStatus = status
  
    const statusIndicator = document.getElementById("status-indicator")
    const statusText = document.getElementById("status-text")
  
    if (statusIndicator && statusText) {
      statusText.textContent = message
  
      statusIndicator.className = "status-indicator"
      if (status === "warning") {
        statusIndicator.classList.add("warning")
      } else if (status === "error") {
        statusIndicator.classList.add("error")
      }
    }
  }
  
  function showWarning(message) {
    const warningMessage = document.getElementById("warning-message")
    if (warningMessage) {
      warningMessage.textContent = message
    }
  
    showModal("warning-modal")
  
    // Envoyer l'avertissement au serveur
    sendWarningToServer(message)
  }
  
  function logIncident(type, severity, details) {
    const incident = {
      type,
      severity,
      details,
      timestamp: new Date().toISOString(),
    }
  
    incidentLog.push(incident)
  
    // Envoyer l'incident au serveur
    sendIncidentToServer(incident)
  }
  
  function sendWarningToServer(message) {
    const attemptId = document.querySelector('input[name="attempt_id"]').value
  
    fetch("../api/proctoring.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "warning",
        attempt_id: attemptId,
        message: message,
      }),
    }).catch((error) => {
      console.error("Erreur d'envoi d'avertissement:", error)
    })
  }
  
  function sendIncidentToServer(incident) {
    const attemptId = document.querySelector('input[name="attempt_id"]').value
  
    fetch("../api/proctoring.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "incident",
        attempt_id: attemptId,
        incident: incident,
      }),
    }).catch((error) => {
      console.error("Erreur d'envoi d'incident:", error)
    })
  }
  
  // Fonctions de simulation pour les démonstrations
  function simulateFaceDetection() {
    return new Promise((resolve) => {
      // Simuler différents scénarios de détection faciale
      const scenarios = [
        // Visage détecté avec haute confiance (90% du temps)
        { result: [{ confidence: 0.95 }], probability: 0.9 },
        // Visage détecté avec faible confiance (5% du temps)
        { result: [{ confidence: 0.6 }], probability: 0.05 },
        // Aucun visage détecté (3% du temps)
        { result: [], probability: 0.03 },
        // Plusieurs visages détectés (2% du temps)
        { result: [{ confidence: 0.9 }, { confidence: 0.85 }], probability: 0.02 },
      ]
  
      const random = Math.random()
      let cumulativeProbability = 0
  
      for (const scenario of scenarios) {
        cumulativeProbability += scenario.probability
        if (random <= cumulativeProbability) {
          resolve(scenario.result)
          return
        }
      }
  
      // Par défaut, retourner un visage détecté
      resolve([{ confidence: 0.95 }])
    })
  }
  
  function simulateEyeTracking() {
    return new Promise((resolve) => {
      // Simuler différents scénarios de suivi du regard
      const scenarios = [
        // Regarde l'écran (95% du temps)
        { result: { lookingAway: false }, probability: 0.95 },
        // Regarde ailleurs (5% du temps)
        { result: { lookingAway: true, direction: "right" }, probability: 0.05 },
      ]
  
      const random = Math.random()
      let cumulativeProbability = 0
  
      for (const scenario of scenarios) {
        cumulativeProbability += scenario.probability
        if (random <= cumulativeProbability) {
          resolve(scenario.result)
          return
        }
      }
  
      // Par défaut, retourner que l'étudiant regarde l'écran
      resolve({ lookingAway: false })
    })
  }
  
  // Nettoyage des ressources
  function cleanupProctoring() {
    // Arrêter les intervalles
    if (faceDetectionInterval) clearInterval(faceDetectionInterval)
    if (eyeTrackingInterval) clearInterval(eyeTrackingInterval)
    if (audioMonitoringInterval) clearInterval(audioMonitoringInterval)
  
    // Arrêter les flux
    if (webcamStream) {
      webcamStream.getTracks().forEach((track) => track.stop())
    }
  
    if (audioStream) {
      audioStream.getTracks().forEach((track) => track.stop())
    }
  }
  
  // Fonctions d'interface utilisateur
  function showModal(modalId) {
    const modal = document.getElementById(modalId)
    if (modal) {
      modal.style.display = "flex"
    }
  }
  
  function hideModal(modalId) {
    const modal = document.getElementById(modalId)
    if (modal) {
      modal.style.display = "none"
    }
  }
  