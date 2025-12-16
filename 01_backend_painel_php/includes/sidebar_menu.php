<?php
declare(strict_types=1);

/**
 * Sidebar Menu com Navegação Inteligente
 * Menu lateral colapsável organizado por categorias
 */

require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db_config.php';

// Verifica autenticação
if (!isset($_SESSION['usuario_id'])) {
    return;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nivel = (string)($_SESSION['nivel_acesso'] ?? 'Vendedor');
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

// Define estrutura do menu por categorias
$menuCategories = [
    'dashboard' => [
        'icon' => 'fa-chart-pie',
        'label' => '📊 Dashboard',
        'items' => [
            ['url' => 'painel_admin.php', 'label' => 'Administração', 'icon' => 'fa-cogs', 'roles' => ['Administrador']],
            ['url' => 'painel_gerente.php', 'label' => 'Painel Gerente', 'icon' => 'fa-user-tie', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'painel_vendedor.php', 'label' => 'Minhas Vendas', 'icon' => 'fa-tachometer-alt'],
            ['url' => 'painel_vendedor_hoje.php', 'label' => 'Operação Hoje', 'icon' => 'fa-calendar-day'],
            ['url' => 'dashboard_supremacy.php', 'label' => 'Dashboard Supremacy', 'icon' => 'fa-crown', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'relatorios.php', 'label' => 'Relatórios', 'icon' => 'fa-file-alt', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'crm' => [
        'icon' => 'fa-users',
        'label' => '👥 CRM',
        'items' => [
            ['url' => 'leads.php', 'label' => 'Leads', 'icon' => 'fa-user-plus', 'badge_type' => 'new_leads'],
            ['url' => 'base_clientes.php', 'label' => 'Base de Clientes', 'icon' => 'fa-address-book'],
            ['url' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'fa-calendar', 'badge_type' => 'pending_tasks'],
            ['url' => 'sessoes_atendimento.php', 'label' => 'Tempo de Atendimento', 'icon' => 'fa-clock'],
            ['url' => 'playbooks.php', 'label' => 'Playbooks', 'icon' => 'fa-book-open', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'vendas' => [
        'icon' => 'fa-cash-register',
        'label' => '💰 Vendas',
        'items' => [
            ['url' => 'nova_venda.php', 'label' => 'Nova Venda', 'icon' => 'fa-plus-circle', 'highlight' => true],
            ['url' => 'vendas.php', 'label' => 'Top Vendas', 'icon' => 'fa-trophy', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'preju.php', 'label' => 'Relatório de Prejuízo', 'icon' => 'fa-exclamation-triangle', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'estoque' => [
        'icon' => 'fa-boxes',
        'label' => '📦 Estoque',
        'items' => [
            ['url' => 'estoque_vendedor.php', 'label' => 'Estoque', 'icon' => 'fa-warehouse'],
            ['url' => 'relatorioestoq.php', 'label' => 'Relatório Estoque', 'icon' => 'fa-chart-bar'],
            ['url' => 'diagnostico_estoque.php', 'label' => 'Diagnóstico Estoque', 'icon' => 'fa-search-plus'],
            ['url' => 'catalogo.php', 'label' => 'Catálogo', 'icon' => 'fa-book'],
            ['url' => 'adicionar_produto.php', 'label' => 'Adicionar Produto', 'icon' => 'fa-plus', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'editar_estoque.php', 'label' => 'Editar Estoque', 'icon' => 'fa-edit', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'marketing' => [
        'icon' => 'fa-bullhorn',
        'label' => '📢 Marketing',
        'items' => [
            ['url' => 'REMARK.php', 'label' => 'Remarketing', 'icon' => 'fa-redo'],
            ['url' => 'remarketing_inteligente.php', 'label' => 'Remarketing Inteligente', 'icon' => 'fa-brain'],
            ['url' => 'ia_campaigns_dashboard.php', 'label' => 'Campanhas IA', 'icon' => 'fa-paper-plane', 'badge_type' => 'active_campaigns'],
        ]
    ],
    'whatsapp' => [
        'icon' => 'fa-whatsapp',
        'label' => '💬 WhatsApp',
        'items' => [
            ['url' => 'whatsapp_bot_console.php', 'label' => 'Conversas', 'icon' => 'fa-comments', 'badge_type' => 'unread_messages'],
            ['url' => 'whatsapp_bot_config.php', 'label' => 'Bot IA Config', 'icon' => 'fa-robot'],
            ['url' => 'flows_manager.php', 'label' => 'Fluxos IA', 'icon' => 'fa-project-diagram'],
            ['url' => 'flows_visual_builder.php', 'label' => 'Visual Builder', 'icon' => 'fa-drafting-compass'],
            ['url' => 'flows_versions.php', 'label' => 'Versões de Fluxos', 'icon' => 'fa-code-branch'],
            ['url' => 'whatsapp_handover.php', 'label' => 'Handover', 'icon' => 'fa-user-shield'],
            ['url' => 'flow_governance.php', 'label' => 'Governança', 'icon' => 'fa-shield-alt'],
            ['url' => 'whatsapp_manual_send.php', 'label' => 'Envio Manual', 'icon' => 'fa-paper-plane'],
        ]
    ],
    'automation' => [
        'icon' => 'fa-bolt',
        'label' => '⚡ Automação',
        'items' => [
            ['url' => 'automation_rules.php', 'label' => 'Regras', 'icon' => 'fa-cog'],
            ['url' => 'automation_runner.php', 'label' => 'Runner', 'icon' => 'fa-play-circle'],
            ['url' => 'jobs_painel.php', 'label' => 'Jobs', 'icon' => 'fa-tasks'],
            ['url' => 'matching_inteligente.php', 'label' => 'Matching Inteligente', 'icon' => 'fa-route'],
        ]
    ],
    'ia' => [
        'icon' => 'fa-brain',
        'label' => '🤖 IA & Analytics',
        'items' => [
            ['url' => 'admin_assistant.php', 'label' => 'Assistente IA', 'icon' => 'fa-user-astronaut', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'llm_training_hub.php', 'label' => 'LLM Training Hub', 'icon' => 'fa-graduation-cap'],
            ['url' => 'llm_templates.php', 'label' => 'Templates LLM', 'icon' => 'fa-file-code'],
            ['url' => 'llm_analytics_dashboard.php', 'label' => 'Analytics IA', 'icon' => 'fa-chart-line'],
            ['url' => 'ia_insights_dashboard.php', 'label' => 'Insights IA', 'icon' => 'fa-lightbulb'],
            ['url' => 'vendor_ai_prefs.php', 'label' => 'Minhas Preferências IA', 'icon' => 'fa-sliders-h'],
            ['url' => 'ia_user_audit.php', 'label' => 'Auditoria IA', 'icon' => 'fa-clipboard-check'],
        ]
    ],
    'logistica' => [
        'icon' => 'fa-truck',
        'label' => '🚚 Logística',
        'items' => [
            ['url' => 'frete.php', 'label' => 'Frete', 'icon' => 'fa-shipping-fast'],
        ]
    ],
    'config' => [
        'icon' => 'fa-cog',
        'label' => '⚙️ Configurações',
        'items' => [
            ['url' => 'env_editor.php', 'label' => 'Config .env', 'icon' => 'fa-file-code', 'roles' => ['Administrador']],
            ['url' => 'apply_env_dashboard.php', 'label' => 'Status apply-env', 'icon' => 'fa-sync-alt', 'roles' => ['Administrador']],
            ['url' => 'audit_dashboard.php', 'label' => 'Auditoria', 'icon' => 'fa-clipboard-list'],
        ]
    ],
];

// Carrega favoritos do usuário
$favorites = [];
try {
    $stmt = $pdo->prepare("
        SELECT page_url, page_label, page_icon, sort_order
        FROM user_favorites
        WHERE user_id = :user_id
        ORDER BY sort_order ASC, created_at ASC
        LIMIT 10
    ");
    $stmt->execute([':user_id' => $usuario_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciosamente falha se a tabela ainda não existe
    $favorites = [];
}

// Função para verificar se o usuário tem acesso ao item
function hasAccess($item, $nivel) {
    if (!isset($item['roles'])) {
        return true; // Sem restrição
    }
    return in_array($nivel, $item['roles'], true);
}
?>

<!-- Sidebar Navigation -->
<aside class="al-sidebar" id="alSidebar">
    <div class="al-sidebar-header">
        <a href="index.php" class="al-sidebar-brand">
            <i class="fas fa-gem"></i>
            <span class="al-sidebar-brand-text">Alabama CMS</span>
        </a>
        <button class="al-sidebar-toggle" id="alSidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- Search Button -->
    <div class="al-sidebar-search">
        <button class="al-search-trigger" id="alSearchTrigger">
            <i class="fas fa-search"></i>
            <span>Buscar páginas...</span>
            <kbd>Ctrl+K</kbd>
        </button>
    </div>
    
    <!-- Favorites Section -->
    <?php if (!empty($favorites)): ?>
    <div class="al-sidebar-section">
        <div class="al-sidebar-section-title">
            <i class="fas fa-star"></i>
            <span>Favoritos</span>
        </div>
        <ul class="al-sidebar-menu">
            <?php foreach ($favorites as $fav): ?>
                <li class="al-sidebar-item <?= $current_page === $fav['page_url'] ? 'active' : '' ?>">
                    <a href="<?= htmlspecialchars($fav['page_url'], ENT_QUOTES, 'UTF-8') ?>" class="al-sidebar-link">
                        <i class="fas <?= htmlspecialchars($fav['page_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <span><?= htmlspecialchars($fav['page_label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <button class="al-favorite-btn active" 
                                data-url="<?= htmlspecialchars($fav['page_url'], ENT_QUOTES, 'UTF-8') ?>"
                                data-label="<?= htmlspecialchars($fav['page_label'], ENT_QUOTES, 'UTF-8') ?>"
                                title="Remover dos favoritos">
                            <i class="fas fa-star"></i>
                        </button>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Menu Categories -->
    <div class="al-sidebar-nav">
        <?php foreach ($menuCategories as $categoryKey => $category): ?>
            <?php
            // Filtra itens baseado em permissões
            $visibleItems = array_filter($category['items'], function($item) use ($nivel) {
                return hasAccess($item, $nivel);
            });
            
            if (empty($visibleItems)) {
                continue; // Pula categoria sem itens visíveis
            }
            
            // Verifica se algum item da categoria está ativo
            $categoryActive = false;
            foreach ($visibleItems as $item) {
                if ($current_page === $item['url']) {
                    $categoryActive = true;
                    break;
                }
            }
            ?>
            
            <div class="al-sidebar-category <?= $categoryActive ? 'active' : '' ?>" data-category="<?= $categoryKey ?>">
                <button class="al-sidebar-category-header" data-bs-toggle="collapse" data-bs-target="#category-<?= $categoryKey ?>">
                    <span class="al-category-icon">
                        <i class="fas <?= htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <span class="al-category-label"><?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="fas fa-chevron-down al-category-arrow"></i>
                </button>
                
                <div class="collapse <?= $categoryActive ? 'show' : '' ?>" id="category-<?= $categoryKey ?>">
                    <ul class="al-sidebar-menu">
                        <?php foreach ($visibleItems as $item): ?>
                            <?php
                            $isActive = $current_page === $item['url'];
                            $isHighlight = $item['highlight'] ?? false;
                            $badgeType = $item['badge_type'] ?? null;
                            
                            // Verifica se é favorito
                            $isFavorite = false;
                            foreach ($favorites as $fav) {
                                if ($fav['page_url'] === $item['url']) {
                                    $isFavorite = true;
                                    break;
                                }
                            }
                            ?>
                            
                            <li class="al-sidebar-item <?= $isActive ? 'active' : '' ?> <?= $isHighlight ? 'highlight' : '' ?>">
                                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" class="al-sidebar-link">
                                    <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($badgeType): ?>
                                        <span class="al-badge" data-badge="<?= htmlspecialchars($badgeType, ENT_QUOTES, 'UTF-8') ?>">0</span>
                                    <?php endif; ?>
                                    <button class="al-favorite-btn <?= $isFavorite ? 'active' : '' ?>" 
                                            data-url="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-icon="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"
                                            title="<?= $isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
                                        <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-star"></i>
                                    </button>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="al-sidebar-footer">
        <a href="sair.php" class="al-sidebar-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="al-sidebar-overlay" id="alSidebarOverlay"></div>
