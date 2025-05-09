/**
 * Système de surveillance avancé pour la plateforme d'examens en ligne
 * Intègre Face-API.js, WebGazer.js, Web Audio API et Page Visibility API
 */

// Configuration globale
const FACE_MODELS_PATH = "../assets/models" // Chemin vers les modèles Face-API.js
const FACE_MATCH_THRESHOLD = 0.6 // Seuil de correspondance faciale (0.6 est un bon compromis)
const FACE_CHECK_INTERVAL = 3000 // Vérification du visage toutes les 3 secondes
const GAZE_OUT_OF_BOUNDS_THRESHOLD = 3000 // 3 secondes de regard hors zone
const AUDIO_CHECK_INTERVAL = 1000 // Vérification audio toutes les secondes
const AUDIO_THRESHOLD = 0.2 // Seuil de volume audio (0-1)
const CONSECUTIVE_AUDIO_VIOLATIONS_THRESHOLD = 3 // Nombre de violations audio consécutives avant signalement
const INACTIVITY_THRESHOLD = 30000 // 30 secondes d'inactivité avant signalement
const FACE_DETECTION_CONFIDENCE = 0.5 // Seuil de confiance pour la détection faciale

// Variables globales
let faceDetectionInterval
let gazeCheckInterval
let audioCheckInterval
let screenCheckInterval
let referenceDescriptor = null
let lastGazeCheck = Date.now()
let gazeOutOfBoundsTime = 0
let consecutiveAudioViolations = 0
let visibilityChangeCount = 0
let lastActiveTime = Date.now()
let incidentCount = 0
let calibrationComplete = false
let proctoringActive = false

// Initialisation du système de surveillance
async function initProctoring() {
  try {
    // Afficher l'indicateur de chargement
    showLoadingIndicator()

    // Initialiser les modules de surveillance
    await Promise.all([initFaceRecognition(), initEyeTracking(), initAudioMonitoring(), initScreenMonitoring()])

    // Masquer l'indicateur de chargement
    hideLoadingIndicator()

    proctoringActive = true
    console.log("Système de surveillance initialisé avec succès")

    // Afficher une notification de succès
    showProctoringNotification(
      "Surveillance active",
      "Tous les systèmes de surveillance sont maintenant actifs.",
      "success",
    )
  } catch (error) {
    console.error("Erreur lors de l'initialisation du système de surveillance:", error)
    showProctoringNotification(
      "Erreur de surveillance",
      "Une erreur est survenue lors de l'initialisation du système de surveillance.",
      "error",
    )
    hideLoadingIndicator()
  }
}

// Afficher l'indicateur de chargement
function showLoadingIndicator() {
  // Créer l'élément de chargement s'il n'existe pas
  if (!document.getElementById("proctoring-loading")) {
    const loadingElement = document.createElement("div")
    loadingElement.id = "proctoring-loading"
    loadingElement.className = "proctoring-loading"
    loadingElement.innerHTML = `
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Chargement des systèmes de surveillance...</p>
            </div>
        `
    document.body.appendChild(loadingElement)
  }

  document.getElementById("proctoring-loading").style.display = "flex"
}

// Masquer l'indicateur de chargement
function hideLoadingIndicator() {
  const loadingElement = document.getElementById("proctoring-loading")
  if (loadingElement) {
    loadingElement.style.display = "none"
  }
}

