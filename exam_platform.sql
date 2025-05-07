

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) 

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `teacher_id` int NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) 

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--

CREATE TABLE `class_enrollments` (
  `id` int NOT NULL,
  `class_id` int NOT NULL,
  `user_id` int NOT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive','pending') DEFAULT 'active'
) 
-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `description` text,
  `teacher_id` int DEFAULT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) 

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `description`, `teacher_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Biologie', 'BIO101', 'Cours fondamental de biologie cellulaire et moléculaire', NULL, 'active', '2025-05-01 15:17:43', NULL),
(2, 'Chimie', 'CHM101', 'Introduction à la chimie générale et organique', NULL, 'active', '2025-05-01 15:17:43', NULL),
(3, 'Informatique', 'INF101', 'Fondements de la programmation et algorithmique', NULL, 'active', '2025-05-01 15:17:43', NULL),
(4, 'Mathématiques', 'MAT101', 'Algèbre linéaire et calcul différentiel', NULL, 'active', '2025-05-01 15:17:43', NULL),
(5, 'Physique', 'PHY101', 'Mécanique classique et thermodynamique', NULL, 'active', '2025-05-01 15:17:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `subject` int NOT NULL,
  `course_id` int DEFAULT NULL,
  `duration` int NOT NULL COMMENT 'en minutes',
  `passing_score` int NOT NULL COMMENT 'en pourcentage',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_proctored` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','published','scheduled','completed') NOT NULL DEFAULT 'draft',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `teacher_id` int DEFAULT NULL,
  `has_essay` int DEFAULT NULL,
  `randomize_questions` int DEFAULT NULL,
  `show_results` int DEFAULT NULL,
  `allow_retake` int DEFAULT NULL,
  `max_retakes` int DEFAULT NULL,
  `enable_face_recognition` int DEFAULT NULL,
  `enable_eye_tracking` int DEFAULT NULL,
  `enable_audio_monitoring` int DEFAULT NULL,
  `enable_screen_monitoring` int DEFAULT NULL,
  `question_count` int DEFAULT NULL,
  `proctoring_enabled` int DEFAULT NULL,
  `proctoring_settings` json DEFAULT NULL
)
--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `title`, `description`, `subject`, `course_id`, `duration`, `passing_score`, `start_date`, `end_date`, `is_proctored`, `status`, `created_by`, `created_at`, `updated_at`, `teacher_id`, `has_essay`, `randomize_questions`, `show_results`, `allow_retake`, `max_retakes`, `enable_face_recognition`, `enable_eye_tracking`, `enable_audio_monitoring`, `enable_screen_monitoring`, `question_count`, `proctoring_enabled`, `proctoring_settings`) VALUES
(19, 'CC 5', 'tres dur', 2, NULL, 60, 60, '2025-05-01 15:40:00', '2025-05-15 15:35:00', 1, 'published', 3, '2025-05-01 15:35:58', '2025-05-04 21:18:35', 3, 0, 1, 0, 0, 1, 1, 1, 1, 1, NULL, 1, NULL),
(23, 'CC 5', 'tres dur', 1, NULL, 60, 60, '2024-05-02 21:19:00', '2026-05-31 16:19:00', 1, 'published', 2, '2025-05-02 16:19:35', '2025-05-04 21:18:35', 2, 0, 1, 1, 0, 1, 1, 1, 1, 1, NULL, 1, NULL),
(24, 'SN 2', 'DETERMINE', 1, NULL, 60, 60, '2026-06-02 23:09:00', '2026-02-01 23:00:00', 0, 'published', 3, '2025-05-03 09:32:31', '2025-05-04 12:20:21', 3, 1, 1, 1, 1, 4, 1, 1, 1, 1, NULL, NULL, NULL),
(25, 'CC 4', 'Facile', 2, NULL, 60, 60, '2024-05-02 23:00:00', '2026-05-04 23:00:00', 1, 'published', 3, '2025-05-04 12:31:35', '2025-05-04 21:18:35', 3, 0, 1, 1, 0, 1, 1, 1, 1, 1, NULL, 1, NULL),
(26, 'SN', 'TRES DUR', 2, NULL, 60, 60, '2025-04-23 23:00:00', '2026-09-23 03:00:00', 1, 'published', 3, '2025-05-05 07:27:31', '2025-05-05 20:40:11', 3, 0, 1, 0, 1, 4, 1, 1, 1, 1, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `user_id` int NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed','graded','failed') NOT NULL DEFAULT 'in_progress',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `enrollment_id` int DEFAULT NULL
) 
--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `exam_id`, `user_id`, `start_time`, `end_time`, `score`, `status`, `created_at`, `updated_at`, `enrollment_id`) VALUES
(30, 19, 5, '2025-05-06 10:09:01', NULL, NULL, 'in_progress', '2025-05-06 09:09:01', NULL, NULL),
(31, 26, 5, '2025-05-06 10:25:38', NULL, NULL, 'in_progress', '2025-05-06 09:25:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_classes`
--

CREATE TABLE `exam_classes` (
  `id` int NOT NULL,
  `exam_id` int DEFAULT NULL,
  `class_id` int DEFAULT NULL
)

-- --------------------------------------------------------

--
-- Table structure for table `exam_enrollments`
--

CREATE TABLE `exam_enrollments` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `enrolled_at` datetime NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved'
) 
-- --------------------------------------------------------

