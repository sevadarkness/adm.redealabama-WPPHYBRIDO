<?php
declare(strict_types=1);

/**
 * Auto-migration: executa migrations pendentes automaticamente.
 * Seguro para rodar múltiplas vezes (idempotente).
 */

/**
 * Executa todas as migrations pendentes.
 * 
 * @param PDO $pdo Conexão PDO com o banco de dados
 * @return array<int, array<string, mixed>> Resultados das migrations executadas
 */
function auto_migrate_run(PDO $pdo): array {
    $results = [];
    
    // Migration: remarketing_campanhas
    if (!auto_migrate_table_exists($pdo, 'remarketing_campanhas')) {
        $sql = "CREATE TABLE IF NOT EXISTS remarketing_campanhas (
            id VARCHAR(32) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            ativo TINYINT(1) DEFAULT 1,
            config_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL,
            INDEX idx_ativo (ativo),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($sql);
            $results[] = ['table' => 'remarketing_campanhas', 'status' => 'created'];
        } catch (Throwable $e) {
            $results[] = ['table' => 'remarketing_campanhas', 'status' => 'error', 'message' => $e->getMessage()];
        }
    } else {
        $results[] = ['table' => 'remarketing_campanhas', 'status' => 'exists'];
    }
    
    // Migration: notifications
    if (!auto_migrate_table_exists($pdo, 'notifications')) {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            data_json TEXT,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_type (type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($sql);
            $results[] = ['table' => 'notifications', 'status' => 'created'];
        } catch (Throwable $e) {
            $results[] = ['table' => 'notifications', 'status' => 'error', 'message' => $e->getMessage()];
        }
    } else {
        $results[] = ['table' => 'notifications', 'status' => 'exists'];
    }
    
    // Migration: whatsapp_scheduled_messages
    if (!auto_migrate_table_exists($pdo, 'whatsapp_scheduled_messages')) {
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_scheduled_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            telefone VARCHAR(20) NOT NULL,
            mensagem TEXT NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
            sent_at DATETIME NULL,
            error_message TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_scheduled_at (scheduled_at),
            INDEX idx_status (status),
            INDEX idx_user_id (user_id),
            INDEX idx_status_scheduled (status, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($sql);
            $results[] = ['table' => 'whatsapp_scheduled_messages', 'status' => 'created'];
        } catch (Throwable $e) {
            $results[] = ['table' => 'whatsapp_scheduled_messages', 'status' => 'error', 'message' => $e->getMessage()];
        }
    } else {
        $results[] = ['table' => 'whatsapp_scheduled_messages', 'status' => 'exists'];
    }
    
    return $results;
}

/**
 * Verifica se uma tabela existe no banco de dados.
 * 
 * @param PDO $pdo Conexão PDO com o banco de dados
 * @param string $table Nome da tabela a verificar
 * @return bool True se a tabela existe, false caso contrário
 */
function auto_migrate_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $table]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}