// Initialisation de la reconnaissance faciale avec Face-API.js
async function initFaceRecognition() {
 // Vérifier si Face-API.js est disponible
    if (typeof faceapi === "undefined") {
      throw new Error("Face-API.js n'est pas chargé")
    }

    updateProctoringStatus("face", "initializing", "Chargement des modèles...")

    // Charger les modèles nécessaires
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODELS_PATH),
      faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODELS_PATH),
      faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODELS_PATH),
      faceapi.nets.faceExpressionNet.loadFromUri(FACE_MODELS_PATH),
    ])

    console.log("Modèles de reconnaissance faciale chargés")
    updateProctoringStatus("face", "initializing", "Accès à la webcam...")

    // Accéder à la webcam
    const video = document.getElementById("webcam")
    if (!video) {
      throw new Error("Élément vidéo non trouvé")
    }

    const stream =
      video.srcObject ||
      (await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          facingMode: "user",
        },
      }))

    if (!video.srcObject) {
      video.srcObject = stream
      await new Promise((resolve) => {
        video.onloadedmetadata = () => {
          video.play().then(resolve)
        }
      })
    }

    // Configurer le canvas pour l'affichage des résultats
    const canvas = document.getElementById("canvas")
    if (!canvas) {
      throw new Error("Élément canvas non trouvé")
    }

    canvas.width = video.videoWidth || 640
    canvas.height = video.videoHeight || 480

    updateProctoringStatus("face", "initializing", "Capture de l'image de référence...")

    // Capturer une image de référence au début
    const referenceSuccess = await captureReferenceImage()
    if (!referenceSuccess) {
      throw new Error("Échec de la capture de l'image de référence")
    }

    // Démarrer la détection périodique
    faceDetectionInterval = setInterval(checkFace, FACE_CHECK_INTERVAL)

    // Mettre à jour le statut
    updateProctoringStatus("face", "active")

    return true
}

// Capture d'une image de référence pour la reconnaissance faciale
async function captureReferenceImage() {
  const video = document.getElementById("webcam")
  if (!video) return false

  // Attendre que la vidéo soit chargée
  if (video.readyState !== 4) {
    await new Promise((resolve) => {
      video.onloadeddata = () => resolve()
    })
  }

  // Essayer plusieurs fois de détecter un visage
  let attempts = 0
  const maxAttempts = 5

  while (attempts < maxAttempts) {
    try {
      // Détecter le visage et extraire le descripteur
      const detection = await faceapi
        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: FACE_DETECTION_CONFIDENCE }))
        .withFaceLandmarks()
        .withFaceDescriptor()

      if (detection) {
        referenceDescriptor = detection.descriptor
        console.log("Image de référence capturée avec succès")

        // Capturer l'image pour référence
        const canvas = document.createElement("canvas")
        canvas.width = video.videoWidth
        canvas.height = video.videoHeight
        const ctx = canvas.getContext("2d")
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

        // Sauvegarder l'image de référence
        const imageData = canvas.toDataURL("image/jpeg", 0.8)
        saveReferenceImage(imageData)

        showProctoringNotification(
          "Image de référence capturée",
          "Votre visage a été enregistré pour la surveillance.",
          "success",
        )

        return true
      }

      attempts++
      await new Promise((resolve) => setTimeout(resolve, 1000)) // Attendre 1 seconde avant de réessayer
    } catch (error) {
      console.error("Erreur lors de la capture de l'image de référence:", error)
      attempts++
      await new Promise((resolve) => setTimeout(resolve, 1000))
    }
  }

  console.error("Aucun visage détecté après plusieurs tentatives")
  showProctoringNotification(
    "Erreur de reconnaissance faciale",
    "Aucun visage détecté. Veuillez vous assurer que votre visage est bien visible.",
    "warning",
  )

  return false
}

// Sauvegarder l'image de référence
function saveReferenceImage(imageData) {
  const attemptId = document.querySelector("#questionContainer").dataset.attemptId

  fetch("../ajax/save-reference-image.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      attempt_id: attemptId,
      image_data: imageData,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Image de référence sauvegardée:", data)
    })
    .catch((error) => {
      console.error("Erreur lors de la sauvegarde de l'image de référence:", error)
    })
}

