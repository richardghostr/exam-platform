<?php
// Traitement PHP pour la réception des données audio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_data'])) {
    $uploadDir = 'audio_uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $filename = uniqid('audio_') . '.wav';
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['audio_data']['tmp_name'], $destination)) {
        echo json_encode(['status' => 'success', 'file' => $filename]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'enregistrement']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance Audio en Temps Réel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        #container { max-width: 800px; margin: 0 auto; }
        #visualizer { width: 100%; height: 200px; background: #f0f0f0; margin: 20px 0; }
        #results { margin-top: 20px; padding: 15px; border: 1px solid #ddd; }
        .btn { padding: 10px 15px; margin-right: 10px; cursor: pointer; }
        #status { margin: 10px 0; padding: 10px; }
    </style>
</head>
<body>
    <div id="container">
        <h1>Surveillance Audio en Temps Réel</h1>
        
        <button id="startBtn" class="btn">Démarrer l'écoute</button>
        <button id="stopBtn" class="btn" disabled>Arrêter</button>
        <button id="analyzeBtn" class="btn" disabled>Analyser</button>
        
        <div id="status">Statut: Inactif</div>
        
        <div id="visualizer"></div>
        
        <div id="results">
            <h3>Résultats d'analyse:</h3>
            <div id="analysisResults"></div>
        </div>
    </div>

    <script>
    // Variables globales
    let audioContext;
    let analyser;
    let microphone;
    let isRecording = false;
    let audioChunks = [];
    let recordingInterval;
    let visualizationInterval;
    const visualizer = document.getElementById('visualizer');
    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const analyzeBtn = document.getElementById('analyzeBtn');
    const statusDiv = document.getElementById('status');
    const resultsDiv = document.getElementById('analysisResults');

    // Démarrer la surveillance audio
    async function startAudioSurveillance() {
        try {
            statusDiv.textContent = "Statut: Initialisation...";
            
            // Créer le contexte audio
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 2048;
            
            // Accéder au microphone
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            microphone = audioContext.createMediaStreamSource(stream);
            microphone.connect(analyser);
            
            // Configurer la visualisation
            setupVisualizer();
            
            isRecording = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            analyzeBtn.disabled = false;
            statusDiv.textContent = "Statut: En écoute active";
            
            // Démarrer l'enregistrement par tranches
            audioChunks = [];
            recordingInterval = setInterval(processAudio, 5000); // Traiter toutes les 5 secondes
            
        } catch (error) {
            console.error("Erreur:", error);
            statusDiv.textContent = "Erreur: " + error.message;
        }
    }

    // Configurer la visualisation audio
    function setupVisualizer() {
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        const canvas = document.createElement('canvas');
        canvas.width = visualizer.offsetWidth;
        canvas.height = visualizer.offsetHeight;
        visualizer.innerHTML = '';
        visualizer.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        
        visualizationInterval = setInterval(() => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Obtenir les données de fréquence
            analyser.getByteFrequencyData(dataArray);
            
            // Dessiner le spectre de fréquence
            const barWidth = (canvas.width / bufferLength) * 2.5;
            let x = 0;
            
            for (let i = 0; i < bufferLength; i++) {
                const barHeight = (dataArray[i] / 255) * canvas.height;
                
                ctx.fillStyle = `rgb(${barHeight + 100}, 50, 50)`;
                ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
            
            // Analyse simple du volume
            const volume = calculateVolume(dataArray);
            if (volume > 70) { // Seuil de volume élevé
                statusDiv.textContent = `Statut: Volume élevé détecté (${Math.round(volume)}%)`;
                statusDiv.style.color = 'red';
            } else {
                statusDiv.textContent = "Statut: En écoute active";
                statusDiv.style.color = 'black';
            }
        }, 100);
    }

    // Calculer le volume moyen
    function calculateVolume(dataArray) {
        let sum = 0;
        for (const value of dataArray) {
            sum += value;
        }
        return (sum / dataArray.length) / 2.55; // Convertir en pourcentage
    }

    // Traiter l'audio capturé
    function processAudio() {
        if (!isRecording) return;
        
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        analyser.getByteTimeDomainData(dataArray);
        
        // Enregistrer le chunk audio
        audioChunks.push(dataArray);
        
        // Envoyer au serveur périodiquement
        if (audioChunks.length >= 3) { // Toutes les 15 secondes (3 x 5s)
            sendAudioToServer();
            audioChunks = [];
        }
    }

    // Envoyer l'audio au serveur PHP
    async function sendAudioToServer() {
        try {
            // Convertir les données audio en WAV (simplifié)
            const audioBlob = convertToWav(audioChunks);
            
            const formData = new FormData();
            formData.append('audio_data', audioBlob, 'recording.wav');
            
            const response = await fetch('audio_surveillance.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log("Audio envoyé:", result);
            
        } catch (error) {
            console.error("Erreur d'envoi:", error);
        }
    }

    // Convertir les données audio en WAV (version simplifiée)
    function convertToWav(audioChunks) {
        // Dans une implémentation réelle, vous devriez encoder correctement en WAV
        // Ceci est une simplification pour l'exemple
        const merged = mergeArrays(audioChunks);
        const blob = new Blob([merged], { type: 'audio/wav' });
        return blob;
    }

    // Fusionner les tableaux Uint8Array
    function mergeArrays(arrays) {
        let totalLength = arrays.reduce((acc, arr) => acc + arr.length, 0);
        let result = new Uint8Array(totalLength);
        let offset = 0;
        
        for (const arr of arrays) {
            result.set(arr, offset);
            offset += arr.length;
        }
        
        return result;
    }

    // Analyser l'audio localement
    function analyzeAudio() {
        if (audioChunks.length === 0) {
            resultsDiv.textContent = "Aucune donnée audio à analyser";
            return;
        }
        
        // Exemple d'analyse simple
        const bufferLength = analyser.frequencyBinCount;
        const frequencyData = new Uint8Array(bufferLength);
        const timeDomainData = new Uint8Array(bufferLength);
        
        analyser.getByteFrequencyData(frequencyData);
        analyser.getByteTimeDomainData(timeDomainData);
        
        // Calculer quelques métriques
        const volume = calculateVolume(frequencyData);
        const dominantFreq = findDominantFrequency(frequencyData, audioContext.sampleRate);
        
        // Afficher les résultats
        resultsDiv.innerHTML = `
            <p><strong>Volume moyen:</strong> ${Math.round(volume)}%</p>
            <p><strong>Fréquence dominante:</strong> ${Math.round(dominantFreq)} Hz</p>
            <p><strong>Analyse:</strong> ${getAudioAnalysis(volume, dominantFreq)}</p>
        `;
    }

    // Trouver la fréquence dominante
    function findDominantFrequency(frequencyData, sampleRate) {
        let maxIndex = 0;
        let maxValue = 0;
        
        for (let i = 0; i < frequencyData.length; i++) {
            if (frequencyData[i] > maxValue) {
                maxValue = frequencyData[i];
                maxIndex = i;
            }
        }
        
        return maxIndex * (sampleRate / 2) / frequencyData.length;
    }

    // Interpréter les résultats audio
    function getAudioAnalysis(volume, frequency) {
        if (volume > 70) {
            return "Niveau sonore élevé détecté - possible bruit important";
        } else if (frequency > 2000 && volume > 30) {
            return "Fréquence aiguë détectée - possible alarme ou sifflement";
        } else if (frequency < 200 && volume > 40) {
            return "Fréquence grave détectée - possible voix masculine ou bourdonnement";
        } else {
            return "Bruit de fond normal";
        }
    }

    // Arrêter la surveillance audio
    function stopAudioSurveillance() {
        if (!isRecording) return;
        
        clearInterval(recordingInterval);
        clearInterval(visualizationInterval);
        
        if (microphone) {
            microphone.disconnect();
        }
        
        if (audioContext) {
            audioContext.close();
        }
        
        isRecording = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        analyzeBtn.disabled = true;
        statusDiv.textContent = "Statut: Inactif";
        statusDiv.style.color = 'black';
        
        // Envoyer les dernières données
        if (audioChunks.length > 0) {
            sendAudioToServer();
        }
    }

    // Événements
    startBtn.addEventListener('click', startAudioSurveillance);
    stopBtn.addEventListener('click', stopAudioSurveillance);
    analyzeBtn.addEventListener('click', analyzeAudio);

    // Vérifier la compatibilité
    if (!navigator.mediaDevices || !window.AudioContext) {
        startBtn.disabled = true;
        statusDiv.textContent = "Votre navigateur ne supporte pas les fonctionnalités audio requises";
    }
    </script>
</body>
</html>