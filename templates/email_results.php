<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats de l'examen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header img {
            max-width: 150px;
        }
        h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }
        h2 {
            color: #3498db;
            font-size: 20px;
            margin-top: 0;
        }
        .result-summary {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .summary-item:last-child {
            margin-bottom: 0;
        }
        .summary-label {
            font-weight: bold;
        }
        .questions-list {
            margin-top: 20px;
        }
        .question-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .question-item:last-child {
            border-bottom: none;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .question-text {
            margin-bottom: 10px;
        }
        .option-item {
            margin-left: 15px;
            margin-bottom: 5px;
        }
        .correct {
            color: #27ae60;
        }
        .incorrect {
            color: #e74c3c;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="ExamSafe Logo">
        <h1>Résultats de l'examen</h1>
        <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
    </div>
    
    <div class="message">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
    
    <div class="result-summary">
        <div class="summary-item">
            <span class="summary-label">Étudiant:</span>
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Date:</span>
            <span><?php echo date('d/m/Y H:i', strtotime($attempt['start_time'])); ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Score:</span>
            <span><?php echo round($attempt['score'], 1); ?>%</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Statut:</span>
            <span class="<?php echo $attempt['score'] >= $exam['passing_score'] ? 'correct' : 'incorrect'; ?>">
                <?php echo $attempt['score'] >= $exam['passing_score'] ? 'Réussi' : 'Échoué'; ?>
            </span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Score minimum requis:</span>
            <span><?php echo $exam['passing_score']; ?>%</span>
        </div>
    </div>
    
    <div class="questions-list">
        <h3>Détail des questions</h3>
        
        <?php foreach ($questions as $index => $question): ?>
            <div class="question-item">
                <div class="question-header">
                    <div class="question-number">Question <?php echo $index + 1; ?></div>
                    <div class="question-points <?php echo $question['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <?php echo $question['points_awarded']; ?>/<?php echo $question['points']; ?> points
                    </div>
                </div>
                
                <div class="question-text">
                    <?php echo htmlspecialchars($question['question_text']); ?>
                </div>
                
                <?php if ($question['question_type'] !== 'essay'): ?>
                    <div class="options-list">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="option-item">
                                <?php if (in_array($option['id'], explode(',', $question['selected_options'] ?? ''))): ?>
                                    <?php if ($option['is_correct']): ?>
                                        ✓ <span class="correct"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <?php else: ?>
                                        ✗ <span class="incorrect"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($option['is_correct']): ?>
                                        <span class="correct">[Correct] <?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="answer-text">
                        <strong>Réponse:</strong> <?php echo nl2br(htmlspecialchars($question['answer_text'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="footer">
        <p>Cet email a été généré automatiquement par la plateforme ExamSafe.</p>
        <p>© <?php echo date('Y'); ?> ExamSafe - Tous droits réservés</p>
    </div>
</body>
</html>