// Vérification périodique du visage
async function checkFace() {
  const video = document.getElementById("webcam")
  const canvas = document.getElementById("canvas")
  if (!video || !canvas) return

  const context = canvas.getContext("2d")

  // Effacer le canvas
  context.clearRect(0, 0, canvas.width, canvas.height)

  try {
    // Détecter le visage
    const detection = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: FACE_DETECTION_CONFIDENCE }))
      .withFaceLandmarks()
      .withFaceDescriptor()
      .withFaceExpressions()

    if (detection) {
      // Dessiner le cadre du visage
      const box = detection.detection.box
      context.strokeStyle = "#00ff00"
      context.lineWidth = 2
      context.strokeRect(box.x, box.y, box.width, box.height)

      // Dessiner les points de repère du visage
      const landmarks = detection.landmarks
      const positions = landmarks.positions

      context.fillStyle = "#00ff00"
      positions.forEach((point) => {
        context.beginPath()
        context.arc(point.x, point.y, 2, 0, 2 * Math.PI)
        context.fill()
      })

      // Vérifier la correspondance avec l'image de référence
      if (referenceDescriptor) {
        const distance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor)
        const match = distance < FACE_MATCH_THRESHOLD

        if (!match) {
          // Visage différent détecté
          context.strokeStyle = "#ff0000"
          context.strokeRect(box.x, box.y, box.width, box.height)
          updateProctoringStatus("face", "warning", "Visage non reconnu")
          reportProctoringIncident("face", "Visage différent détecté (distance: " + distance.toFixed(2) + ")")
          captureAndSaveImage("face", "Visage différent détecté")
        } else {
          updateProctoringStatus("face", "active")

          // Vérifier les expressions faciales
          const expressions = detection.expressions
          const dominantExpression = Object.keys(expressions).reduce((a, b) =>
            expressions[a] > expressions[b] ? a : b,
          )

          // Si l'expression dominante est la surprise ou la colère avec une confiance élevée
          if (
            (dominantExpression === "surprised" || dominantExpression === "angry") &&
            expressions[dominantExpression] > 0.7
          ) {
            updateProctoringStatus("face", "warning", `Expression faciale: ${dominantExpression}`)
            reportProctoringIncident("face", `Expression faciale suspecte détectée: ${dominantExpression}`)
            captureAndSaveImage("face", `Expression faciale: ${dominantExpression}`)
          }
        }
      }
    } else {
      // Aucun visage détecté
      updateProctoringStatus("face", "warning", "Aucun visage détecté")
      reportProctoringIncident("face", "Aucun visage détecté dans le champ de la caméra")
      captureAndSaveImage("face", "Aucun visage détecté")
    }
  } catch (error) {
    console.error("Erreur lors de la vérification du visage:", error)
    updateProctoringStatus("face", "error", "Erreur de détection")
  }
}

// Initialisation du suivi oculaire avec WebGazer.js
async function initEyeTracking() {
  try {
    // Vérifier si WebGazer est disponible
    if (typeof webgazer === "undefined") {
      console.error("WebGazer.js n'est pas chargé")
      updateProctoringStatus("gaze", "error", "WebGazer non disponible")
      return false
    }

    updateProctoringStatus("gaze", "initializing", "Initialisation...")

    // Initialiser WebGazer
    await webgazer.setGazeListener(handleGazeData).begin()

    // Désactiver l'affichage vidéo et des points de prédiction par défaut
    webgazer.showVideoPreview(false).showPredictionPoints(false)

    // Afficher le modal de calibration
    showCalibrationModal()

    // Mettre à jour le statut
    updateProctoringStatus("gaze", "calibrating", "Calibration requise")

    return true
  } catch (error) {
    console.error("Erreur lors de l'initialisation du suivi oculaire:", error)
    updateProctoringStatus("gaze", "error", error.message)
    reportProctoringIncident("gaze", "Erreur d'initialisation du suivi oculaire: " + error.message)
    throw error
  }
}

