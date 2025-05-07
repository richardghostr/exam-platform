/**
 * Module de détection d'objets pour système de surveillance d'examen
 * Version simplifiée utilisant uniquement COCO-SSD
 */

// Configuration
const DETECTION_INTERVAL = 2000; // Intervalle de détection en ms
const CONFIDENCE_THRESHOLD = 0.6; // Seuil de confiance minimum
const FORBIDDEN_OBJECTS = ["cell phone", "laptop", "book", "remote", "keyboard", "mouse", "tablet"];

// État global
let detectionModel = null;
let detectionActive = false;
let detectionInterval = null;
let detectionCanvas = null;
let canvasContext = null;

/**
 * Initialisation du système de détection
 */
async function initDetectionSystem() {
    if (detectionModel || detectionActive) {
        console.log('Détection déjà initialisée');
        return true;
    }

    updateStatus('Chargement du modèle...');
    
    try {
        // Charger COCO-SSD uniquement
        if (typeof cocoSsd === 'undefined') {
            await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js');
        }

        // Créer le canvas de superposition
        setupDetectionCanvas();

        // Charger le modèle
        detectionModel = await cocoSsd.load();
        updateStatus('Modèle chargé - Prêt');
        
        return true;
    } catch (error) {
        console.error('Erreur initialisation:', error);
        updateStatus('Erreur de chargement', 'error');
        return false;
    }
}

/**
 * Configurer le canvas de détection
 */
function setupDetectionCanvas() {
    const videoElement = document.getElementById('webcam');
    if (!videoElement) {
        throw new Error('Élément vidéo introuvable');
    }

    // Créer ou récupérer le canvas
    detectionCanvas = document.getElementById('detection-canvas');
    if (!detectionCanvas) {
        detectionCanvas = document.createElement('canvas');
        detectionCanvas.id = 'detection-canvas';
        detectionCanvas.style.position = 'absolute';
        detectionCanvas.style.top = '0';
        detectionCanvas.style.left = '0';
        detectionCanvas.style.pointerEvents = 'none';
        
        videoElement.parentNode.appendChild(detectionCanvas);
    }

    canvasContext = detectionCanvas.getContext('2d');
}

/**
 * Démarrer la détection en continu
 */
function startDetection() {
    if (detectionActive || !detectionModel) return;

    detectionActive = true;
    updateStatus('Détection active');
    
    // Détection immédiate puis intervalle
    runDetection();
    detectionInterval = setInterval(runDetection, DETECTION_INTERVAL);
}

/**
 * Arrêter la détection
 */
function stopDetection() {
    if (!detectionActive) return;

    clearInterval(detectionInterval);
    detectionActive = false;
    updateStatus('Détection arrêtée');
    
    // Nettoyer le canvas
    if (canvasContext && detectionCanvas) {
        canvasContext.clearRect(0, 0, detectionCanvas.width, detectionCanvas.height);
    }
}

/**
 * Exécuter une détection
 */
async function runDetection() {
    const videoElement = document.getElementById('webcam');
    if (!videoElement || videoElement.readyState < 2) return;

    try {
        // Ajuster la taille du canvas
        resizeCanvasToVideo(videoElement);

        // Détecter les objets
        const predictions = await detectionModel.detect(videoElement);
        
        // Traiter les résultats
        processDetections(predictions);
    } catch (error) {
        console.error('Erreur détection:', error);
        updateStatus('Erreur de détection', 'error');
    }
}

/**
 * Redimensionner le canvas selon la vidéo
 */
function resizeCanvasToVideo(video) {
    if (detectionCanvas.width !== video.videoWidth || detectionCanvas.height !== video.videoHeight) {
        detectionCanvas.width = video.videoWidth;
        detectionCanvas.height = video.videoHeight;
    }
}

/**
 * Traiter les résultats de détection
 */
function processDetections(predictions) {
    // Filtrer les objets interdits
    const forbiddenDetections = predictions.filter(
        pred => FORBIDDEN_OBJECTS.includes(pred.class) && pred.score >= CONFIDENCE_THRESHOLD
    );

    // Effacer le canvas
    canvasContext.clearRect(0, 0, detectionCanvas.width, detectionCanvas.height);

    if (forbiddenDetections.length > 0) {
        updateStatus(`${forbiddenDetections.length} objet(s) interdit(s) détecté(s)`, 'warning');
        drawDetections(forbiddenDetections);
        reportDetections(forbiddenDetections);
    } else {
        updateStatus('Aucun objet interdit détecté');
    }
}

/**
 * Dessiner les boîtes de détection
 */
function drawDetections(detections) {
    detections.forEach(detection => {
        const [x, y, width, height] = detection.bbox;
        
        // Dessiner la boîte
        canvasContext.strokeStyle = '#FF0000';
        canvasContext.lineWidth = 2;
        canvasContext.strokeRect(x, y, width, height);
        
        // Dessiner le fond du label
        canvasContext.fillStyle = 'rgba(255, 0, 0, 0.5)';
        canvasContext.fillRect(x, y - 20, width, 20);
        
        // Dessiner le texte
        canvasContext.fillStyle = '#FFFFFF';
        canvasContext.font = '14px Arial';
        canvasContext.fillText(
            `${detection.class} (${Math.round(detection.score * 100)}%)`,
            x + 5,
            y - 5
        );
    });
}

/**
 * Signaler les détections au serveur
 */
async function reportDetections(detections) {
    try {
        const attemptId = document.getElementById('exam-container')?.dataset?.attemptId;
        if (!attemptId) return;

        const response = await fetch('/api/report-violations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attemptId,
                detections: detections.map(d => ({
                    object: d.class,
                    confidence: d.score,
                    timestamp: new Date().toISOString()
                }))
            })
        });

        if (!response.ok) {
            throw new Error('Échec du signalement');
        }
    } catch (error) {
        console.error('Erreur signalement:', error);
    }
}

/**
 * Mettre à jour l'interface utilisateur
 */
function updateStatus(message, type = 'info') {
    const statusElement = document.getElementById('detection-status');
    if (statusElement) {
        statusElement.textContent = message;
        statusElement.className = `status ${type}`;
    }
}

/**
 * Charger un script dynamiquement
 */
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// Interface publique
window.ObjectDetector = {
    init: initDetectionSystem,
    start: startDetection,
    stop: stopDetection
};

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        initDetectionSystem().then(initialized => {
            if (initialized) {
                startDetection();
            }
        });
    }, 3000);
});