--
-- Table structure for table `exam_results`
--

CREATE TABLE `exam_results` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `user_id` int NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_points` int NOT NULL,
  `points_earned` decimal(5,2) NOT NULL,
  `passing_score` int NOT NULL,
  `passed` tinyint(1) NOT NULL DEFAULT '0',
  `time_spent` int NOT NULL COMMENT 'en secondes',
  `completed_at` datetime NOT NULL,
  `graded_by` int DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `feedback` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `status` text,
  `is_graded` text
)

-- --------------------------------------------------------

--
-- Table structure for table `exam_sessions`
--

CREATE TABLE `exam_sessions` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `user_id` int NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('in_progress','completed','terminated') NOT NULL DEFAULT 'in_progress',
  `total_score` decimal(10,2) DEFAULT NULL,
  `incident_count` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `attempt_id` timestamp NULL DEFAULT NULL
) 

--
-- Dumping data for table `exam_sessions`
--

INSERT INTO `exam_sessions` (`id`, `exam_id`, `user_id`, `start_time`, `end_time`, `status`, `total_score`, `incident_count`, `created_at`, `updated_at`, `attempt_id`) VALUES
(1, 19, 5, '2025-05-04 20:36:50', NULL, 'in_progress', NULL, 0, '2025-05-04 19:36:50', '2025-05-04 19:36:50', NULL),
(2, 23, 5, '2025-05-04 20:54:42', NULL, 'in_progress', NULL, 0, '2025-05-04 19:54:42', '2025-05-04 19:54:42', NULL),
(3, 26, 5, '2025-05-05 08:38:04', NULL, 'in_progress', NULL, 0, '2025-05-05 07:38:04', '2025-05-05 07:38:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `face_descriptors`
--

CREATE TABLE `face_descriptors` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `descriptor_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) 

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) 
-- --------------------------------------------------------

--
-- Table structure for table `proctoring_images`
--

CREATE TABLE `proctoring_images` (
  `id` int NOT NULL,
  `session_id` int NOT NULL,
  `student_id` int NOT NULL,
  `incident_type` enum('face','gaze','screen') NOT NULL,
  `description` text,
  `image_path` varchar(255) NOT NULL,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) 
-- --------------------------------------------------------

--
-- Table structure for table `proctoring_incidents`
--

