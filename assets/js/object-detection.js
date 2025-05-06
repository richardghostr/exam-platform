/**
 * Module de détection d'objets pour le système de surveillance d'examen
 * Version corrigée avec meilleure gestion des erreurs
 */

// Configuration
const DETECTION_INTERVAL = 5000;
const CONFIDENCE_THRESHOLD = 0.65;
const FORBIDDEN_OBJECTS = ["cell phone", "laptop", "book", "remote", "keyboard", "mouse", "tablet"];
const CONSECUTIVE_DETECTIONS_THRESHOLD = 2;
const MODEL_LOAD_TIMEOUT = 30000; // 30 secondes timeout pour le chargement du modèle

// Variables globales
let objectDetectionModel = null;
let objectDetectionInterval = null;
let consecutiveDetections = {};
let lastDetectionTime = 0;
let isObjectDetectionActive = false;
let objectDetectionInitialized = false;
let modelLoading = false;

// Fonction pour vérifier que TensorFlow.js et COCO-SSD sont correctement chargés
function areDependenciesLoaded() {
    const isTfLoaded = typeof tf !== 'undefined' && typeof tf.ready === 'function';
    const isCocoSsdLoaded = typeof cocoSsd !== 'undefined' && typeof cocoSsd.load === 'function';
    
    if (!isTfLoaded) console.error('TensorFlow.js non chargé ou version incorrecte');
    if (!isCocoSsdLoaded) console.error('COCO-SSD non chargé ou version incorrecte');
    
    return isTfLoaded && isCocoSsdLoaded;
}

// Fonction pour charger dynamiquement les dépendances
function loadDependencies() {
    return new Promise(async (resolve, reject) => {
        if (areDependenciesLoaded()) {
            resolve();
            return;
        }

        console.log('Chargement des dépendances...');
        
        // Charger TensorFlow.js
        const tfScript = document.createElement('script');
        tfScript.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js';
        tfScript.onerror = () => reject(new Error('Échec du chargement de TensorFlow.js'));
        document.head.appendChild(tfScript);

        // Charger COCO-SSD
        const cocoSsdScript = document.createElement('script');
        cocoSsdScript.src = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js';
        cocoSsdScript.onerror = () => reject(new Error('Échec du chargement de COCO-SSD'));
        document.head.appendChild(cocoSsdScript);

        // Vérifier périodiquement si les dépendances sont chargées
        const checkInterval = setInterval(() => {
            if (areDependenciesLoaded()) {
                clearInterval(checkInterval);
                clearTimeout(timeout);
                resolve();
            }
        }, 100);

        // Timeout pour éviter une attente infinie
        const timeout = setTimeout(() => {
            clearInterval(checkInterval);
            reject(new Error('Timeout du chargement des dépendances'));
        }, MODEL_LOAD_TIMEOUT);
    });
}

// Fonction pour initialiser la détection d'objets
async function initObjectDetection() {
    if (objectDetectionInitialized || modelLoading) return false;
    
    modelLoading = true;
    updateObjectDetectionStatus('initializing', 'Initialisation en cours...');

    try {
        // 1. Charger les dépendances
        await loadDependencies();
        
        // 2. Attendre que TensorFlow soit prêt
        await tf.ready();
        console.log('TensorFlow.js est prêt');

        // 3. Charger le modèle COCO-SSD avec vérification
        updateObjectDetectionStatus('initializing', 'Chargement du modèle...');
        
        const loadingTimeout = setTimeout(() => {
            throw new Error('Timeout du chargement du modèle');
        }, MODEL_LOAD_TIMEOUT);

        objectDetectionModel = await cocoSsd.load({
            base: 'lite_mobilenet_v2'
        });

        clearTimeout(loadingTimeout);

        // Vérification que le modèle est bien chargé
        if (!objectDetectionModel || typeof objectDetectionModel.detect !== 'function') {
            throw new Error('Le modèle ne s\'est pas chargé correctement');
        }

        console.log('Modèle COCO-SSD chargé avec succès');
        objectDetectionInitialized = true;
        modelLoading = false;
        
        updateObjectDetectionStatus('active', 'Prêt à détecter');
        return true;

    } catch (error) {
        console.error('Erreur d\'initialisation:', error);
        updateObjectDetectionStatus('error', `Erreur: ${error.message}`);
        objectDetectionModel = null;
        modelLoading = false;
        return false;
    }
}

