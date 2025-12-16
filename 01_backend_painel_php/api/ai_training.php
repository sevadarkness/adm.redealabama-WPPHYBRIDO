<?php
declare(strict_types=1);

/**
 * AI Training API Endpoint
 * Handles AI training data submission from Chrome extension.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../rbac.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => $status >= 200 && $status < 300,
        'data' => $data['data'] ?? $data,
        'error' => $data['error'] ?? null,
        'meta' => $data['meta'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Check authentication
$user = current_user();
if (!$user) {
    respond(['error' => ['code' => 'unauthenticated', 'message' => 'Não autenticado.']], 401);
}

$userId = (int) $user['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    global $pdo;

    // POST - Add training data
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            respond(['error' => ['code' => 'invalid_json', 'message' => 'Invalid JSON input']], 400);
        }
        
        $type = trim($input['type'] ?? '');
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        
        // Validate inputs
        if (empty($type)) {
            respond(['error' => ['code' => 'missing_type', 'message' => 'Tipo é obrigatório']], 400);
        }
        
        if (empty($title)) {
            respond(['error' => ['code' => 'missing_title', 'message' => 'Título é obrigatório']], 400);
        }
        
        if (empty($content)) {
            respond(['error' => ['code' => 'missing_content', 'message' => 'Conteúdo é obrigatório']], 400);
        }
        
        $allowedTypes = ['product', 'faq', 'canned'];
        if (!in_array($type, $allowedTypes, true)) {
            respond(['error' => ['code' => 'invalid_type', 'message' => 'Tipo inválido']], 400);
        }
        
        // Insert training sample
        // Map types to appropriate format
        $mensagemUsuario = '';
        $respostaBot = '';
        $tags = '';
        
        if ($type === 'product') {
            $mensagemUsuario = "Informações sobre o produto: {$title}";
            $respostaBot = $content;
            $tags = 'produto';
        } elseif ($type === 'faq') {
            $mensagemUsuario = $title;
            $respostaBot = $content;
            $tags = 'faq';
        } elseif ($type === 'canned') {
            $mensagemUsuario = "Resposta rápida: {$title}";
            $respostaBot = $content;
            $tags = 'resposta_rapida';
        }
        
        $sql = "INSERT INTO llm_training_samples 
                (fonte, mensagem_usuario, resposta_bot, resposta_ajustada, aprovado, marcado_por_id, tags, created_at)
                VALUES ('extension', :mensagem_usuario, :resposta_bot, :resposta_ajustada, 1, :marcado_por_id, :tags, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':mensagem_usuario' => $mensagemUsuario,
            ':resposta_bot' => $respostaBot,
            ':resposta_ajustada' => $respostaBot,
            ':marcado_por_id' => $userId,
            ':tags' => $tags,
        ]);
        
        $sampleId = (int) $pdo->lastInsertId();
        
        log_app_event('ai_training', 'sample_added', [
            'user_id' => $userId,
            'sample_id' => $sampleId,
            'type' => $type,
            'tags' => $tags,
        ]);
        
        respond([
            'message' => 'Treinamento adicionado com sucesso',
            'id' => $sampleId,
            'type' => $type,
        ], 201);
    }

    // GET - List training samples (optional, for future use)
    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $tags = $_GET['tags'] ?? null;
        
        $sql = "SELECT id, fonte, mensagem_usuario, resposta_bot, tags, aprovado, created_at 
                FROM llm_training_samples";
        
        $conditions = [];
        $params = [];
        
        if ($tags) {
            $conditions[] = "tags LIKE :tags";
            $params[':tags'] = "%{$tags}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        respond([
            'data' => $samples,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($samples),
            ],
        ]);
    }

    // Other methods not allowed
    respond(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);

} catch (Throwable $e) {
    log_app_event('ai_training_api', 'error', [
        'user_id' => $userId,
        'method' => $method,
        'error' => $e->getMessage(),
    ]);
    
    respond([
        'error' => [
            'code' => 'internal_error',
            'message' => 'Internal server error',
        ],
    ], 500);
}