CREATE TABLE `proctoring_incidents` (
  `id` int NOT NULL,
  `attempt_id` int NOT NULL,
  `incident_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `description` text NOT NULL,
  `status` enum('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  `notes` text,
  `timestamp` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `details` text,
  `user_id` int DEFAULT NULL,
  `image_path` text,
  `resolved` int DEFAULT NULL,
  `reviewed` int DEFAULT NULL
) 
-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','single_choice','true_false','short_answer','essay') NOT NULL,
  `points` int NOT NULL DEFAULT '1',
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `question_type_id` int DEFAULT NULL,
  `question_order` text,
  `type` text,
  `explanation` varchar(500) CHARACTER SET armscii8 COLLATE armscii8_bin NOT NULL DEFAULT 'Aucune'
)

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `exam_id`, `question_text`, `question_type`, `points`, `difficulty`, `created_at`, `updated_at`, `question_type_id`, `question_order`, `type`, `explanation`) VALUES
(11, 19, 'gvjh', 'short_answer', 1, 'medium', '2025-05-01 19:22:52', NULL, NULL, NULL, NULL, 'Aucune'),
(12, 19, 'gvjh', 'short_answer', 1, 'medium', '2025-05-01 19:23:14', NULL, NULL, NULL, NULL, 'Aucune'),
(13, 19, 'gvjh', 'short_answer', 1, 'medium', '2025-05-01 19:23:42', NULL, NULL, NULL, NULL, 'Aucune'),
(14, 24, 'Bonjour', 'single_choice', 2, 'hard', '2025-05-03 09:35:05', NULL, NULL, NULL, NULL, 'Aucune'),
(15, 24, 'ri', 'true_false', 1, 'medium', '2025-05-03 11:53:16', NULL, NULL, NULL, NULL, 'Aucune'),
(16, 25, 'comment allez vous', 'single_choice', 1, 'medium', '2025-05-04 12:32:25', NULL, NULL, NULL, NULL, 'Aucune'),
(17, 26, 'BIEN ?', 'true_false', 1, 'medium', '2025-05-05 07:27:50', NULL, NULL, NULL, NULL, 'Aucune');

-- --------------------------------------------------------

--
-- Table structure for table `question_answers`
--

CREATE TABLE `question_answers` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `exam_result_id` int NOT NULL,
  `user_id` int NOT NULL,
  `answer_text` text,
  `selected_option_id` int DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
)

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
)
--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`id`, `question_id`, `option_text`, `is_correct`, `created_at`, `updated_at`) VALUES
(13, 14, 'BIEN', 1, '2025-05-03 09:35:05', NULL),
(14, 14, 'MAL', 0, '2025-05-03 09:35:05', NULL),
(15, 15, 'Vrai', 1, '2025-05-03 11:53:16', NULL),
(16, 15, 'Faux', 0, '2025-05-03 11:53:16', NULL),
(17, 16, 'bien', 1, '2025-05-04 12:32:25', NULL),
(18, 16, 'mal', 0, '2025-05-04 12:32:25', NULL),
(19, 17, 'Vrai', 0, '2025-05-05 07:27:50', NULL),
(20, 17, 'Faux', 1, '2025-05-05 07:27:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `question_types`
--

CREATE TABLE `question_types` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `requires_manual_grading` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) 

--
-- Dumping data for table `question_types`
--

INSERT INTO `question_types` (`id`, `name`, `description`, `icon`, `requires_manual_grading`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'QCM', 'Question à choix multiples avec une seule réponse correcte', 'fas fa-check-circle', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(2, 'Vrai/Faux', 'Question avec deux options: Vrai ou Faux', 'fas fa-toggle-on', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(3, 'Réponse courte', 'Question nécessitant une réponse textuelle courte', 'fas fa-pencil-alt', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(4, 'Réponse longue/Essai', 'Question nécessitant une réponse textuelle longue ou un essai', 'fas fa-paragraph', 1, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(5, 'Correspondance', 'Question où l\'étudiant doit faire correspondre des éléments entre eux', 'fas fa-arrows-alt-h', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(6, 'Glisser-déposer', 'Question où l\'étudiant doit glisser des éléments à la bonne position', 'fas fa-hand-point-up', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(7, 'Numérique', 'Question nécessitant une réponse numérique', 'fas fa-calculator', 0, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(8, 'Dessin', 'Question où l\'étudiant doit dessiner ou annoter une image', 'fas fa-paint-brush', 1, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16'),
(9, 'Code', 'Question où l\'étudiant doit écrire du code informatique', 'fas fa-code', 1, 1, '2025-05-01 18:42:16', '2025-05-01 18:42:16');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `permissions` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) 
--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrateur du système avec tous les droits', 'all', '2025-05-01 08:37:35', NULL),
(2, 'teacher', 'Enseignant pouvant créer et gérer des examens', 'create_exam,edit_exam,view_results', '2025-05-01 08:37:35', NULL),
(3, 'student', 'Étudiant pouvant passer des examens', 'take_exam,view_own_results', '2025-05-01 08:37:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `value` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `description` text
) 
--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `value`, `created_at`, `updated_at`, `description`) VALUES
(1, 'site_name', 'ExamSafe', '2025-04-30 11:47:18', NULL, NULL),
(2, 'site_description', 'Plateforme d\'examens en ligne sécurisée', '2025-04-30 11:47:18', NULL, NULL),
(3, 'contact_email', 'contact@examsafe.com', '2025-04-30 11:47:18', NULL, NULL),
(4, 'maintenance_mode', '0', '2025-04-30 11:47:18', NULL, NULL),
(5, 'default_duration', '60', '2025-04-30 11:47:18', NULL, NULL),
(6, 'default_passing_score', '60', '2025-04-30 11:47:18', NULL, NULL),
(7, 'allow_retakes', '1', '2025-04-30 11:47:18', NULL, NULL),
(8, 'max_retakes', '2', '2025-04-30 11:47:18', '2025-05-04 16:16:56', NULL),
(9, 'show_results', '1', '2025-04-30 11:47:18', NULL, NULL),
(10, 'enable_face_recognition', '1', '2025-04-30 11:47:18', NULL, NULL),
(11, 'enable_eye_tracking', '1', '2025-04-30 11:47:18', NULL, NULL),
(12, 'enable_audio_monitoring', '1', '2025-04-30 11:47:18', NULL, NULL),
(13, 'enable_screen_monitoring', '1', '2025-04-30 11:47:18', NULL, NULL),
(14, 'strictness_level', 'medium', '2025-04-30 11:47:18', NULL, NULL),
(15, 'proctoring_enabled', '1', '2025-05-04 16:16:56', NULL, 'Activer la surveillance par défaut pour tous les examens'),
(16, 'webcam_required', '1', '2025-05-04 16:16:56', NULL, 'Exiger l\'accès à la webcam pour les examens surveillés'),
(17, 'audio_monitoring', '0', '2025-05-04 16:16:56', NULL, 'Activer la surveillance audio'),
(18, 'screen_monitoring', '1', '2025-05-04 16:16:56', NULL, 'Activer la surveillance de l\'écran'),
(19, 'incident_threshold', '5', '2025-05-04 16:16:56', NULL, 'Nombre d\'incidents avant notification automatique à l\'enseignant');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) 
--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Mathématiques', 'Cours de mathématiques générales', '2025-04-30 11:47:18', NULL),
(2, 'Informatique', 'Programmation et concepts informatiques', '2025-04-30 11:47:18', NULL),
(3, 'Physique', 'Principes fondamentaux de la physique', '2025-04-30 11:47:18', NULL),
(4, 'Chimie', 'Étude des substances et de leurs transformations', '2025-04-30 11:47:18', NULL),
(5, 'Biologie', 'Étude des organismes vivants', '2025-04-30 11:47:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'user',
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'user_firstname',
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'user_lastname',
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `role_id` int DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `full_name` text NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` text,
  `phone` double DEFAULT NULL,
  `bio` text
)

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `role`, `role_id`, `status`, `profile_image`, `created_at`, `updated_at`, `last_login`, `full_name`, `teacher_id`, `created_by`, `updated_by`, `phone`, `bio`) VALUES
(2, 'de reyes', '$2y$10$wBvJwApNcp5701C6ka89I.mUD.pKbd9OJMRm6tQN/2Mm6ES2/5auu', 'richardtiomela4@gmail.com', 'userRR', 'user', 'admin', 1, 'active', 'uploads/profiles/profile_2_1746097444.png', '2025-04-30 11:54:47', '2025-05-05 07:29:27', '2025-05-05 07:29:27', 'richard de reyes', NULL, NULL, NULL, NULL, NULL),
(3, 'ghost', '$2y$10$Sj2koyXoA0pwneXEH/YsrOYZsZWIJvJILF9HXGzazxqmuoy7/JcSa', 'richardtiomela5@gmail.com', 'user', 'user', 'teacher', 2, 'active', NULL, '2025-04-30 17:07:40', '2025-05-05 16:55:30', '2025-05-05 16:55:30', 'rich', NULL, NULL, NULL, 237672507275, 'none'),
(5, 'user', '$2y$10$kxjmK4KHyomYhNhM6pKxCuQbeiTqAkKsNEvQSoYM64f9xUuYraB4u', 'carinefomekong153@gmail.com', 'richard', 'Kuete Tiomela', 'student', 3, 'active', 'uploads/profiles/profile_5_1746396129.png', '2025-05-01 09:13:02', '2025-05-06 09:55:39', '2025-05-06 09:55:39', 'richard KUETE', NULL, 2, '2', NULL, NULL),
(6, 'de reyes2', '$2y$10$s2URgodTQZvOjT0SS8DvjOSVs1TKPw3TbpsmbeTHbXNoCMzWdESpa', 'carinefomekong152@gmail.com', 'user_firstname', 'user_lastname', 'student', NULL, 'active', NULL, '2025-05-02 18:47:36', '2025-05-04 16:09:35', '2025-05-04 16:09:35', 'richard de reyes2', NULL, NULL, NULL, NULL, NULL),
(7, 'de reyes4', '$2y$10$ne3dB/5PQIWTeOLn17ftk.Q674rAVVt.fXNcV4YwxZEjKV9nP2KYq', 'richardtiomela6@gmail.com', 'user_firstname', 'user_lastname', 'student', NULL, 'active', NULL, '2025-05-04 09:39:18', '2025-05-04 09:39:28', '2025-05-04 09:39:28', '4richard de reyes', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_answers`
--

CREATE TABLE `user_answers` (
  `id` int NOT NULL,
  `attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_text` text,
  `selected_options` varchar(255) DEFAULT NULL COMMENT 'IDs des options sélectionnées, séparés par des virgules',
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_awarded` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) 

