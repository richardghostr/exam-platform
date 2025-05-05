<?php
// Vous pouvez ajouter du traitement PHP ici si nécessaire
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détection d'objets en temps réel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        #container { display: flex; flex-direction: column; align-items: center; }
        #video { border: 3px solid #333; max-width: 100%; }
        #canvas { position: absolute; max-width: 100%; }
        #result { margin-top: 20px; padding: 10px; border: 1px solid #ddd; min-height: 100px; }
        .video-container { position: relative; }
    </style>
</head>
<body>
    <div id="container">
        <h1>Détection d'objets en temps réel</h1>
        
        <div class="video-container">
            <video id="video" width="640" height="480" autoplay muted></video>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>
        
        <button id="startButton">Démarrer la détection</button>
        <button id="stopButton" disabled>Arrêter</button>
        
        <div id="result">
            <h3>Résultats de détection:</h3>
            <div id="detections"></div>
        </div>
    </div>

    <!-- Charger TensorFlow.js et le modèle COCO-SSD -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js"></script>
    
    <script>
    // Variables globales
    let model = null;
    let isDetecting = false;
    let video = document.getElementById('video');
    let canvas = document.getElementById('canvas');
    let ctx = canvas.getContext('2d');
    let startButton = document.getElementById('startButton');
    let stopButton = document.getElementById('stopButton');
    let detectionsDiv = document.getElementById('detections');

    // Charger le modèle COCO-SSD
    async function loadModel() {
        try {
            model = await cocoSsd.load();
            console.log('Modèle chargé avec succès');
            startButton.disabled = false;
        } catch (err) {
            console.error('Erreur lors du chargement du modèle:', err);
        }
    }

    // Démarrer la caméra
    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: 640, height: 480, facingMode: 'environment' },
                audio: false 
            });
            video.srcObject = stream;
            return new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    resolve();
                };
            });
        } catch (err) {
            console.error("Erreur d'accès à la caméra:", err);
            throw err;
        }
    }

    // Détecter les objets dans le flux vidéo
    async function detectObjects() {
        if (!isDetecting) return;
        
        try {
            // Effectuer la détection
            const predictions = await model.detect(video);
            
            // Effacer le canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Dessiner les boîtes englobantes
            predictions.forEach(prediction => {
                // Dessiner le rectangle
                ctx.strokeStyle = '#00FF00';
                ctx.lineWidth = 4;
                ctx.strokeRect(...prediction.bbox);
                
                // Dessiner l'étiquette
                ctx.fillStyle = '#00FF00';
                ctx.font = '18px Arial';
                ctx.fillText(
                    `${prediction.class} (${Math.round(prediction.score * 100)}%)`, 
                    prediction.bbox[0], 
                    prediction.bbox[1] > 10 ? prediction.bbox[1] - 5 : 10
                );
            });
            
            // Afficher les résultats textuels
            detectionsDiv.innerHTML = predictions.map(p => 
                `<p>${p.class} - Confiance: ${Math.round(p.score * 100)}%</p>`
            ).join('');
            
            // Envoyer les données au serveur (optionnel)
            if (predictions.length > 0) {
                sendDetectionData(predictions);
            }
            
            // Continuer la détection
            requestAnimationFrame(detectObjects);
        } catch (err) {
            console.error('Erreur de détection:', err);
            stopDetection();
        }
    }

    // Envoyer les données au serveur PHP
    async function sendDetectionData(predictions) {
        try {
            const response = await fetch('save_detections.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    timestamp: new Date().toISOString(),
                    detections: predictions
                })
            });
            
            const data = await response.json();
            console.log('Données enregistrées:', data);
        } catch (err) {
            console.error('Erreur lors de l\'envoi des données:', err);
        }
    }

    // Démarrer la détection
    async function startDetection() {
        try {
            await startCamera();
            isDetecting = true;
            startButton.disabled = true;
            stopButton.disabled = false;
            detectObjects();
        } catch (err) {
            console.error('Erreur lors du démarrage:', err);
        }
    }

    // Arrêter la détection
    function stopDetection() {
        isDetecting = false;
        startButton.disabled = false;
        stopButton.disabled = true;
        
        // Arrêter le flux vidéo
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
        
        // Effacer le canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        detectionsDiv.innerHTML = '<p>Détection arrêtée</p>';
    }

    // Événements
    startButton.addEventListener('click', startDetection);
    stopButton.addEventListener('click', stopDetection);

    // Charger le modèle au démarrage
    loadModel();
    </script>
</body>
</html>