// Fonction de détection d'objets avec gestion d'erreur améliorée
async function detectObjects() {
    if (!isObjectDetectionActive) return;
    
    const video = document.getElementById("webcam");
    
    // Vérifications approfondies
    if (!video || !video.srcObject || video.paused || video.ended) {
        console.warn('Détection: vidéo non disponible');
        return;
    }

    if (!objectDetectionModel || typeof objectDetectionModel.detect !== 'function') {
        console.error('Modèle de détection non valide');
        updateObjectDetectionStatus('error', 'Modèle non valide - réinitialisation');
        await initObjectDetection(); // Tentative de réinitialisation
        return;
    }

    try {
        const now = Date.now();
        if (now - lastDetectionTime < DETECTION_INTERVAL * 0.8) return;
        lastDetectionTime = now;

        // Détection avec gestion d'erreur
        const predictions = await objectDetectionModel.detect(video).catch(err => {
            throw new Error(`Erreur de détection: ${err.message}`);
        });

        // Traitement des résultats
        const forbiddenObjects = predictions.filter(
            p => FORBIDDEN_OBJECTS.includes(p.class) && p.score >= CONFIDENCE_THRESHOLD
        );

        if (forbiddenObjects.length > 0) {
            console.log("Objets interdits détectés:", forbiddenObjects);
            
            forbiddenObjects.forEach(obj => {
                consecutiveDetections[obj.class] = (consecutiveDetections[obj.class] || 0) + 1;
                
                if (consecutiveDetections[obj.class] >= CONSECUTIVE_DETECTIONS_THRESHOLD) {
                    reportObjectDetection(forbiddenObjects);
                    consecutiveDetections[obj.class] = 0;
                }
            });
            
            updateObjectDetectionStatus('warning', `Détection: ${forbiddenObjects.map(o => o.class).join(", ")}`);
            drawDetectionBoxes(video, forbiddenObjects);
        } else {
            consecutiveDetections = {};
            updateObjectDetectionStatus('active', 'Surveillance active');
            clearDetectionCanvas();
        }
    } catch (error) {
        console.error('Erreur de détection:', error);
        updateObjectDetectionStatus('error', 'Erreur de détection');
        
        // Réinitialiser si l'erreur concerne le modèle
        if (error.message.includes('model') || error.message.includes('détection')) {
            await initObjectDetection();
        }
    }
}

// Fonctions auxiliaires restantes (non modifiées mais nécessaires)
function updateObjectDetectionStatus(status, message) {
    console.log(`[Object Detection] ${status}: ${message}`);
    const statusElement = document.getElementById('object-status');
    if (statusElement) {
        let icon = 'fa-spinner fa-spin';
        if (status === 'active') icon = 'fa-check-circle';
        if (status === 'warning') icon = 'fa-exclamation-triangle';
        if (status === 'error') icon = 'fa-times-circle';
        
        statusElement.innerHTML = `<i class="fas ${icon}"></i> Détection: ${message}`;
        statusElement.className = 'status-item status-' + status;
    }
}

function drawDetectionBoxes(video, objects) {
    const canvas = document.getElementById('object-canvas');
    if (!canvas) return;
    
    if (canvas.width !== video.videoWidth || canvas.height !== video.videoHeight) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    objects.forEach(obj => {
        ctx.strokeStyle = '#FF0000';
        ctx.lineWidth = 3;
        ctx.strokeRect(...obj.bbox);
        
        ctx.fillStyle = '#FF0000';
        ctx.font = '16px Arial';
        ctx.fillText(
            `${obj.class} (${Math.round(obj.score * 100)}%)`, 
            obj.bbox[0], 
            obj.bbox[1] > 20 ? obj.bbox[1] - 5 : 20
        );
    });
}

function clearDetectionCanvas() {
    const canvas = document.getElementById('object-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function reportObjectDetection(objects) {
    const description = `Objets interdits détectés: ${objects.map(o => o.class).join(", ")}`;
    console.warn(description);
    
    if (typeof window.reportProctoringIncident === 'function') {
        window.reportProctoringIncident('object', description, 'high');
    }
}

function startObjectDetection() {
    if (isObjectDetectionActive) return;
    
    isObjectDetectionActive = true;
    updateObjectDetectionStatus('active', 'Détection démarrée');
    
    // Démarrer la détection immédiate et périodique
    detectObjects();
    objectDetectionInterval = setInterval(detectObjects, DETECTION_INTERVAL);
}

function stopObjectDetection() {
    isObjectDetectionActive = false;
    if (objectDetectionInterval) {
        clearInterval(objectDetectionInterval);
        objectDetectionInterval = null;
    }
    updateObjectDetectionStatus('inactive', 'Détection arrêtée');
    clearDetectionCanvas();
}

// Exposer les fonctions globales
window.ObjectDetection = {
    init: initObjectDetection,
    start: startObjectDetection,
    stop: stopObjectDetection,
    isActive: () => isObjectDetectionActive
};