-- --------------------------------------------------------

--
-- Table structure for table `user_classes`
--

CREATE TABLE `user_classes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `class_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) 

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `theme` varchar(50) DEFAULT 'light',
  `language` varchar(10) DEFAULT 'fr',
  `notifications_enabled` tinyint(1) DEFAULT '1',
  `email_notifications` tinyint(1) DEFAULT '1',
  `display_mode` varchar(20) DEFAULT 'default',
  `items_per_page` int DEFAULT '10',
  `timezone` varchar(100) DEFAULT 'Europe/Paris',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notifications` text,
  `sound_enabled` int NOT NULL DEFAULT '0',
  `accessibility_mode` int DEFAULT NULL,
  `high_contrast` int NOT NULL DEFAULT '0',
  `exam_countdown` int DEFAULT '0',
  `auto_save` int NOT NULL DEFAULT '0',
  `save_interval` int NOT NULL DEFAULT '0',
  `font_size` text
)
--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `theme`, `language`, `notifications_enabled`, `email_notifications`, `display_mode`, `items_per_page`, `timezone`, `created_at`, `updated_at`, `notifications`, `sound_enabled`, `accessibility_mode`, `high_contrast`, `exam_countdown`, `auto_save`, `save_interval`, `font_size`) VALUES
(1, 3, 'dark', 'fr', 1, 1, 'default', 10, 'Europe/Paris', '2025-05-02 06:19:14', '2025-05-02 06:21:05', '1', 0, 0, 0, 0, 0, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`class_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject` (`subject`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `exams_ibfk_3` (`course_id`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status_index` (`status`);

