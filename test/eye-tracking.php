<?php
// Vous pouvez ajouter du code PHP ici pour le traitement côté serveur
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi oculaire avec WEBGAZER</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        #webgazerVideoContainer {
            position: fixed;
            top: 10px;
            right: 10px;
            width: 200px;
            height: 150px;
            z-index: 1000;
            border: 2px solid #ccc;
        }
        #webgazerVideoFeed {
            width: 100%;
            height: 100%;
        }
        #webgazerFaceOverlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        #gazeDot {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: red;
            z-index: 999;
            pointer-events: none;
            display: none;
        }
        #trackingStatus {
            margin: 20px 0;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        #dataLog {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            min-height: 100px;
            text-align: left;
        }
    </style>
</head>
<body>
    <h1>Suivi oculaire avec WEBGAZER</h1>
    
    <div id="trackingStatus">Statut: Non initialisé</div>
    
    <button id="startTracking">Démarrer le suivi</button>
    <button id="stopTracking" disabled>Arrêter le suivi</button>
    <button id="saveData" disabled>Enregistrer les données</button>
    
    <div id="webgazerVideoContainer" style="display: none;">
        <video id="webgazerVideoFeed" autoplay muted></video>
        <canvas id="webgazerFaceOverlay"></canvas>
    </div>
    
    <div id="gazeDot"></div>
    
    <div id="dataLog">
        <h3>Journal des données:</h3>
        <div id="logContent"></div>
    </div>

    <!-- Inclure la bibliothèque WEBGAZER -->
    <script src="https://webgazer.cs.brown.edu/webgazer.js"></script>
    <script>
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
    </script>
</body>
</html>