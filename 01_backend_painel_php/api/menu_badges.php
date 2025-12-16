<?php
declare(strict_types=1);

/**
 * API de Badges do Menu
 * Retorna contadores de notificações para exibir nos itens do menu
 */

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';

header('Content-Type: application/json');

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nivel_acesso = (string)($_SESSION['nivel_acesso'] ?? 'Vendedor');

try {
    $badges = [];
    
    // Badge: Novos Leads (leads criados hoje)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM leads 
        WHERE DATE(criado_em) = CURDATE() 
        AND status = 'novo'
    ");
    $stmt->execute();
    $badges['new_leads'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Badge: Mensagens não lidas do WhatsApp
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM whatsapp_conversas 
        WHERE unread_count > 0 
        AND status = 'ativa'
    ");
    $stmt->execute();
    $badges['unread_messages'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Badge: Campanhas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM remarketing_campanhas 
        WHERE ativo = 1
    ");
    $stmt->execute();
    $badges['active_campaigns'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Badge: Tarefas pendentes (agenda)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM agenda 
        WHERE usuario_id = :usuario_id 
        AND status = 'pendente'
        AND data_hora >= NOW()
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $badges['pending_tasks'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Badge: Vendas do dia (apenas para referência)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM vendas 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $badges['sales_today'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'ok' => true,
        'badges' => $badges,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    log_app_event('api', 'menu_badges_error', [
        'error' => $e->getMessage(),
        'usuario_id' => $usuario_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro ao carregar badges',
        'badges' => []
    ]);
} catch (Throwable $e) {
    log_app_event('api', 'menu_badges_error', [
        'error' => $e->getMessage(),
        'usuario_id' => $usuario_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro inesperado',
        'badges' => []
    ]);
}
