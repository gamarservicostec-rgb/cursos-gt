<?php
/**
 * API REST - Gestão Administrativa de Quizzes e Questões.
 * Retorna exclusivamente application/json.
 */

use Config\Database;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/AuthMiddleware.php';

// Requer autenticação administrativa
\Middleware\AuthMiddleware::requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $courseId = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;

        if (!$courseId) {
            echo json_encode(['success' => false, 'error' => 'Identificador do curso não informado.']);
            exit;
        }

        // Busca o quiz do curso
        $quizStmt = $db->prepare("SELECT id, title, min_score FROM quizzes WHERE course_id = :course_id LIMIT 1");
        $quizStmt->execute([':course_id' => $courseId]);
        $quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            echo json_encode(['success' => true, 'quiz' => null]);
            exit;
        }

        $quizId = (int)$quiz['id'];

        // Busca as questões do quiz
        $questStmt = $db->prepare("SELECT id, question_text, type FROM questions WHERE quiz_id = :quiz_id ORDER BY id ASC");
        $questStmt->execute([':quiz_id' => $quizId]);
        $questions = $questStmt->fetchAll(PDO::FETCH_ASSOC);

        $quizQuestions = [];
        foreach ($questions as $q) {
            $qId = (int)$q['id'];
            
            // Busca as opções para cada questão
            $optStmt = $db->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = :question_id ORDER BY id ASC");
            $optStmt->execute([':question_id' => $qId]);
            $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia opções para retornar o índice correto de forma amigável ao painel
            $correctIdx = 0;
            $formattedOptions = [];
            foreach ($options as $idx => $o) {
                if ((int)$o['is_correct'] === 1) {
                    $correctIdx = $idx;
                }
                $formattedOptions[] = [
                    'id' => (int)$o['id'],
                    'text' => $o['option_text']
                ];
            }

            $quizQuestions[] = [
                'id' => $qId,
                'text' => $q['question_text'],
                'type' => $q['type'],
                'options' => $formattedOptions,
                'correct_idx' => $correctIdx
            ];
        }

        echo json_encode([
            'success' => true,
            'quiz' => [
                'id' => $quizId,
                'title' => $quiz['title'],
                'min_score' => (int)$quiz['min_score'],
                'questions' => $quizQuestions
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        // Recebe payload JSON
        $input = json_decode(file_get_contents('php_input') ?: file_get_contents('php://input'), true);

        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Payload JSON inválido.']);
            exit;
        }

        $action = $input['action'] ?? '';

        if ($action === 'save_quiz') {
            $courseId = isset($input['course_id']) ? filter_var($input['course_id'], FILTER_VALIDATE_INT) : null;
            $quizId = isset($input['quiz_id']) ? filter_var($input['quiz_id'], FILTER_VALIDATE_INT) : null;
            $title = isset($input['title']) ? trim($input['title']) : '';
            $minScore = isset($input['min_score']) ? filter_var($input['min_score'], FILTER_VALIDATE_INT) : 70;

            if (!$courseId || empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Título e Curso são obrigatórios.']);
                exit;
            }

            if ($quizId) {
                // Atualiza
                $stmt = $db->prepare("UPDATE quizzes SET title = :title, min_score = :min_score WHERE id = :id AND course_id = :course_id");
                $stmt->execute([
                    ':title' => $title,
                    ':min_score' => $minScore,
                    ':id' => $quizId,
                    ':course_id' => $courseId
                ]);
            } else {
                // Cria novo
                $stmt = $db->prepare("INSERT INTO quizzes (course_id, title, min_score) VALUES (:course_id, :title, :min_score)");
                $stmt->execute([
                    ':course_id' => $courseId,
                    ':title' => $title,
                    ':min_score' => $minScore
                ]);
                $quizId = (int)$db->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Configurações da avaliação salvas com sucesso!',
                'quiz_id' => $quizId
            ]);
            exit;
        }

        if ($action === 'save_question') {
            $quizId = isset($input['quiz_id']) ? filter_var($input['quiz_id'], FILTER_VALIDATE_INT) : null;
            $questionId = isset($input['question_id']) ? filter_var($input['question_id'], FILTER_VALIDATE_INT) : null;
            $questionText = isset($input['question_text']) ? trim($input['question_text']) : '';
            $options = $input['options'] ?? [];
            $correctIdx = isset($input['correct_idx']) ? (int)$input['correct_idx'] : 0;

            if (!$quizId || empty($questionText) || count($options) < 2) {
                echo json_encode(['success' => false, 'error' => 'A questão deve conter texto e pelo menos 2 opções de resposta.']);
                exit;
            }

            $db->beginTransaction();

            if ($questionId) {
                // Atualiza a pergunta
                $stmt = $db->prepare("UPDATE questions SET question_text = :question_text WHERE id = :id AND quiz_id = :quiz_id");
                $stmt->execute([
                    ':question_text' => $questionText,
                    ':id' => $questionId,
                    ':quiz_id' => $quizId
                ]);

                // Remove opções antigas e recria para evitar desalinhamento
                $db->prepare("DELETE FROM question_options WHERE question_id = :question_id")->execute([':question_id' => $questionId]);
            } else {
                // Insere nova pergunta
                $stmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, type) VALUES (:quiz_id, :question_text, 'unica_escolha')");
                $stmt->execute([
                    ':quiz_id' => $quizId,
                    ':question_text' => $questionText
                ]);
                $questionId = (int)$db->lastInsertId();
            }

            // Insere as novas opções
            $optInsert = $db->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
            foreach ($options as $idx => $optText) {
                $isCorrect = ($idx === $correctIdx) ? 1 : 0;
                $optInsert->execute([
                    ':question_id' => $questionId,
                    ':option_text' => trim($optText),
                    ':is_correct' => $isCorrect
                ]);
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Pergunta salva com sucesso!',
                'question_id' => $questionId
            ]);
            exit;
        }

        if ($action === 'delete_question') {
            $questionId = isset($input['question_id']) ? filter_var($input['question_id'], FILTER_VALIDATE_INT) : null;
            $quizId = isset($input['quiz_id']) ? filter_var($input['quiz_id'], FILTER_VALIDATE_INT) : null;

            if (!$questionId || !$quizId) {
                echo json_encode(['success' => false, 'error' => 'Identificadores ausentes para remoção.']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM questions WHERE id = :id AND quiz_id = :quiz_id");
            $stmt->execute([
                ':id' => $questionId,
                ':quiz_id' => $quizId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Pergunta excluída da avaliação com sucesso!'
            ]);
            exit;
        }

        if ($action === 'delete_quiz') {
            $quizId = isset($input['quiz_id']) ? filter_var($input['quiz_id'], FILTER_VALIDATE_INT) : null;

            if (!$quizId) {
                echo json_encode(['success' => false, 'error' => 'Identificador da avaliação não fornecido.']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM quizzes WHERE id = :id");
            $stmt->execute([':id' => $quizId]);

            echo json_encode([
                'success' => true,
                'message' => 'Avaliação técnica e todas as suas questões excluídas com sucesso!'
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Ação administrativa inválida.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Método HTTP não suportado.']);
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Erro interno na API: ' . $e->getMessage()]);
}
