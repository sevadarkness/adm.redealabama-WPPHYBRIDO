<?php
declare(strict_types=1);

/**
 * API de Favoritos
 * Gerencia páginas favoritas do usuário
 * 
 * Endpoints:
 * - GET: Lista favoritos do usuário
 * - POST action=add: Adiciona favorito
 * - POST action=remove: Remove favorito
 * - POST action=reorder: Reordena favoritos
 */

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';

header('Content-Type: application/json');

// Verifica autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autenticado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Lista favoritos
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, page_url, page_label, page_icon, sort_order, created_at
            FROM user_favorites
            WHERE user_id = :user_id
            ORDER BY sort_order ASC, created_at ASC
        ");
        $stmt->execute([':user_id' => $usuario_id]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'favorites' => $favorites
        ]);
        exit;
    }
    
    // POST - Ações
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        // Adicionar favorito
        if ($action === 'add') {
            $page_url = $input['page_url'] ?? '';
            $page_label = $input['page_label'] ?? '';
            $page_icon = $input['page_icon'] ?? 'fa-star';
            
            if (empty($page_url) || empty($page_label)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'URL e label são obrigatórios']);
                exit;
            }
            
            // Pega o próximo sort_order
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order
                FROM user_favorites
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $usuario_id]);
            $next_order = (int)$stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
            
            $stmt = $pdo->prepare("
                INSERT INTO user_favorites (user_id, page_url, page_label, page_icon, sort_order)
                VALUES (:user_id, :page_url, :page_label, :page_icon, :sort_order)
                ON DUPLICATE KEY UPDATE
                    page_label = VALUES(page_label),
                    page_icon = VALUES(page_icon),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                ':user_id' => $usuario_id,
                ':page_url' => $page_url,
                ':page_label' => $page_label,
                ':page_icon' => $page_icon,
                ':sort_order' => $next_order
            ]);
            
            log_app_event('favorites', 'add', [
                'usuario_id' => $usuario_id,
                'page_url' => $page_url
            ]);
            
            echo json_encode([
                'ok' => true,
                'message' => 'Favorito adicionado',
                'favorite_id' => (int)$pdo->lastInsertId()
            ]);
            exit;
        }
        
        // Remover favorito
        if ($action === 'remove') {
            $page_url = $input['page_url'] ?? '';
            
            if (empty($page_url)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'URL é obrigatória']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM user_favorites
                WHERE user_id = :user_id AND page_url = :page_url
            ");
            
            $stmt->execute([
                ':user_id' => $usuario_id,
                ':page_url' => $page_url
            ]);
            
            log_app_event('favorites', 'remove', [
                'usuario_id' => $usuario_id,
                'page_url' => $page_url
            ]);
            
            echo json_encode([
                'ok' => true,
                'message' => 'Favorito removido'
            ]);
            exit;
        }
        
        // Reordenar favoritos
        if ($action === 'reorder') {
            $order = $input['order'] ?? [];
            
            if (!is_array($order) || empty($order)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Array de ordem inválido']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE user_favorites
                SET sort_order = :sort_order
                WHERE user_id = :user_id AND id = :id
            ");
            
            foreach ($order as $index => $favorite_id) {
                $stmt->execute([
                    ':sort_order' => $index,
                    ':user_id' => $usuario_id,
                    ':id' => (int)$favorite_id
                ]);
            }
            
            $pdo->commit();
            
            log_app_event('favorites', 'reorder', [
                'usuario_id' => $usuario_id,
                'count' => count($order)
            ]);
            
            echo json_encode([
                'ok' => true,
                'message' => 'Favoritos reordenados'
            ]);
            exit;
        }
        
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ação inválida']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido']);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    log_app_event('api', 'favorites_error', [
        'error' => $e->getMessage(),
        'usuario_id' => $usuario_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro ao processar favoritos'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    log_app_event('api', 'favorites_error', [
        'error' => $e->getMessage(),
        'usuario_id' => $usuario_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro inesperado'
    ]);
}
