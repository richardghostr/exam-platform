/**
 * Script pour la gestion des examens
 * Ce script gère le minuteur, la navigation entre les questions, la sauvegarde des réponses et la soumission de l'examen
 */

// Variables globales
let currentQuestion = 0;
let remainingTime = 0;
let timerInterval;
let autoSaveInterval;
let questions = [];
let answers = {};
let attemptId = 0;
let enrollmentId = 0;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer les données de l'examen
    attemptId = document.querySelector('input[name="attempt_id"]').value;
    enrollmentId = document.querySelector('input[name="enrollment_id"]').value;
    
    // Initialiser les questions
    const questionContainers = document.querySelectorAll('.question-container');
    questionContainers.forEach((container, index) => {
        questions.push({
            id: container.id.replace('question-', ''),
            element: container
        });
    });
    
    // Initialiser le temps restant
    const timerElement = document.getElementById('timer-display');
    if (timerElement) {
        const timeString = timerElement.getAttribute('data-remaining-time');
        remainingTime = parseInt(timeString) || 0;
        startTimer();
    }
    
    // Configurer la sauvegarde automatique
    autoSaveInterval = setInterval(saveAnswers, 30000); // Toutes les 30 secondes
    
    // Configurer les événements pour les options de réponse
    setupAnswerEvents();
    
    // Empêcher la fermeture accidentelle de la page
    window.addEventListener('beforeunload', function(e) {
        if (document.getElementById('exam-form')) {
            e.preventDefault();
            e.returnValue = 'Êtes-vous sûr de vouloir quitter l\'examen ? Vos réponses pourraient être perdues.';
            return e.returnValue;
        }
    });
});

// Démarrer le minuteur
function startTimer() {
    updateTimerDisplay();
    
    timerInterval = setInterval(function() {
        remainingTime--;
        updateTimerDisplay();
        
        if (remainingTime <= 0) {
            clearInterval(timerInterval);
            submitExam(true); // Soumettre automatiquement l'examen
        }
    }, 1000);
}

// Mettre à jour l'affichage du minuteur
function updateTimerDisplay() {
    const timerDisplay = document.getElementById('timer-display');
    if (!timerDisplay) return;
    
    const hours = Math.floor(remainingTime / 3600);
    const minutes = Math.floor((remainingTime % 3600) / 60);
    const seconds = remainingTime % 60;
    
    timerDisplay.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    // Ajouter des classes d'alerte lorsque le temps est presque écoulé
    if (remainingTime <= 300) { // 5 minutes
        document.getElementById('exam-timer').classList.add('danger');
    } else if (remainingTime <= 600) { // 10 minutes
        document.getElementById('exam-timer').classList.add('warning');
    }
}

// Configurer les événements pour les options de réponse
function setupAnswerEvents() {
    // Pour les options à choix multiples
    const optionItems = document.querySelectorAll('.option-item');
    optionItems.forEach(item => {
        item.addEventListener('click', function() {
            const questionId = this.closest('.question-container').id.replace('question-', '');
            const radio = this.querySelector('input[type="radio"]');
            
            // Désélectionner toutes les options de cette question
            const allOptions = document.querySelectorAll(`#question-${questionId} .option-item`);
            allOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Sélectionner cette option
            this.classList.add('selected');
            radio.checked = true;
            
            // Mettre à jour la navigation
            updateQuestionNavigation(questionId, true);
            
            // Sauvegarder la réponse
            saveAnswers();
        });
    });
    
    // Pour les réponses textuelles
    const textareas = document.querySelectorAll('.text-answer textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            const questionId = this.closest('.question-container').id.replace('question-', '');
            
            // Mettre à jour la navigation si la réponse n'est pas vide
            updateQuestionNavigation(questionId, this.value.trim() !== '');
        });
        
        textarea.addEventListener('blur', function() {
            // Sauvegarder la réponse lorsque l'utilisateur quitte le champ
            saveAnswers();
        });
    });
}