// Afficher le modal de calibration du suivi oculaire
function showCalibrationModal() {
  // Créer le modal s'il n'existe pas
  if (!document.getElementById("calibration-modal")) {
    const modalElement = document.createElement("div")
    modalElement.id = "calibration-modal"
    modalElement.className = "proctoring-modal"
    modalElement.innerHTML = `
            <div class="modal-content">
                <h2>Calibration du suivi oculaire</h2>
                <p>Pour une surveillance précise, veuillez suivre le point qui apparaîtra à l'écran.</p>
                <div id="calibration-points"></div>
                <div class="modal-actions">
                    <button id="start-calibration" class="btn btn-primary">Commencer la calibration</button>
                </div>
            </div>
        `
    document.body.appendChild(modalElement)

    // Ajouter l'événement au bouton de calibration
    document.getElementById("start-calibration").addEventListener("click", startCalibration)
  }

  document.getElementById("calibration-modal").style.display = "flex"
}

// Démarrer la calibration du suivi oculaire
function startCalibration() {
  // Masquer le modal
  document.getElementById("calibration-modal").style.display = "none"

  // Points de calibration (positions en pourcentage)
  const calibrationPoints = [
    { top: "20%", left: "20%" },
    { top: "20%", left: "80%" },
    { top: "80%", left: "20%" },
    { top: "80%", left: "80%" },
    { top: "50%", left: "50%" },
  ]

  // Créer l'élément de point de calibration s'il n'existe pas
  if (!document.getElementById("calibration-point")) {
    const pointElement = document.createElement("div")
    pointElement.id = "calibration-point"
    pointElement.className = "calibration-point"
    document.body.appendChild(pointElement)
  }

  const point = document.getElementById("calibration-point")
  let currentPointIndex = 0

  // Fonction pour afficher le point suivant
  function showNextPoint() {
    if (currentPointIndex >= calibrationPoints.length) {
      // Calibration terminée
      point.style.display = "none"
      calibrationComplete = true
      updateProctoringStatus("gaze", "active")
      showProctoringNotification("Calibration terminée", "Le suivi oculaire est maintenant actif.", "success")

      // Démarrer la vérification périodique du regard
      gazeCheckInterval = setInterval(() => {
        if (gazeOutOfBoundsTime >= GAZE_OUT_OF_BOUNDS_THRESHOLD) {
          updateProctoringStatus("gaze", "warning", "Regard hors zone")
          reportProctoringIncident("gaze", "Regard hors zone pendant plus de 3 secondes")
          captureAndSaveImage("gaze", "Regard hors zone")
          gazeOutOfBoundsTime = 0 // Réinitialiser pour éviter des rapports multiples
        }
      }, 1000)

      return
    }

    // Positionner le point
    point.style.top = calibrationPoints[currentPointIndex].top
    point.style.left = calibrationPoints[currentPointIndex].left
    point.style.display = "block"

    // Animation du point
    point.classList.add("pulse")

    // Passer au point suivant après 2 secondes
    setTimeout(() => {
      point.classList.remove("pulse")
      currentPointIndex++
      showNextPoint()
    }, 2000)
  }

  // Commencer la calibration
  showNextPoint()
}

// Traiter les données de regard
function handleGazeData(data, elapsedTime) {
  if (!data || !calibrationComplete) return

  // Vérifier si le regard est dans les limites de l'écran d'examen
  const examContent = document.querySelector(".exam-content") || document.querySelector(".question-container")
  if (!examContent) return

  const rect = examContent.getBoundingClientRect()
  const isGazeInBounds = data.x >= rect.left && data.x <= rect.right && data.y >= rect.top && data.y <= rect.bottom

  // Dessiner le point de regard si un canvas est disponible
  const gazeCanvas = document.getElementById("gaze-canvas")
  if (gazeCanvas) {
    const ctx = gazeCanvas.getContext("2d")
    gazeCanvas.width = window.innerWidth
    gazeCanvas.height = window.innerHeight
    ctx.clearRect(0, 0, gazeCanvas.width, gazeCanvas.height)

    ctx.beginPath()
    ctx.arc(data.x, data.y, 10, 0, 2 * Math.PI)
    ctx.fillStyle = isGazeInBounds ? "rgba(0, 255, 0, 0.5)" : "rgba(255, 0, 0, 0.5)"
    ctx.fill()
  }

  // Mettre à jour le temps hors limites
  const now = Date.now()
  const elapsed = now - lastGazeCheck
  lastGazeCheck = now

  if (!isGazeInBounds) {
    gazeOutOfBoundsTime += elapsed
  } else {
    gazeOutOfBoundsTime = 0
    updateProctoringStatus("gaze", "active")
  }
}

