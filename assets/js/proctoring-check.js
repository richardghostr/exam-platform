/**
 * Script de vérification du système de surveillance
 * Vérifie que tous les composants nécessaires sont disponibles et fonctionnels
 */

document.addEventListener("DOMContentLoaded", () => {
  // Vérifier si la surveillance est activée
  if (!document.querySelector(".proctoring-container")) {
    console.log("La surveillance n'est pas activée pour cet examen")
    return
  }

  // Afficher le statut de vérification
  function showStatus(message, type = "info") {
    const statusContainer = document.createElement("div")
    statusContainer.className = `proctoring-check-status status-${type}`
    statusContainer.innerHTML = `
            <div class="status-icon">
                <i class="fas ${type === "success" ? "fa-check-circle" : type === "error" ? "fa-times-circle" : "fa-info-circle"}"></i>
            </div>
            <div class="status-message">${message}</div>
        `

    // Ajouter au conteneur ou au body si le conteneur n'existe pas
    const container = document.getElementById("proctoring-check-container") || document.body
    container.appendChild(statusContainer)

    // Supprimer après 5 secondes si c'est une info ou un succès
    if (type !== "error") {
      setTimeout(() => {
        statusContainer.remove()
      }, 5000)
    }
  }

  // Vérifier les bibliothèques JavaScript
  function checkLibraries() {
    let allLoaded = true

    // Vérifier Face-API.js
    if (typeof faceapi === "undefined") {
      showStatus("Face-API.js n'est pas chargé. La reconnaissance faciale ne fonctionnera pas.", "error")
      allLoaded = false
    } else {
      showStatus("Face-API.js est correctement chargé", "success")
    }

    // Vérifier WebGazer.js
    if (typeof webgazer === "undefined") {
      showStatus("WebGazer.js n'est pas chargé. Le suivi oculaire ne fonctionnera pas.", "error")
      allLoaded = false
    } else {
      showStatus("WebGazer.js est correctement chargé", "success")
    }

    return allLoaded
  }

  // Vérifier l'accès aux périphériques
  async function checkDeviceAccess() {
    try {
      // Vérifier l'accès à la webcam et au microphone
      const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true })

      // Vérifier si la webcam fonctionne
      const video = document.getElementById("webcam")
      if (video) {
        video.srcObject = stream
        showStatus("Accès à la webcam et au microphone autorisé", "success")
        return true
      } else {
        showStatus("Élément vidéo non trouvé dans le DOM", "error")
        return false
      }
    } catch (error) {
      showStatus(`Erreur d'accès aux périphériques: ${error.message}`, "error")
      return false
    }
  }

  // Vérifier les modèles Face-API.js
  async function checkFaceApiModels() {
    if (typeof faceapi === "undefined") return false

    try {
      showStatus("Vérification des modèles Face-API.js...", "info")

      // Vérifier si les modèles sont chargés ou peuvent être chargés
      const modelPath = "../assets/models"

      // Essayer de charger le modèle TinyFaceDetector
      await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath)
      showStatus("Modèle TinyFaceDetector chargé avec succès", "success")

      // Essayer de charger le modèle FaceLandmark68
      await faceapi.nets.faceLandmark68Net.loadFromUri(modelPath)
      showStatus("Modèle FaceLandmark68 chargé avec succès", "success")

      // Essayer de charger le modèle FaceRecognition
      await faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)
      showStatus("Modèle FaceRecognition chargé avec succès", "success")

      // Essayer de charger le modèle FaceExpression
      await faceapi.nets.faceExpressionNet.loadFromUri(modelPath)
      showStatus("Modèle FaceExpression chargé avec succès", "success")

      return true
    } catch (error) {
      showStatus(`Erreur lors du chargement des modèles Face-API.js: ${error.message}`, "error")
      return false
    }
  }

  // Vérifier les éléments du DOM
  function checkDomElements() {
    const requiredElements = [
      { id: "webcam", name: "Webcam" },
      { id: "canvas", name: "Canvas de détection faciale" },
      { id: "gaze-canvas", name: "Canvas de suivi oculaire" },
      { id: "face-status", name: "Statut de reconnaissance faciale" },
      { id: "gaze-status", name: "Statut de suivi oculaire" },
      { id: "audio-status", name: "Statut de surveillance audio" },
      { id: "screen-status", name: "Statut de surveillance d'écran" },
      { id: "audio-volume-indicator", name: "Indicateur de volume audio" },
    ]

    let allFound = true

    for (const element of requiredElements) {
      if (!document.getElementById(element.id)) {
        showStatus(`Élément ${element.name} (${element.id}) non trouvé dans le DOM`, "error")
        allFound = false
      }
    }

    if (allFound) {
      showStatus("Tous les éléments DOM nécessaires sont présents", "success")
    }

    return allFound
  }

  // Vérifier les fonctions JavaScript
  function checkJavaScriptFunctions() {
    const requiredFunctions = [
      { name: "initProctoring", global: true },
      { name: "reportProctoringIncident", global: true },
      { name: "captureAndSaveImage", global: true },
      { name: "showProctoringNotification", global: true },
      { name: "updateProctoringStatus", global: true },
    ]

    let allFound = true

    for (const func of requiredFunctions) {
      if (func.global) {
        if (typeof window[func.name] !== "function") {
          showStatus(`Fonction globale ${func.name} non définie`, "error")
          allFound = false
        }
      }
    }

    if (allFound) {
      showStatus("Toutes les fonctions JavaScript nécessaires sont définies", "success")
    }

    return allFound
  }

  // Vérifier la connectivité au serveur
  async function checkServerConnectivity() {
    try {
      // Tester la connexion au serveur avec une requête simple
      const response = await fetch("../ajax/ping.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ test: true }),
      })

      if (response.ok) {
        const data = await response.json()
        if (data.success) {
          showStatus("Connexion au serveur établie avec succès", "success")
          return true
        }
      }

      showStatus("Erreur de connexion au serveur", "error")
      return false
    } catch (error) {
      showStatus(`Erreur de connexion au serveur: ${error.message}`, "error")
      return false
    }
  }

  // Créer un fichier ping.php pour tester la connectivité
  async function createPingFile() {
    try {
      const response = await fetch("../ajax/create-ping-file.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ create: true }),
      })

      if (response.ok) {
        const data = await response.json()
        if (data.success) {
          showStatus("Fichier ping.php créé avec succès", "success")
          return true
        }
      }

      return false
    } catch (error) {
      return false
    }
  }

  // Déclarer les variables faceapi et webgazer
  let faceapi
  let webgazer

  // Exécuter toutes les vérifications
  async function runAllChecks() {
    showStatus("Démarrage des vérifications du système de surveillance...", "info")

    // Vérifier les bibliothèques
    const librariesOk = checkLibraries()

    // Vérifier les éléments DOM
    const domOk = checkDomElements()

    // Vérifier les fonctions JavaScript
    const functionsOk = checkJavaScriptFunctions()

    // Vérifier l'accès aux périphériques
    const deviceAccessOk = await checkDeviceAccess()

    // Vérifier les modèles Face-API.js
    const modelsOk = await checkFaceApiModels()

    // Vérifier la connectivité au serveur
    await createPingFile()
    const serverOk = await checkServerConnectivity()

    // Résultat final
    if (librariesOk && domOk && functionsOk && deviceAccessOk && modelsOk && serverOk) {
      showStatus("Toutes les vérifications sont réussies. Le système de surveillance est prêt.", "success")
      return true
    } else {
      showStatus(
        "Certaines vérifications ont échoué. Le système de surveillance pourrait ne pas fonctionner correctement.",
        "error",
      )
      return false
    }
  }

  // Exécuter les vérifications après un court délai
  setTimeout(runAllChecks, 2000)
})
