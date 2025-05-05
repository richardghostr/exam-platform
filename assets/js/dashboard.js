/**
 * Script pour le tableau de bord étudiant
 * Gère l'affichage des données, les graphiques et les interactions utilisateur
 */
document.addEventListener("DOMContentLoaded", () => {
    // Initialisation des variables
    const studentId = document.body.dataset.studentId;
    const dashboardStats = document.getElementById("dashboard-stats");
    const upcomingExams = document.getElementById("upcoming-exams");
    const recentResults = document.getElementById("recent-results");
    const activityChart = document.getElementById("activity-chart");
    const performanceChart = document.getElementById("performance-chart");
    const notificationsList = document.getElementById("notifications-list");
    const notificationBadge = document.getElementById("notification-badge");
    const markAllReadBtn = document.getElementById("mark-all-read");
    
    // Initialisation du tableau de bord
    initDashboard();
    
    /**
     * Initialise le tableau de bord et charge les données
     */
    function initDashboard() {
      // Charger les statistiques du tableau de bord
      loadDashboardStats();
      
      // Charger les examens à venir
      loadUpcomingExams();
      
      // Charger les résultats récents
      loadRecentResults();
      
      // Initialiser les graphiques
      initCharts();
      
      // Charger les notifications
      loadNotifications();
      
      // Initialiser les gestionnaires d'événements
      initEventListeners();
      
      // Mettre à jour l'heure actuelle
      updateCurrentTime();
      
      console.log("Tableau de bord initialisé avec succès");
    }
    
    /**
     * Charge les statistiques du tableau de bord
     */
    function loadDashboardStats() {
      if (!dashboardStats || !studentId) return;
      
      // Simuler un chargement
      dashboardStats.innerHTML = `
        <div class="loading-spinner">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
          </div>
        </div>
      `;
      
      // Charger les données depuis le serveur
      fetch(`ajax/get-dashboard-stats.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Mettre à jour les statistiques
            dashboardStats.innerHTML = `
              <div class="row">
                <div class="col-md-3">
                  <div class="stat-card">
                    <div class="stat-card-body">
                      <div class="stat-card-icon bg-primary">
                        <i class="fas fa-file-alt"></i>
                      </div>
                      <div class="stat-card-info">
                        <div class="stat-card-title">Examens passés</div>
                        <div class="stat-card-value">${data.stats.completed_exams}</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-card">
                    <div class="stat-card-body">
                      <div class="stat-card-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                      </div>
                      <div class="stat-card-info">
                        <div class="stat-card-title">Examens réussis</div>
                        <div class="stat-card-value">${data.stats.passed_exams}</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-card">
                    <div class="stat-card-body">
                      <div class="stat-card-icon bg-info">
                        <i class="fas fa-calendar-alt"></i>
                      </div>
                      <div class="stat-card-info">
                        <div class="stat-card-title">Examens à venir</div>
                        <div class="stat-card-value">${data.stats.upcoming_exams}</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-card">
                    <div class="stat-card-body">
                      <div class="stat-card-icon bg-warning">
                        <i class="fas fa-chart-line"></i>
                      </div>
                      <div class="stat-card-info">
                        <div class="stat-card-title">Note moyenne</div>
                        <div class="stat-card-value">${data.stats.average_score}%</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            `;
          } else {
            console.error("Erreur lors du chargement des statistiques:", data.message);
            dashboardStats.innerHTML = `
              <div class="alert alert-danger">
                Une erreur est survenue lors du chargement des statistiques. Veuillez rafraîchir la page.
              </div>
            `;
          }
        })
        .catch(error => {
          console.error("Erreur lors du chargement des statistiques:", error);
          dashboardStats.innerHTML = `
            <div class="alert alert-danger">
              Une erreur est survenue lors du chargement des statistiques. Veuillez rafraîchir la page.
            </div>
          `;
        });
    }
    
    /**
     * Charge les examens à venir
     */
    function loadUpcomingExams() {
      if (!upcomingExams || !studentId) return;
      
      // Simuler un chargement
      upcomingExams.innerHTML = `
        <div class="loading-spinner">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
          </div>
        </div>
      `;
      
      // Charger les données depuis le serveur
      fetch(`ajax/get-upcoming-exams.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.exams.length > 0) {
            // Mettre à jour la liste des examens à venir
            let examsList = `
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Examen</th>
                      <th>Date</th>
                      <th>Durée</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
            `;
            
            data.exams.forEach(exam => {
              const examDate = new Date(exam.exam_date);
              const formattedDate = examDate.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              });
              
              examsList += `
                <tr>
                  <td>${exam.exam_title}</td>
                  <td>${formattedDate}</td>
                  <td>${exam.duration} minutes</td>
                  <td>
                    <a href="exam-details.php?exam_id=${exam.exam_id}" class="btn btn-sm btn-primary">
                      <i class="fas fa-info-circle"></i> Détails
                    </a>
                  </td>
                </tr>
              `;
            });
            
            examsList += `
                  </tbody>
                </table>
              </div>
            `;
            
            upcomingExams.innerHTML = examsList;
          } else if (data.success && data.exams.length === 0) {
            upcomingExams.innerHTML = `
              <div class="alert alert-info">
                Aucun examen à venir pour le moment.
              </div>
            `;
          } else {
            console.error("Erreur lors du chargement des examens à venir:", data.message);
            upcomingExams.innerHTML = `
              <div class="alert alert-danger">
                Une erreur est survenue lors du chargement des examens à venir. Veuillez rafraîchir la page.
              </div>
            `;
          }
        })
        .catch(error => {
          console.error("Erreur lors du chargement des examens à venir:", error);
          upcomingExams.innerHTML = `
            <div class="alert alert-danger">
              Une erreur est survenue lors du chargement des examens à venir. Veuillez rafraîchir la page.
            </div>
          `;
        });
    }
    
    /**
     * Charge les résultats récents
     */
    function loadRecentResults() {
      if (!recentResults || !studentId) return;
      
      // Simuler un chargement
      recentResults.innerHTML = `
        <div class="loading-spinner">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
          </div>
        </div>
      `;
      
      // Charger les données depuis le serveur
      fetch(`ajax/get-recent-results.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.results.length > 0) {
            // Mettre à jour la liste des résultats récents
            let resultsList = `
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Examen</th>
                      <th>Date</th>
                      <th>Score</th>
                      <th>Statut</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
            `;
            
            data.results.forEach(result => {
              const resultDate = new Date(result.completion_date);
              const formattedDate = resultDate.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
              });
              
              const statusClass = result.passed ? 'text-success' : 'text-danger';
              const statusText = result.passed ? 'Réussi' : 'Échoué';
              
              resultsList += `
                <tr>
                  <td>${result.exam_title}</td>
                  <td>${formattedDate}</td>
                  <td>${result.score}%</td>
                  <td><span class="${statusClass}">${statusText}</span></td>
                  <td>
                    <a href="exam-result.php?attempt_id=${result.attempt_id}" class="btn btn-sm btn-info">
                      <i class="fas fa-eye"></i> Voir
                    </a>
                  </td>
                </tr>
              `;
            });
            
            resultsList += `
                  </tbody>
                </table>
              </div>
            `;
            
            recentResults.innerHTML = resultsList;
          } else if (data.success && data.results.length === 0) {
            recentResults.innerHTML = `
              <div class="alert alert-info">
                Aucun résultat d'examen disponible pour le moment.
              </div>
            `;
          } else {
            console.error("Erreur lors du chargement des résultats récents:", data.message);
            recentResults.innerHTML = `
              <div class="alert alert-danger">
                Une erreur est survenue lors du chargement des résultats récents. Veuillez rafraîchir la page.
              </div>
            `;
          }
        })
        .catch(error => {
          console.error("Erreur lors du chargement des résultats récents:", error);
          recentResults.innerHTML = `
            <div class="alert alert-danger">
              Une erreur est survenue lors du chargement des résultats récents. Veuillez rafraîchir la page.
            </div>
          `;
        });
    }
    
    /**
     * Initialise les graphiques
     */
    function initCharts() {
      if (!activityChart || !performanceChart || !studentId) return;
      
      // Charger les données pour les graphiques
      fetch(`ajax/get-chart-data.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Initialiser le graphique d'activité
            initActivityChart(data.activity_data);
            
            // Initialiser le graphique de performance
            initPerformanceChart(data.performance_data);
          } else {
            console.error("Erreur lors du chargement des données des graphiques:", data.message);
          }
        })
        .catch(error => {
          console.error("Erreur lors du chargement des données des graphiques:", error);
        });
    }
    
    /**
     * Initialise le graphique d'activité
     * @param {Array} activityData - Les données d'activité
     */
    function initActivityChart(activityData) {
      if (!activityChart) return;
      
      // Vérifier si Chart.js est disponible
      if (typeof Chart === 'undefined') {
        console.error("Chart.js n'est pas chargé");
        return;
      }
      
      // Créer le graphique d'activité
      const ctx = activityChart.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: activityData.labels,
          datasets: [{
            label: 'Examens passés',
            data: activityData.values,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0
              }
            }
          },
          plugins: {
            legend: {
              display: true,
              position: 'top'
            },
            tooltip: {
              mode: 'index',
              intersect: false
            }
          }
        }
      });
    }
    
    /**
     * Initialise le graphique de performance
     * @param {Array} performanceData - Les données de performance
     */
    function initPerformanceChart(performanceData) {
      if (!performanceChart) return;
      
      // Vérifier si Chart.js est disponible
      if (typeof Chart === 'undefined') {
        console.error("Chart.js n'est pas chargé");
        return;
      }
      
      // Créer le graphique de performance
      const ctx = performanceChart.getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: performanceData.labels,
          datasets: [{
            label: 'Score (%)',
            data: performanceData.values,
            backgroundColor: performanceData.values.map(value => {
              if (value >= 80) return 'rgba(75, 192, 192, 0.7)'; // Vert
              if (value >= 60) return 'rgba(255, 206, 86, 0.7)'; // Jaune
              return 'rgba(255, 99, 132, 0.7)'; // Rouge
            }),
            borderColor: performanceData.values.map(value => {
              if (value >= 80) return 'rgba(75, 192, 192, 1)'; // Vert
              if (value >= 60) return 'rgba(255, 206, 86, 1)'; // Jaune
              return 'rgba(255, 99, 132, 1)'; // Rouge
            }),
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
              ticks: {
                callback: function(value) {
                  return value + '%';
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return context.dataset.label + ': ' + context.raw + '%';
                }
              }
            }
          }
        }
      });
    }
    
    /**
     * Charge les notifications
     */
    function loadNotifications() {
      if (!notificationsList || !notificationBadge || !studentId) return;
      
      // Charger les données depuis le serveur
      fetch(`ajax/get-notifications.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Mettre à jour le badge de notification
            const unreadCount = data.notifications.filter(notification => !notification.read).length;
            notificationBadge.textContent = unreadCount;
            notificationBadge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
            
            // Mettre à jour la liste des notifications
            if (data.notifications.length > 0) {
              let notificationsHtml = '';
              
              data.notifications.forEach(notification => {
                const notificationDate = new Date(notification.created_at);
                const formattedDate = notificationDate.toLocaleDateString('fr-FR', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                });
                
                const readClass = notification.read ? 'notification-read' : 'notification-unread';
                
                notificationsHtml += `
                  <div class="notification-item ${readClass}" data-notification-id="${notification.id}">
                    <div class="notification-icon">
                      <i class="fas ${getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                      <div class="notification-title">${notification.title}</div>
                      <div class="notification-message">${notification.message}</div>
                      <div class="notification-time">${formattedDate}</div>
                    </div>
                    <div class="notification-actions">
                      <button class="btn btn-sm btn-link mark-read-btn" data-notification-id="${notification.id}">
                        <i class="fas fa-check"></i>
                      </button>
                    </div>
                  </div>
                `;
              });
              
              notificationsList.innerHTML = notificationsHtml;
              
              // Ajouter les gestionnaires d'événements pour les boutons de lecture
              document.querySelectorAll('.mark-read-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                  e.preventDefault();
                  const notificationId = button.dataset.notificationId;
                  markNotificationAsRead(notificationId);
                });
              });
              
              // Ajouter les gestionnaires d'événements pour les notifications
              document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', () => {
                  const notificationId = item.dataset.notificationId;
                  markNotificationAsRead(notificationId);
                  
                  // Rediriger vers la page appropriée en fonction du type de notification
                  if (item.dataset.link) {
                    window.location.href = item.dataset.link;
                  }
                });
              });
            } else {
              notificationsList.innerHTML = `
                <div class="no-notifications">
                  <i class="fas fa-bell-slash"></i>
                  <p>Aucune notification pour le moment</p>
                </div>
              `;
            }
          } else {
            console.error("Erreur lors du chargement des notifications:", data.message);
            notificationsList.innerHTML = `
              <div class="alert alert-danger">
                Une erreur est survenue lors du chargement des notifications.
              </div>
            `;
          }
        })
        .catch(error => {
          console.error("Erreur lors du chargement des notifications:", error);
          notificationsList.innerHTML = `
            <div class="alert alert-danger">
              Une erreur est survenue lors du chargement des notifications.
            </div>
          `;
        });
    }
    
    /**
     * Marque une notification comme lue
     * @param {string} notificationId - L'ID de la notification
     */
    function markNotificationAsRead(notificationId) {
      if (!studentId) return;
      
      fetch('ajax/mark-notification-read.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}&student_id=${studentId}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Mettre à jour l'interface utilisateur
            const notificationItem = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
              notificationItem.classList.remove('notification-unread');
              notificationItem.classList.add('notification-read');
            }
            
            // Mettre à jour le badge de notification
            const unreadCount = parseInt(notificationBadge.textContent) - 1;
            notificationBadge.textContent = unreadCount > 0 ? unreadCount : '';
            notificationBadge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
          } else {
            console.error("Erreur lors du marquage de la notification comme lue:", data.message);
          }
        })
        .catch(error => {
          console.error("Erreur lors du marquage de la notification comme lue:", error);
        });
    }
    
    /**
     * Marque toutes les notifications comme lues
     */
    function markAllNotificationsAsRead() {
      if (!studentId) return;
      
      fetch('ajax/mark-all-notifications-read.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${studentId}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Mettre à jour l'interface utilisateur
            document.querySelectorAll('.notification-unread').forEach(item => {
              item.classList.remove('notification-unread');
              item.classList.add('notification-read');
            });
            
            // Mettre à jour le badge de notification
            notificationBadge.textContent = '';
            notificationBadge.style.display = 'none';
          } else {
            console.error("Erreur lors du marquage de toutes les notifications comme lues:", data.message);
          }
        })
        .catch(error => {
          console.error("Erreur lors du marquage de toutes les notifications comme lues:", error);
        });
    }
    
    /**
     * Obtient l'icône appropriée pour un type de notification
     * @param {string} type - Le type de notification
     * @returns {string} - La classe d'icône FontAwesome
     */
    function getNotificationIcon(type) {
      switch (type) {
        case 'exam':
          return 'fa-file-alt';
        case 'result':
          return 'fa-chart-bar';
        case 'warning':
          return 'fa-exclamation-triangle';
        case 'info':
          return 'fa-info-circle';
        case 'success':
          return 'fa-check-circle';
        default:
          return 'fa-bell';
      }
    }
    
    /**
     * Initialise les gestionnaires d'événements
     */
    function initEventListeners() {
      // Gestionnaire pour le bouton "Marquer tout comme lu"
      if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', (e) => {
          e.preventDefault();
          markAllNotificationsAsRead();
        });
      }
      
      // Gestionnaire pour le bouton de rafraîchissement
      const refreshBtn = document.getElementById('refresh-dashboard');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', (e) => {
          e.preventDefault();
          refreshDashboard();
        });
      }
    }
    
    /**
     * Rafraîchit le tableau de bord
     */
    function refreshDashboard() {
      // Afficher un indicateur de chargement
      const loadingOverlay = document.createElement('div');
      loadingOverlay.className = 'loading-overlay';
      loadingOverlay.innerHTML = `
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Chargement...</span>
        </div>
      `;
      document.body.appendChild(loadingOverlay);
      
      // Recharger toutes les données
      Promise.all([
        loadDashboardStats(),
        loadUpcomingExams(),
        loadRecentResults(),
        loadNotifications()
      ])
        .then(() => {
          // Supprimer l'indicateur de chargement
          document.body.removeChild(loadingOverlay);
          
          // Afficher une notification de succès
          showNotification('Tableau de bord mis à jour', 'Les données du tableau de bord ont été mises à jour avec succès.', 'success');
        })
        .catch(error => {
          console.error("Erreur lors du rafraîchissement du tableau de bord:", error);
          
          // Supprimer l'indicateur de chargement
          document.body.removeChild(loadingOverlay);
          
          // Afficher une notification d'erreur
          showNotification('Erreur', 'Une erreur est survenue lors de la mise à jour du tableau de bord.', 'danger');
        });
    }
    
    /**
     * Met à jour l'heure actuelle
     */
    function updateCurrentTime() {
      const currentTimeElement = document.getElementById('current-time');
      if (!currentTimeElement) return;
      
      // Mettre à jour l'heure toutes les secondes
      setInterval(() => {
        const now = new Date();
        const formattedTime = now.toLocaleTimeString('fr-FR', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        });
        currentTimeElement.textContent = formattedTime;
      }, 1000);
    }
    
    /**
     * Affiche une notification
     * @param {string} title - Le titre de la notification
     * @param {string} message - Le message de la notification
     * @param {string} type - Le type de notification (info, success, warning, danger)
     */
    function showNotification(title, message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `toast toast-${type} show`;
      notification.setAttribute('role', 'alert');
      notification.setAttribute('aria-live', 'assertive');
      notification.setAttribute('aria-atomic', 'true');
      
      notification.innerHTML = `
        <div class="toast-header">
          <strong class="me-auto">${title}</strong>
          <small>À l'instant</small>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
        <div class="toast-body">
          ${message}
        </div>
      `;
      
      // Ajouter la notification au conteneur
      const toastContainer = document.querySelector('.toast-container');
      if (toastContainer) {
        toastContainer.appendChild(notification);
      } else {
        // Créer un conteneur si nécessaire
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.appendChild(notification);
        document.body.appendChild(container);
      }
      
      // Fermer automatiquement après 5 secondes
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          notification.remove();
        }, 300);
      }, 5000);
      
      // Gestionnaire pour le bouton de fermeture
      const closeBtn = notification.querySelector('.btn-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          notification.classList.remove('show');
          setTimeout(() => {
            notification.remove();
          }, 300);
        });
      }
    }
  });
  