// Initialisation de la surveillance audio
async function initAudioMonitoring() {
  try {
    updateProctoringStatus("audio", "initializing", "Accès au microphone...")

    // Accéder au microphone
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
    })

    // Créer le contexte audio
    const audioContext = new (window.AudioContext || window.webkitAudioContext)()
    const source = audioContext.createMediaStreamSource(stream)
    const analyser = audioContext.createAnalyser()
    analyser.fftSize = 256

    source.connect(analyser)

    // Créer le tableau pour les données audio
    const dataArray = new Uint8Array(analyser.frequencyBinCount)

    // Démarrer la surveillance audio
    audioCheckInterval = setInterval(() => {
      // Obtenir les données audio
      analyser.getByteFrequencyData(dataArray)

      // Calculer le niveau sonore moyen (0-255)
      let sum = 0
      for (let i = 0; i < dataArray.length; i++) {
        sum += dataArray[i]
      }
      const average = sum / dataArray.length

      // Normaliser entre 0 et 1
      const normalizedVolume = average / 255

      // Mettre à jour l'indicateur de volume si disponible
      const volumeIndicator = document.getElementById("audio-volume-indicator")
      if (volumeIndicator) {
        volumeIndicator.style.width = normalizedVolume * 100 + "%"
        volumeIndicator.style.backgroundColor = normalizedVolume > AUDIO_THRESHOLD ? "#ff0000" : "#00ff00"
      }

      // Vérifier si le niveau sonore dépasse le seuil
      if (normalizedVolume > AUDIO_THRESHOLD) {
        consecutiveAudioViolations++

        if (consecutiveAudioViolations >= CONSECUTIVE_AUDIO_VIOLATIONS_THRESHOLD) {
          updateProctoringStatus("audio", "warning", "Niveau sonore élevé")
          reportProctoringIncident("audio", "Niveau sonore élevé détecté")
          consecutiveAudioViolations = 0
        }
      } else {
        consecutiveAudioViolations = 0
        updateProctoringStatus("audio", "active")
      }
    }, AUDIO_CHECK_INTERVAL)

    // Mettre à jour le statut
    updateProctoringStatus("audio", "active")

    return true
  } catch (error) {
    console.error("Erreur lors de l'initialisation de la surveillance audio:", error)
    updateProctoringStatus("audio", "error", "Accès au micro refusé")
    reportProctoringIncident("audio", "Erreur d'accès au microphone: " + error.message)
    throw error
  }
}

