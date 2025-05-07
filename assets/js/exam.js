/**
 * Script principal pour la page d'examen
 * Gère le minuteur, la navigation entre les questions, la sauvegarde des réponses et la surveillance
 */
document.addEventListener("DOMContentLoaded", () => {
  // Initialisation des variables
  const questionContainer = document.querySelector(".question-container")
  const timerElement = document.getElementById("exam-timer")
  const progressBar = document.querySelector(".progress-bar")
  const questionNavigation = document.querySelector(".question-navigation")
  const submitExamBtn = document.getElementById("submit-exam")
  const saveStatusIndicator = document.getElementById("save-status")

  // Récupération des données d'examen
  const examId = questionContainer ? questionContainer.dataset.examId : null
  const attemptId = questionContainer ? questionContainer.dataset.attemptId : null
  const totalTime = questionContainer ? Number.parseInt(questionContainer.dataset.totalTime) : 0
  const totalQuestions = document.querySelectorAll(".question-item").length

  let currentQuestionIndex = 0
  let timeRemaining = totalTime * 60 // Conversion en secondes
  let autoSaveInterval
  const answers = {}
  let timerInterval
  let isSubmitting = false
  const answeredQuestions = {}

  // Initialisation de l'examen
  initExam()

  /**
   * Initialise l'examen et tous les composants nécessaires
   */
  function initExam() {
    if (!examId || !attemptId) return

    // Initialiser le minuteur
    startTimer()

    // Initialiser la navigation entre les questions
    initQuestionNavigation()

    // Initialiser la sauvegarde automatique
    initAutoSave()

    // Initialiser les gestionnaires d'événements
    initEventListeners()

    // Empêcher la navigation arrière pendant l'examen
    preventBackNavigation()

    // Empêcher le clic droit
    preventRightClick()

    // Détecter quand l'utilisateur quitte la page
    detectPageLeave()

    // Détecter les tentatives de copier-coller
    preventCopyPaste()

    // Afficher la première question
    showQuestion(0)

    console.log("Examen initialisé avec succès")
  }

  /**
   * Démarre le minuteur de l'examen
   */
  function startTimer() {
    updateTimerDisplay()

    timerInterval = setInterval(() => {
      timeRemaining--

      // Mettre à jour l'affichage du minuteur
      updateTimerDisplay()

      // Mettre à jour la barre de progression
      updateProgressBar()

      // Si le temps est écoulé, soumettre automatiquement l'examen
      if (timeRemaining <= 0) {
        clearInterval(timerInterval)
        finishExam(true)
      }

      // Avertissement lorsqu'il reste 5 minutes
      if (timeRemaining === 300) {
        showNotification("Attention", "Il vous reste 5 minutes pour terminer l'examen.", "warning")
      }

      // Avertissement lorsqu'il reste 1 minute
      if (timeRemaining === 60) {
        showNotification("Attention", "Il vous reste 1 minute pour terminer l'examen.", "danger")
      }
    }, 1000)
  }

  /**
   * Met à jour l'affichage du minuteur
   */
  function updateTimerDisplay() {
    const hours = Math.floor(timeRemaining / 3600)
    const minutes = Math.floor((timeRemaining % 3600) / 60)
    const seconds = timeRemaining % 60

    timerElement.textContent = `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`

    // Changer la couleur du minuteur en fonction du temps restant
    if (timeRemaining <= 300) {
      // 5 minutes
      timerElement.classList.add("text-warning")
    }

    if (timeRemaining <= 60) {
      // 1 minute
      timerElement.classList.remove("text-warning")
      timerElement.classList.add("text-danger")
    }
  }

  /**
   * Met à jour la barre de progression
   */
  function updateProgressBar() {
    if (!progressBar) return

    const totalSeconds = totalTime * 60
    const percentageUsed = ((totalSeconds - timeRemaining) / totalSeconds) * 100
    progressBar.style.width = `${percentageUsed}%`

    // Changer la couleur de la barre de progression en fonction du temps écoulé
    if (percentageUsed >= 75) {
      progressBar.classList.remove("bg-success", "bg-warning")
      progressBar.classList.add("bg-danger")
    } else if (percentageUsed >= 50) {
      progressBar.classList.remove("bg-success", "bg-danger")
      progressBar.classList.add("bg-warning")
    }
  }

  /**
   * Initialise la navigation entre les questions
   */
  function initQuestionNavigation() {
    if (!questionNavigation) return

    // Créer les boutons de navigation pour chaque question
    for (let i = 0; i < totalQuestions; i++) {
      const navButton = document.createElement("button")
      navButton.className = "question-nav-btn"
      navButton.textContent = i + 1
      navButton.dataset.index = i

      navButton.addEventListener("click", () => {
        saveCurrentAnswer()
        showQuestion(i)
      })

      questionNavigation.appendChild(navButton)
    }

    // Ajouter les boutons précédent et suivant
    const prevBtn = document.getElementById("prev-question")
    const nextBtn = document.getElementById("next-question")

    if (prevBtn) {
      prevBtn.addEventListener("click", () => {
        if (currentQuestionIndex > 0) {
          saveCurrentAnswer()
          showQuestion(currentQuestionIndex - 1)
        }
      })
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", () => {
        if (currentQuestionIndex < totalQuestions - 1) {
          saveCurrentAnswer()
          showQuestion(currentQuestionIndex + 1)
        }
      })
    }
  }

  /**
   * Affiche une question spécifique
   * @param {number} index - L'index de la question à afficher
   */
  function showQuestion(index) {
    // Masquer toutes les questions
    const questions = document.querySelectorAll(".question-item")
    questions.forEach((q) => q.classList.add("d-none"))

    // Afficher la question sélectionnée
    const selectedQuestion = questions[index]
    if (selectedQuestion) {
      selectedQuestion.classList.remove("d-none")
      currentQuestionIndex = index

      // Mettre à jour les boutons de navigation
      updateNavigationButtons()

      // Mettre à jour l'indicateur de question actuelle
      updateQuestionIndicator()

      // Charger la réponse précédemment enregistrée (si disponible)
      loadSavedAnswer()
    }
  }

  /**
   * Met à jour les boutons de navigation (précédent/suivant)
   */
  function updateNavigationButtons() {
    const prevBtn = document.getElementById("prev-question")
    const nextBtn = document.getElementById("next-question")

    if (prevBtn) {
      prevBtn.disabled = currentQuestionIndex === 0
    }

    if (nextBtn) {
      nextBtn.disabled = currentQuestionIndex === totalQuestions - 1
    }

    // Mettre à jour les boutons de navigation des questions
    const navButtons = document.querySelectorAll(".question-nav-btn")
    navButtons.forEach((btn, index) => {
      btn.classList.remove("active")
      if (index === currentQuestionIndex) {
        btn.classList.add("active")
      }
    })
  }

  /**
   * Met à jour l'indicateur de question actuelle
   */
  function updateQuestionIndicator() {
    const questionIndicator = document.getElementById("question-indicator")
    if (questionIndicator) {
      questionIndicator.textContent = `Question ${currentQuestionIndex + 1} sur ${totalQuestions}`
    }
  }

  /**
   * Initialise la sauvegarde automatique des réponses
   */
  function initAutoSave() {
    // Sauvegarder toutes les 30 secondes
    autoSaveInterval = setInterval(() => {
      saveCurrentAnswer()
    }, 30000)
  }

  /**
   * Sauvegarde la réponse à la question actuelle
   */
  function saveCurrentAnswer() {
    const currentQuestion = document.querySelectorAll(".question-item")[currentQuestionIndex]
    if (!currentQuestion) return

    const questionId = currentQuestion.dataset.questionId
    let answer = null
    let questionType = currentQuestion.dataset.questionType || 'single_choice';

    // Déterminer le type de question et récupérer la réponse
    if (currentQuestion.querySelector('input[type="radio"]:checked')) {
      // Question à choix unique
      answer = currentQuestion.querySelector('input[type="radio"]:checked').value
      questionType = 'single_choice';
    } else if (currentQuestion.querySelectorAll('input[type="checkbox"]:checked').length > 0) {
      // Question à choix multiples
      answer = Array.from(currentQuestion.querySelectorAll('input[type="checkbox"]:checked'))
        .map((checkbox) => checkbox.value)
      questionType = 'multiple_choice';
    } else if (currentQuestion.querySelector("textarea")) {
      // Question à réponse libre
      answer = currentQuestion.querySelector("textarea").value
      questionType = 'essay';
    }

    // Si une réponse est fournie, la sauvegarder
    if (answer !== null) {
      answers[questionId] = answer

      // Marquer la question comme répondue dans la navigation
      const navButton = document.querySelector(`.question-nav-btn[data-index="${currentQuestionIndex}"]`)
      if (navButton) {
        navButton.classList.add("answered")
      }

      // Envoyer la réponse au serveur
      saveAnswer(questionId, answer, questionType)
    }
  }

  /**
   * Charge la réponse précédemment enregistrée pour la question actuelle
   */
  function loadSavedAnswer() {
    const currentQuestion = document.querySelectorAll(".question-item")[currentQuestionIndex]
    if (!currentQuestion) return

    const questionId = currentQuestion.dataset.questionId
    const savedAnswer = answers[questionId]

    if (savedAnswer !== undefined) {
      // Restaurer la réponse en fonction du type de question
      if (currentQuestion.querySelector('input[type="radio"]')) {
        // Question à choix unique
        const radioButton = currentQuestion.querySelector(`input[type="radio"][value="${savedAnswer}"]`)
        if (radioButton) {
          radioButton.checked = true
        }
      } else if (currentQuestion.querySelector('input[type="checkbox"]')) {
        // Question à choix multiples
        if (Array.isArray(savedAnswer)) {
          savedAnswer.forEach((value) => {
            const checkbox = currentQuestion.querySelector(`input[type="checkbox"][value="${value}"]`)
            if (checkbox) {
              checkbox.checked = true
            }
          })
        } else if (typeof savedAnswer === 'string') {
          const savedValues = savedAnswer.split(",")
          savedValues.forEach((value) => {
            const checkbox = currentQuestion.querySelector(`input[type="checkbox"][value="${value}"]`)
            if (checkbox) {
              checkbox.checked = true
            }
          })
        }
      } else if (currentQuestion.querySelector("textarea")) {
        // Question à réponse libre
        currentQuestion.querySelector("textarea").value = savedAnswer
      }
    }
  }

  /**
   * Envoie la réponse au serveur
   * @param {string} questionId - L'ID de la question
   * @param {string|Array} answer - La réponse de l'étudiant
   * @param {string} type - Le type de question (single_choice, multiple_choice, essay, etc.)
   */
  function saveAnswer(questionId, answer, type) {
    if (!examId || !attemptId) return

    // Mettre à jour l'indicateur de sauvegarde
    const saveStatus = document.getElementById(`saveStatus-${questionId}`) || saveStatusIndicator;
    if (saveStatus) {
      saveStatus.textContent = "Sauvegarde en cours..."
      saveStatus.className = "save-status saving"
      saveStatus.style.display = 'block';
    }

    // Préparer les données à envoyer
    let data = {
      attempt_id: attemptId,
      question_id: questionId
    };

    // Formater les données selon le type de question
    if (type === 'multiple_choice') {
      data.selected_options = Array.isArray(answer) ? answer.join(',') : answer;
    } else if (type === 'single_choice' || type === 'true_false') {
      data.selected_options = answer;
    } else if (type === 'essay' || type === 'short_answer') {
      data.answer_text = answer;
    }

    // Envoyer la requête au serveur
    fetch("../ajax/save-answer.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Erreur HTTP: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          // Mettre à jour l'indicateur de sauvegarde
          if (saveStatus) {
            saveStatus.textContent = "Réponse sauvegardée"
            saveStatus.className = "save-status saved"
          }

          // Marquer la question comme répondue
          answeredQuestions[questionId] = true;
          const questionButton = document.querySelector(`.question-button[data-question-id="${questionId}"]`);
          if (questionButton) {
            questionButton.classList.add('answered');
          }

          // Mettre à jour le compteur si présent
          const answeredCount = document.getElementById('answeredCount');
          if (answeredCount) {
            answeredCount.textContent = Object.keys(answeredQuestions).length;
          }

          // Mettre à jour l'état du bouton de fin si nécessaire
          if (typeof updateFinishButtonState === 'function') {
            updateFinishButtonState();
          }
        } else {
          console.error("Erreur lors de la sauvegarde de la réponse:", data.message)

          // Mettre à jour l'indicateur de sauvegarde
          if (saveStatus) {
            saveStatus.textContent = "Erreur de sauvegarde"
            saveStatus.className = "save-status error"
          }
        }

        // Cacher le statut après 3 secondes
        if (saveStatus) {
          setTimeout(() => {
            saveStatus.style.opacity = '0';
            setTimeout(() => {
              saveStatus.style.display = 'none';
              saveStatus.style.opacity = '1';
            }, 300);
          }, 3000);
        }
      })
      .catch((error) => {
        console.error("Erreur lors de la sauvegarde de la réponse:", error)

        // Mettre à jour l'indicateur de sauvegarde
        if (saveStatus) {
          saveStatus.textContent = "Erreur de sauvegarde"
          saveStatus.className = "save-status error"
        }
      })
  }

  /**
   * Initialise les gestionnaires d'événements
   */
  function initEventListeners() {
    // Gestionnaire pour le bouton de soumission de l'examen
    if (submitExamBtn) {
      submitExamBtn.addEventListener("click", (e) => {
        e.preventDefault()

        // Demander confirmation avant de soumettre
        const confirmation = confirm("Êtes-vous sûr de vouloir terminer l'examen ? Cette action est irréversible.")
        if (confirmation) {
          saveCurrentAnswer() // Sauvegarder la dernière réponse
          finishExam(false)
        }
      })
    }

    // Gestionnaires pour les réponses aux questions
    document.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach((input) => {
      input.addEventListener("change", () => {
        saveCurrentAnswer()
      })
    })

    document.querySelectorAll("textarea").forEach((textarea) => {
      textarea.addEventListener("blur", () => {
        saveCurrentAnswer()
      })

      // Sauvegarde automatique lors de la frappe (avec debounce)
      let typingTimer
      textarea.addEventListener("input", () => {
        clearTimeout(typingTimer)
        typingTimer = setTimeout(() => {
          saveCurrentAnswer()
        }, 1000)
      })
    })
  }

  /**
   * Termine l'examen et envoie toutes les réponses au serveur
   * @param {boolean} timeExpired - Indique si le temps est écoulé
   */
  function finishExam(timeExpired = false) {
    if (isSubmitting) return
    isSubmitting = true

    // Désactiver le bouton de soumission
    if (submitExamBtn) {
      submitExamBtn.disabled = true
      submitExamBtn.textContent = "Finalisation en cours..."
    }

    // Arrêter le minuteur et la sauvegarde automatique
    clearInterval(timerInterval)
    clearInterval(autoSaveInterval)

    // Afficher un message de chargement
    const loadingMessage = document.createElement("div")
    loadingMessage.className = "exam-submission-overlay"
    loadingMessage.innerHTML = `
      <div class="submission-content">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Chargement...</span>
        </div>
        <h3>${timeExpired ? "Temps écoulé!" : "Finalisation de l'examen"}</h3>
        <p>Veuillez patienter pendant que nous finalisons votre examen...</p>
      </div>
    `
    document.body.appendChild(loadingMessage)

    // Envoyer la demande de finalisation au serveur
    fetch("../ajax/finish-exam.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        attempt_id: attemptId,
        time_expired: timeExpired ? 1 : 0
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Rediriger vers la page de résultats
          window.location.href = data.redirect_url || "exam-result.php?attempt_id=" + attemptId
        } else {
          console.error("Erreur lors de la finalisation de l'examen:", data.message)
          alert("Une erreur est survenue lors de la finalisation de l'examen. Veuillez contacter l'administrateur.")

          // Réactiver le bouton de soumission
          if (submitExamBtn) {
            submitExamBtn.disabled = false
            submitExamBtn.textContent = "Terminer l'examen"
          }

          isSubmitting = false
          document.body.removeChild(loadingMessage)
        }
      })
      .catch((error) => {
        console.error("Erreur lors de la finalisation de l'examen:", error)
        alert("Une erreur est survenue lors de la finalisation de l'examen. Veuillez contacter l'administrateur.")

        // Réactiver le bouton de soumission
        if (submitExamBtn) {
          submitExamBtn.disabled = false
          submitExamBtn.textContent = "Terminer l'examen"
        }

        isSubmitting = false
        document.body.removeChild(loadingMessage)
      })
  }

  /**
   * Empêche la navigation arrière pendant l'examen
   */
  function preventBackNavigation() {
    history.pushState(null, null, location.href)
    window.onpopstate = () => {
      history.go(1)
    }
  }

  /**
   * Empêche le clic droit
   */
  function preventRightClick() {
    document.addEventListener("contextmenu", (e) => {
      e.preventDefault()
      showWarning("Le clic droit n'est pas autorisé pendant l'examen.")
    })
  }

  /**
   * Détecte quand l'utilisateur quitte la page
   */
  function detectPageLeave() {
    window.addEventListener("beforeunload", (e) => {
      // Annuler l'événement
      e.preventDefault()
      // Chrome requiert returnValue pour être défini
      e.returnValue = ""
    })

    // Détecter quand l'utilisateur change d'onglet ou minimise la fenêtre
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden") {
        // L'utilisateur a changé d'onglet ou minimisé la fenêtre
        reportVisibilityChange()
      }
    })
  }

  /**
   * Empêche les tentatives de copier-coller
   */
  function preventCopyPaste() {
    document.addEventListener("copy", (e) => {
      e.preventDefault()
      showWarning("La copie de texte n'est pas autorisée pendant l'examen.")
    })

    document.addEventListener("paste", (e) => {
      e.preventDefault()
      showWarning("Le collage de texte n'est pas autorisé pendant l'examen.")
    })

    document.addEventListener("cut", (e) => {
      e.preventDefault()
      showWarning("Le coupage de texte n'est pas autorisé pendant l'examen.")
    })
  }

  /**
   * Signale un changement de visibilité
   */
  function reportVisibilityChange() {
    if (!attemptId) return

    fetch("../ajax/report-incident.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        attempt_id: attemptId,
        incident_type: "tab_switch",
        description: "L'étudiant a changé d'onglet ou minimisé la fenêtre"
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("Visibility change reported:", data)
      })
      .catch((error) => {
        console.error("Error reporting visibility change:", error)
      })
  }

  /**
   * Affiche un avertissement
   * @param {string} message - Le message d'avertissement
   */
  function showWarning(message) {
    const warning = document.createElement("div")
    warning.className = "proctoring-notification"
    warning.innerHTML = `
      <div class="notification-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div class="notification-content">
        <div class="notification-title">Action non autorisée</div>
        <div class="notification-message">${message}</div>
      </div>
    `
    document.body.appendChild(warning)

    // Animer la notification
    setTimeout(() => {
      warning.classList.add("show")
    }, 10)

    // Cacher la notification après 3 secondes
    setTimeout(() => {
      warning.classList.remove("show")
      setTimeout(() => {
        document.body.removeChild(warning)
      }, 300)
    }, 3000)
  }

  /**
   * Affiche une notification
   * @param {string} title - Le titre de la notification
   * @param {string} message - Le message de la notification
   * @param {string} type - Le type de notification (info, success, warning, danger)
   */
  function showNotification(title, message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `notification notification-${type}`
    notification.innerHTML = `
      <div class="notification-header">
        <h4>${title}</h4>
        <button class="close-notification">&times;</button>
      </div>
      <div class="notification-body">
        <p>${message}</p>
      </div>
    `

    // Ajouter la notification au conteneur
    const notificationContainer = document.querySelector(".notification-container")
    if (notificationContainer) {
      notificationContainer.appendChild(notification)
    } else {
      // Créer un conteneur si nécessaire
      const container = document.createElement("div")
      container.className = "notification-container"
      container.appendChild(notification)
      document.body.appendChild(container)
    }

    // Gérer la fermeture de la notification
    const closeBtn = notification.querySelector(".close-notification")
    if (closeBtn) {
      closeBtn.addEventListener("click", () => {
        notification.classList.add("closing")
        setTimeout(() => {
          notification.remove()
        }, 300)
      })
    }

    // Fermer automatiquement après 5 secondes
    setTimeout(() => {
      notification.classList.add("closing")
      setTimeout(() => {
        notification.remove()
      }, 300)
    }, 5000)
  }
})
