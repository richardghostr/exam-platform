<?php
// Traitement des résultats de reconnaissance faciale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['face_data'])) {
    $data = json_decode($_POST['face_data'], true);
    $filename = 'face_data_' . date('Y-m-d_H-i-s') . '.json';
    
    if (file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT))) {
        echo json_encode(['status' => 'success', 'file' => $filename]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur d\'enregistrement']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconnaissance Faciale avec Face-API.js</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        #container { max-width: 1000px; margin: 0 auto; }
        #videoContainer { position: relative; margin: 20px 0; }
        #video { width: 100%; max-width: 640px; border: 3px solid #333; }
        #canvas { position: absolute; top: 0; left: 0; }
        .controls { margin: 20px 0; }
        .btn { padding: 10px 15px; margin-right: 10px; cursor: pointer; }
        #results { margin-top: 20px; padding: 15px; border: 1px solid #ddd; }
        #faceDescriptions { margin-top: 10px; }
        .face-box { position: absolute; border: 2px solid #0F0; background: rgba(0, 255, 0, 0.1); }
        .face-label { position: absolute; color: #0F0; font-weight: bold; background: rgba(0, 0, 0, 0.7); padding: 2px 5px; }
    </style>
</head>
<body>
    <div id="container">
        <h1>Reconnaissance Faciale en Temps Réel</h1>
        
        <div class="controls">
            <button id="startBtn" class="btn">Démarrer</button>
            <button id="stopBtn" class="btn" disabled>Arrêter</button>
            <button id="registerBtn" class="btn" disabled>Enregistrer Visage</button>
            <select id="modelSelect" class="btn">
                <option value="tiny">Modèle Léger (Tiny)</option>
                <option value="small">Modèle Petit (Small)</option>
                <option value="large" selected>Modèle Complet (Large)</option>
            </select>
        </div>
        
        <div id="videoContainer">
            <video id="video" width="640" height="480" autoplay muted></video>
        </div>
        
        <div id="results">
            <h3>Résultats de détection:</h3>
            <div id="faceCount">0 visage(s) détecté(s)</div>
            <div id="faceDescriptions"></div>
        </div>
    </div>

    <!-- Charger Face-API.js -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    
    <script>
    // Variables globales
    let video;
    let isDetecting = false;
    let detectionInterval;
    let registeredFaces = [];
    let faceMatcher = null;

    // Charger les modèles
    async function loadModels(modelSize) {
        const modelPath = `https://justadudewhohacks.github.io/face-api.js/models/`;
        
        try {
            // Afficher le statut de chargement
            document.getElementById('faceDescriptions').innerHTML = 
                '<p>Chargement des modèles de reconnaissance faciale...</p>';
            
            // Charger les modèles sélectionnés
            await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath);
            
            if (modelSize === 'large') {
                await faceapi.loadFaceLandmarkModel(modelPath);
                await faceapi.loadFaceRecognitionModel(modelPath);
                await faceapi.loadFaceExpressionModel(modelPath);
                await faceapi.loadAgeGenderModel(modelPath);
            } else if (modelSize === 'small') {
                await faceapi.loadFaceLandmarkModel(modelPath);
                await faceapi.loadFaceRecognitionModel(modelPath);
            }
            
            console.log('Modèles chargés avec succès');
            return true;
        } catch (error) {
            console.error('Erreur de chargement des modèles:', error);
            document.getElementById('faceDescriptions').innerHTML = 
                '<p style="color:red">Erreur de chargement des modèles</p>';
            return false;
        }
    }

    // Démarrer la caméra
    async function startCamera() {
        try {
            video = document.getElementById('video');
            
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480, facingMode: 'user' },
                audio: false
            });
            
            video.srcObject = stream;
            
            return new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    video.play();
                    resolve();
                };
            });
        } catch (error) {
            console.error("Erreur d'accès à la caméra:", error);
            throw error;
        }
    }

    // Démarrer la détection faciale
    async function startFaceDetection() {
        const modelSize = document.getElementById('modelSelect').value;
        const modelsLoaded = await loadModels(modelSize);
        
        if (!modelsLoaded) return;
        
        try {
            await startCamera();
            
            isDetecting = true;
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('registerBtn').disabled = false;
            
            // Démarrer la détection en temps réel
            detectionInterval = setInterval(async () => {
                await detectFaces();
            }, 300); // Détection toutes les 300ms
            
        } catch (error) {
            console.error('Erreur de démarrage:', error);
            document.getElementById('faceDescriptions').innerHTML = 
                '<p style="color:red">Erreur: ' + error.message + '</p>';
        }
    }

    // Détecter et reconnaître les visages
    async function detectFaces() {
        if (!isDetecting) return;
        
        try {
            // Options de détection
            const detectionOptions = {
                inputSize: 512, // Taille d'entrée pour le détecteur
                scoreThreshold: 0.8 // Seuil de confiance
            };
            
            // Détection des visages
            const detections = await faceapi.detectAllFaces(
                video, 
                new faceapi.TinyFaceDetectorOptions(detectionOptions)
            )
            .withFaceLandmarks()
            .withFaceDescriptors();
            
            // Afficher les résultats
            displayDetections(detections);
            
            // Envoyer les données au serveur périodiquement
            if (detections.length > 0 && Math.random() < 0.1) { // 10% de chance d'envoyer
                sendFaceDataToServer(detections);
            }
            
        } catch (error) {
            console.error('Erreur de détection:', error);
        }
    }

    // Afficher les détections
    function displayDetections(detections) {
        const videoContainer = document.getElementById('videoContainer');
        const faceCountDiv = document.getElementById('faceCount');
        const faceDescriptionsDiv = document.getElementById('faceDescriptions');
        
        // Nettoyer les résultats précédents
        videoContainer.querySelectorAll('.face-box, .face-label').forEach(el => el.remove());
        faceDescriptionsDiv.innerHTML = '';
        
        // Mettre à jour le compte de visages
        faceCountDiv.textContent = `${detections.length} visage(s) détecté(s)`;
        
        if (detections.length === 0) return;
        
        // Ajuster les dimensions pour l'affichage
        const displaySize = { width: video.width, height: video.height };
        const resizedDetections = faceapi.resizeResults(detections, displaySize);
        
        // Informations détaillées
        let descriptionsHTML = '';
        
        resizedDetections.forEach((detection, i) => {
            const box = detection.detection.box;
            
            // Dessiner le rectangle autour du visage
            const faceBox = document.createElement('div');
            faceBox.className = 'face-box';
            faceBox.style.width = `${box.width}px`;
            faceBox.style.height = `${box.height}px`;
            faceBox.style.left = `${box.x}px`;
            faceBox.style.top = `${box.y}px`;
            videoContainer.appendChild(faceBox);
            
            // Ajouter une étiquette
            const faceLabel = document.createElement('div');
            faceLabel.className = 'face-label';
            faceLabel.style.left = `${box.x}px`;
            faceLabel.style.top = `${box.y - 20}px`;
            faceLabel.textContent = `Visage ${i + 1}`;
            videoContainer.appendChild(faceLabel);
            
            // Collecter les informations descriptives
            descriptionsHTML += `<div><strong>Visage ${i + 1}:</strong>`;
            descriptionsHTML += `<ul>`;
            descriptionsHTML += `<li>Confiance: ${Math.round(detection.detection.score * 100)}%</li>`;
            
            // Ajouter des informations supplémentaires si le modèle large est utilisé
            if (detection.hasOwnProperty('landmarks')) {
                descriptionsHTML += `<li>Points de repère: ${detection.landmarks.positions.length}</li>`;
            }
            
            descriptionsHTML += `</ul></div>`;
        });
        
        faceDescriptionsDiv.innerHTML = descriptionsHTML;
    }

    // Enregistrer un visage pour la reconnaissance
    async function registerFace() {
        try {
            const detections = await faceapi.detectAllFaces(
                video, 
                new faceapi.TinyFaceDetectorOptions()
            )
            .withFaceLandmarks()
            .withFaceDescriptor();
            
            if (detections.length === 0) {
                alert('Aucun visage détecté pour enregistrement');
                return;
            }
            
            const faceName = prompt("Entrez le nom pour ce visage:");
            if (!faceName) return;
            
            registeredFaces.push({
                name: faceName,
                descriptor: detections[0].descriptor
            });
            
            // Mettre à jour le FaceMatcher pour la reconnaissance
            if (registeredFaces.length > 0) {
                const labeledDescriptors = registeredFaces.map(face => 
                    new faceapi.LabeledFaceDescriptors(face.name, [face.descriptor])
                );
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors);
            }
            
            alert(`Visage "${faceName}" enregistré avec succès!`);
            
        } catch (error) {
            console.error('Erreur lors de l\'enregistrement:', error);
            alert('Erreur lors de l\'enregistrement du visage');
        }
    }

    // Envoyer les données au serveur PHP
    async function sendFaceDataToServer(detections) {
        try {
            // Préparer les données à envoyer
            const faceData = detections.map(detection => ({
                score: detection.detection.score,
                box: detection.detection.box,
                landmarks: detection.landmarks ? detection.landmarks.positions.map(p => ({ x: p.x, y: p.y })) : null,
                timestamp: new Date().toISOString()
            }));
            
            // Envoyer via AJAX
            const response = await fetch('facial_recognition.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `face_data=${encodeURIComponent(JSON.stringify(faceData))}`
            });
            
            const result = await response.json();
            console.log('Données faciales envoyées:', result);
            
        } catch (error) {
            console.error('Erreur d\'envoi des données:', error);
        }
    }

    // Arrêter la détection
    function stopFaceDetection() {
        isDetecting = false;
        clearInterval(detectionInterval);
        
        document.getElementById('startBtn').disabled = false;
        document.getElementById('stopBtn').disabled = true;
        document.getElementById('registerBtn').disabled = true;
        
        // Arrêter le flux vidéo
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
        
        // Nettoyer l'affichage
        document.getElementById('videoContainer').queryAll('.face-box, .face-label')
            .forEach(el => el.remove());
        document.getElementById('faceCount').textContent = '0 visage(s) détecté(s)';
        document.getElementById('faceDescriptions').innerHTML = '';
    }

    // Événements
    document.getElementById('startBtn').addEventListener('click', startFaceDetection);
    document.getElementById('stopBtn').addEventListener('click', stopFaceDetection);
    document.getElementById('registerBtn').addEventListener('click', registerFace);
    document.getElementById('modelSelect').addEventListener('change', () => {
        if (isDetecting) {
            stopFaceDetection();
            startFaceDetection();
        }
    });

    // Vérifier la compatibilité
    if (!navigator.mediaDevices || !window.AudioContext) {
        document.getElementById('startBtn').disabled = true;
        document.getElementById('faceDescriptions').innerHTML = 
            '<p style="color:red">Votre navigateur ne supporte pas les fonctionnalités requises</p>';
    }
    </script>
    
</body>
</html>