// Initialisation de la surveillance d'écran
function initScreenMonitoring() {
  try {
    updateProctoringStatus("screen", "initializing")

    // Détecter les changements d'onglet/fenêtre
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden") {
        visibilityChangeCount++
        updateProctoringStatus("screen", "warning", "Changement d'onglet détecté")
        reportProctoringIncident("screen", "Changement d'onglet ou de fenêtre détecté")
      } else {
        updateProctoringStatus("screen", "active")
      }
    })

    // Empêcher le copier-coller
    document.addEventListener("copy", (e) => {
      e.preventDefault()
      updateProctoringStatus("screen", "warning", "Tentative de copie")
      reportProctoringIncident("screen", "Tentative de copie détectée")
    })

    document.addEventListener("paste", (e) => {
      e.preventDefault()
      updateProctoringStatus("screen", "warning", "Tentative de collage")
      reportProctoringIncident("screen", "Tentative de collage détectée")
    })

    // Empêcher le clic droit
    document.addEventListener("contextmenu", (e) => {
      e.preventDefault()
      updateProctoringStatus("screen", "warning", "Menu contextuel")
      reportProctoringIncident("screen", "Tentative d'ouverture du menu contextuel")
    })

    // Vérifier l'activité de l'utilisateur
    document.addEventListener("mousemove", resetActivityTime)
    document.addEventListener("keydown", resetActivityTime)
    document.addEventListener("click", resetActivityTime)
    document.addEventListener("scroll", resetActivityTime)

    // Vérifier l'inactivité périodiquement
    screenCheckInterval = setInterval(() => {
      const now = Date.now()
      const inactiveTime = now - lastActiveTime

      // Si l'utilisateur est inactif depuis plus de 30 secondes
      if (inactiveTime > INACTIVITY_THRESHOLD) {
        updateProctoringStatus("screen", "warning", "Inactivité détectée")
        reportProctoringIncident("screen", "Inactivité prolongée détectée")
        lastActiveTime = now // Réinitialiser pour éviter des rapports multiples
      }
    }, 10000)

    // Détecter les tentatives de capture d'écran (fonctionne dans certains navigateurs)
    window.addEventListener("keydown", (e) => {
      // Combinaisons de touches pour les captures d'écran
      const isPrintScreen = e.key === "PrintScreen"
      const isCtrlShiftI = e.ctrlKey && e.shiftKey && (e.key === "i" || e.key === "I")
      const isCtrlShiftJ = e.ctrlKey && e.shiftKey && (e.key === "j" || e.key === "J")
      const isCtrlShiftC = e.ctrlKey && e.shiftKey && (e.key === "c" || e.key === "C")
      const isF12 = e.key === "F12"

      if (isPrintScreen || isCtrlShiftI || isCtrlShiftJ || isCtrlShiftC || isF12) {
        e.preventDefault()
        updateProctoringStatus("screen", "warning", "Tentative de capture d'écran")
        reportProctoringIncident("screen", "Tentative de capture d'écran ou d'ouverture des outils de développement")
        return false
      }
    })

    // Mettre à jour le statut
    updateProctoringStatus("screen", "active")

    return true
  } catch (error) {
    console.error("Erreur lors de l'initialisation de la surveillance d'écran:", error)
    updateProctoringStatus("screen", "error", error.message)
    throw error
  }
}

// Réinitialiser le temps d'activité
function resetActivityTime() {
  lastActiveTime = Date.now()
}

// Mettre à jour le statut d'un module de surveillance
function updateProctoringStatus(module, status, message = "") {
  const statusElement = document.getElementById(`${module}-status`)
  if (!statusElement) return

  // Définir l'icône et la classe en fonction du statut
  let icon, statusClass
  switch (status) {
    case "active":
      icon = "fa-check-circle"
      statusClass = "status-active"
      message = message || "Actif"
      break
    case "warning":
      icon = "fa-exclamation-triangle"
      statusClass = "status-warning"
      break
    case "error":
      icon = "fa-times-circle"
      statusClass = "status-error"
      message = message || "Erreur"
      break
    case "calibrating":
      icon = "fa-sync fa-spin"
      statusClass = "status-calibrating"
      message = message || "Calibration en cours"
      break
    case "initializing":
      icon = "fa-spinner fa-spin"
      statusClass = "status-initializing"
      message = message || "Initialisation..."
      break
    default:
      icon = "fa-question-circle"
      statusClass = ""
  }

  // Mettre à jour le contenu
  statusElement.className = `status-item ${statusClass}`
  statusElement.innerHTML = `<i class="fas ${icon}"></i> ${getModuleName(module)}: ${message}`
}

// Obtenir le nom d'un module de surveillance
function getModuleName(module) {
  switch (module) {
    case "face":
      return "Reconnaissance faciale"
    case "gaze":
      return "Suivi oculaire"
    case "audio":
      return "Surveillance audio"
    case "screen":
      return "Surveillance d'écran"
    default:
      return module
  }
}