--
-- Indexes for table `exam_enrollments`
--
ALTER TABLE `exam_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `exam_student` (`exam_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `graded_by` (`graded_by`);

--
-- Indexes for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `face_descriptors`
--
ALTER TABLE `face_descriptors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `proctoring_images`
--
ALTER TABLE `proctoring_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `proctoring_incidents`
--
ALTER TABLE `proctoring_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `difficulty_index` (`difficulty`);

--
-- Indexes for table `question_answers`
--
ALTER TABLE `question_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `exam_result_id` (`exam_result_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `question_types`
--
ALTER TABLE `question_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `users_ibfk_1` (`role_id`);

--
-- Indexes for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `is_correct_index` (`is_correct`);

--
-- Indexes for table `user_classes`
--
ALTER TABLE `user_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_class_unique` (`user_id`,`class_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_pref` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `exam_enrollments`
--
ALTER TABLE `exam_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `face_descriptors`
--
ALTER TABLE `face_descriptors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctoring_images`
--
ALTER TABLE `proctoring_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctoring_incidents`
--
ALTER TABLE `proctoring_incidents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `question_answers`
--
ALTER TABLE `question_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `question_types`
--
ALTER TABLE `question_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_classes`
--
ALTER TABLE `user_classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `class_enrollments_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_enrollments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`subject`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `exams_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `exams_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `exam_enrollments`
--
ALTER TABLE `exam_enrollments`
  ADD CONSTRAINT `exam_enrollments_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_results`
--
ALTER TABLE `exam_results`
  ADD CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `exam_results_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  ADD CONSTRAINT `exam_sessions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `face_descriptors`
--
ALTER TABLE `face_descriptors`
  ADD CONSTRAINT `face_descriptors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctoring_images`
--
ALTER TABLE `proctoring_images`
  ADD CONSTRAINT `proctoring_images_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctoring_incidents`
--
ALTER TABLE `proctoring_incidents`
  ADD CONSTRAINT `proctoring_incidents_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_answers`
--
ALTER TABLE `question_answers`
  ADD CONSTRAINT `question_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_answers_ibfk_2` FOREIGN KEY (`exam_result_id`) REFERENCES `exam_results` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_answers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_answers_ibfk_4` FOREIGN KEY (`selected_option_id`) REFERENCES `question_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD CONSTRAINT `user_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `user_classes`
--
ALTER TABLE `user_classes`
  ADD CONSTRAINT `user_classes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