// Mettre à jour la navigation des questions
function updateQuestionNavigation(questionId, isAnswered) {
    const questionNumber = document.querySelector(`.question-number[data-question-id="${questionId}"]`);
    if (questionNumber) {
        if (isAnswered) {
            questionNumber.classList.add('answered');
        } else {
            questionNumber.classList.remove('answered');
        }
    }
}

// Afficher une question spécifique
function showQuestion(index) {
    if (index < 0 || index >= questions.length) {
        return;
    }
    
    // Masquer toutes les questions
    questions.forEach(q => {
        q.element.style.display = 'none';
    });
    
    // Afficher la question sélectionnée
    questions[index].element.style.display = 'block';
    
    // Mettre à jour la navigation
    const questionNumbers = document.querySelectorAll('.question-number');
    questionNumbers.forEach((num, i) => {
        if (i === index) {
            num.classList.add('active');
        } else {
            num.classList.remove('active');
        }
    });
    
    // Mettre à jour la question courante
    currentQuestion = index;
    
    // Faire défiler vers le haut
    window.scrollTo(0, 0);
}

// Sauvegarder les réponses
function saveAnswers() {
    const form = document.getElementById('exam-form');
    if (!form) return;
    
    const formData = new FormData(form);
    
    // Envoyer les données au serveur
    fetch('../api/save-answers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Réponses sauvegardées avec succès');
        } else {
            console.error('Erreur lors de la sauvegarde des réponses:', data.error);
        }
    })
    .catch(error => {
        console.error('Erreur lors de la sauvegarde des réponses:', error);
    });
}

// Afficher la confirmation de soumission
function showSubmitConfirmation() {
    // Vérifier s'il y a des questions sans réponse
    const unansweredCount = document.querySelectorAll('.question-number:not(.answered)').length;
    const unansweredWarning = document.getElementById('unanswered-warning');
    
    if (unansweredWarning) {
        if (unansweredCount > 0) {
            unansweredWarning.textContent = `Attention : Vous n'avez pas répondu à ${unansweredCount} question(s).`;
            unansweredWarning.style.display = 'block';
        } else {
            unansweredWarning.style.display = 'none';
        }
    }
    
    showModal('submit-modal');
}

// Soumettre l'examen
function submitExam(isTimeout = false) {
    // Arrêter les intervalles
    clearInterval(timerInterval);
    clearInterval(autoSaveInterval);
    
    // Si c'est un timeout, afficher un message
    if (isTimeout) {
        alert('Le temps est écoulé. Votre examen va être soumis automatiquement.');
    }
    
    // Soumettre le formulaire
    const form = document.getElementById('exam-form');
    if (form) {
        form.submit();
    }
}

// Gérer les changements de visibilité de la page
function handleVisibilityChange() {
    if (document.hidden) {
        // L'utilisateur a quitté la page
        logProctoringEvent('tab_switch', 'L\'utilisateur a changé d\'onglet ou de fenêtre');
    }
}

// Gérer les tentatives de copier-coller
function handleCopyPaste(event) {
    event.preventDefault();
    logProctoringEvent('copy_paste', `Tentative de ${event.type}`);
    alert('Les actions de copier-coller sont désactivées pendant l\'examen.');
}

// Gérer le redimensionnement de la fenêtre
function handleResize() {
    logProctoringEvent('window_resize', `Fenêtre redimensionnée à ${window.innerWidth}x${window.innerHeight}`);
}

// Enregistrer un événement de surveillance
function logProctoringEvent(type, details) {
    fetch('../api/proctoring.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'incident',
            attempt_id: attemptId,
            incident: {
                type: type,
                severity: 'medium',
                details: details,
                timestamp: new Date().toISOString()
            }
        })
    })
    .catch(error => {
        console.error('Erreur lors de l\'enregistrement de l\'événement:', error);
    });
}

// Fonctions d'interface utilisateur
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}