// Signaler un incident de surveillance
function reportProctoringIncident(type, description) {
  // Ne pas signaler d'incidents si la surveillance n'est pas active
  if (!proctoringActive) return

  // Incrémenter le compteur d'incidents
  incidentCount++

  // Mettre à jour le compteur d'incidents dans l'interface
  const warningsContainer = document.getElementById("proctoringWarnings")
  if (warningsContainer) {
    const warningCountElement = warningsContainer.querySelector(".warning-count")
    if (warningCountElement) {
      warningCountElement.textContent = incidentCount
    }
  }

  // Afficher une notification
  showProctoringNotification("Alerte de surveillance", description, "warning")

  // Enregistrer l'incident dans la base de données
  const attemptId = document.querySelector("#questionContainer").dataset.attemptId

  fetch("../ajax/report-incident.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      attempt_id: attemptId,
      incident_type: type,
      description: description,
      timestamp: new Date().toISOString(),
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Incident signalé:", data)
    })
    .catch((error) => {
      console.error("Erreur lors du signalement de l'incident:", error)
    })
}

// Capturer et sauvegarder une image d'incident
function captureAndSaveImage(incidentType, description) {
  const video = document.getElementById("webcam")
  if (!video) return

  const canvas = document.createElement("canvas")
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight

  const ctx = canvas.getContext("2d")
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

  // Convertir en base64
  const imageData = canvas.toDataURL("image/jpeg", 0.7)

  // Envoyer l'image au serveur
  const attemptId = document.querySelector("#questionContainer").dataset.attemptId

  fetch("../ajax/save-incident-image.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      attempt_id: attemptId,
      incident_type: incidentType,
      description: description,
      image_data: imageData,
      timestamp: new Date().toISOString(),
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Image d'incident sauvegardée:", data)
    })
    .catch((error) => {
      console.error("Erreur lors de la sauvegarde de l'image d'incident:", error)
    })
}

// Afficher une notification de surveillance
function showProctoringNotification(title, message, type = "info") {
  // Créer l'élément de notification
  const notification = document.createElement("div")
  notification.className = `proctoring-notification notification-${type}`

  // Définir l'icône en fonction du type
  let icon
  switch (type) {
    case "success":
      icon = "fa-check-circle"
      break
    case "warning":
      icon = "fa-exclamation-triangle"
      break
    case "error":
      icon = "fa-times-circle"
      break
    default:
      icon = "fa-info-circle"
  }

  // Définir le contenu
  notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${icon}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
    `

  // Ajouter au document
  document.body.appendChild(notification)

  // Animer l'apparition
  setTimeout(() => {
    notification.classList.add("show")
  }, 10)

  // Masquer après 5 secondes
  setTimeout(() => {
    notification.classList.remove("show")
    setTimeout(() => {
      if (notification.parentNode) {
        document.body.removeChild(notification)
      }
    }, 300)
  }, 5000)
}

// Arrêter tous les systèmes de surveillance
function stopProctoring() {
  // Arrêter la reconnaissance faciale
  if (faceDetectionInterval) {
    clearInterval(faceDetectionInterval)
  }

  // Arrêter le suivi oculaire
  if (gazeCheckInterval) {
    clearInterval(gazeCheckInterval)
  }
  if (typeof webgazer !== "undefined") {
    webgazer.end()
  }

  // Arrêter la surveillance audio
  if (audioCheckInterval) {
    clearInterval(audioCheckInterval)
  }

  // Arrêter la surveillance d'écran
  if (screenCheckInterval) {
    clearInterval(screenCheckInterval)
  }

  // Arrêter les flux média
  const video = document.getElementById("webcam")
  if (video && video.srcObject) {
    const tracks = video.srcObject.getTracks()
    tracks.forEach((track) => track.stop())
  }

  proctoringActive = false
  console.log("Système de surveillance arrêté")
}

// Nettoyer les ressources lors de la fermeture de la page
window.addEventListener("beforeunload", stopProctoring)

// Initialiser la surveillance au chargement de la page
document.addEventListener("DOMContentLoaded", () => {
  // Vérifier si la surveillance est activée
  const proctoringContainer = document.querySelector(".proctoring-container")
  if (proctoringContainer) {
    // Attendre un peu pour s'assurer que tous les éléments sont chargés
    setTimeout(() => {
      initProctoring()
    }, 1000)
  }
})
