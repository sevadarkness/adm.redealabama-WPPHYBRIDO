<?php
declare(strict_types=1);

/**
 * WhatsApp Schedule API Endpoint
 * Handles scheduling of WhatsApp messages for authenticated users.
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

    // POST - Schedule a new message
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            respond(['error' => ['code' => 'invalid_json', 'message' => 'Invalid JSON input']], 400);
        }
        
        $telefone = trim($input['telefone'] ?? '');
        $mensagem = trim($input['mensagem'] ?? '');
        $scheduledAt = trim($input['scheduled_at'] ?? '');
        
        // Validate inputs
        if (empty($telefone)) {
            respond(['error' => ['code' => 'missing_phone', 'message' => 'Telefone é obrigatório']], 400);
        }
        
        if (empty($mensagem)) {
            respond(['error' => ['code' => 'missing_message', 'message' => 'Mensagem é obrigatória']], 400);
        }
        
        if (empty($scheduledAt)) {
            respond(['error' => ['code' => 'missing_schedule', 'message' => 'Data/hora de agendamento é obrigatória']], 400);
        }
        
        // Validate date format
        $scheduledDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $scheduledAt);
        if (!$scheduledDateTime) {
            // Try alternative format
            $scheduledDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        }
        
        if (!$scheduledDateTime) {
            respond(['error' => ['code' => 'invalid_date', 'message' => 'Formato de data inválido. Use YYYY-MM-DDTHH:MM']], 400);
        }
        
        // Check if scheduled time is in the future
        if ($scheduledDateTime <= new DateTime()) {
            respond(['error' => ['code' => 'past_date', 'message' => 'Data/hora deve ser no futuro']], 400);
        }
        
        // Insert into database
        $sql = "INSERT INTO whatsapp_scheduled_messages 
                (user_id, telefone, mensagem, scheduled_at, status) 
                VALUES (:user_id, :telefone, :mensagem, :scheduled_at, 'pending')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':telefone' => $telefone,
            ':mensagem' => $mensagem,
            ':scheduled_at' => $scheduledDateTime->format('Y-m-d H:i:s'),
        ]);
        
        $messageId = (int) $pdo->lastInsertId();
        
        log_app_event('whatsapp_schedule', 'message_scheduled', [
            'user_id' => $userId,
            'message_id' => $messageId,
            'scheduled_at' => $scheduledDateTime->format('Y-m-d H:i:s'),
        ]);
        
        respond([
            'message' => 'Mensagem agendada com sucesso',
            'id' => $messageId,
            'scheduled_at' => $scheduledDateTime->format('Y-m-d H:i:s'),
        ], 201);
    }

    // GET - List scheduled messages
    if ($method === 'GET') {
        $status = $_GET['status'] ?? 'all';
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        
        $sql = "SELECT id, telefone, mensagem, scheduled_at, status, sent_at, error_message, created_at 
                FROM whatsapp_scheduled_messages 
                WHERE user_id = :user_id";
        
        if ($status !== 'all') {
            $allowedStatuses = ['pending', 'sent', 'failed', 'cancelled'];
            if (in_array($status, $allowedStatuses, true)) {
                $sql .= " AND status = :status";
            }
        }
        
        $sql .= " ORDER BY scheduled_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        if ($status !== 'all' && in_array($status, ['pending', 'sent', 'failed', 'cancelled'], true)) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        respond([
            'data' => $messages,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($messages),
            ],
        ]);
    }

    // DELETE - Cancel a scheduled message
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            respond(['error' => ['code' => 'invalid_id', 'message' => 'Valid message ID required']], 400);
        }
        
        // Only allow cancelling pending messages
        $sql = "UPDATE whatsapp_scheduled_messages 
                SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id AND user_id = :user_id AND status = 'pending'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);
        
        if ($stmt->rowCount() > 0) {
            log_app_event('whatsapp_schedule', 'message_cancelled', [
                'user_id' => $userId,
                'message_id' => $id,
            ]);
            
            respond(['message' => 'Mensagem cancelada com sucesso', 'id' => $id]);
        } else {
            respond(['error' => ['code' => 'not_found', 'message' => 'Mensagem não encontrada ou já foi enviada']], 404);
        }
    }

    // Other methods not allowed
    respond(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);

} catch (Throwable $e) {
    log_app_event('whatsapp_schedule_api', 'error', [
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
