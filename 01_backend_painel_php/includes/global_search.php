<?php
declare(strict_types=1);

/**
 * Modal de Busca Global (Ctrl+K)
 * Permite buscar rapidamente entre todas as páginas do sistema
 */
?>

<!-- Global Search Modal -->
<div id="globalSearchModal" class="al-search-modal">
    <div class="al-search-backdrop"></div>
    <div class="al-search-container">
        <div class="al-search-header">
            <i class="fas fa-search"></i>
            <input type="text" 
                   id="globalSearchInput" 
                   placeholder="Buscar páginas... (Ctrl+K)" 
                   autocomplete="off"
                   autofocus>
            <button class="al-search-close" id="globalSearchClose" title="Fechar (ESC)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="globalSearchResults" class="al-search-results">
            <!-- Resultados dinâmicos inseridos via JavaScript -->
            <div class="al-search-empty">
                <i class="fas fa-search"></i>
                <p>Digite para buscar páginas</p>
            </div>
        </div>
        
        <div class="al-search-footer">
            <div class="al-search-hints">
                <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
                <span><kbd>↵</kbd> abrir</span>
                <span><kbd>esc</kbd> fechar</span>
            </div>
        </div>
    </div>
</div>

<script>
// Array com todas as páginas do sistema para busca
window.alAllPages = [
    // Dashboard
    { url: 'painel_admin.php', label: 'Administração', category: 'Dashboard', icon: 'fa-cogs' },
    { url: 'painel_gerente.php', label: 'Painel Gerente', category: 'Dashboard', icon: 'fa-user-tie' },
    { url: 'painel_vendedor.php', label: 'Minhas Vendas', category: 'Dashboard', icon: 'fa-tachometer-alt' },
    { url: 'painel_vendedor_hoje.php', label: 'Operação Hoje', category: 'Dashboard', icon: 'fa-calendar-day' },
    { url: 'dashboard_supremacy.php', label: 'Dashboard Supremacy', category: 'Dashboard', icon: 'fa-crown' },
    { url: 'relatorios.php', label: 'Relatórios', category: 'Dashboard', icon: 'fa-file-alt' },
    
    // CRM
    { url: 'leads.php', label: 'Leads', category: 'CRM', icon: 'fa-user-plus' },
    { url: 'base_clientes.php', label: 'Base de Clientes', category: 'CRM', icon: 'fa-address-book' },
    { url: 'agenda.php', label: 'Agenda', category: 'CRM', icon: 'fa-calendar' },
    { url: 'sessoes_atendimento.php', label: 'Tempo de Atendimento', category: 'CRM', icon: 'fa-clock' },
    { url: 'playbooks.php', label: 'Playbooks', category: 'CRM', icon: 'fa-book-open' },
    
    // Vendas
    { url: 'nova_venda.php', label: 'Nova Venda', category: 'Vendas', icon: 'fa-plus-circle' },
    { url: 'vendas.php', label: 'Top Vendas', category: 'Vendas', icon: 'fa-trophy' },
    { url: 'preju.php', label: 'Relatório de Prejuízo', category: 'Vendas', icon: 'fa-exclamation-triangle' },
    
    // Estoque
    { url: 'estoque_vendedor.php', label: 'Estoque', category: 'Estoque', icon: 'fa-warehouse' },
    { url: 'relatorioestoq.php', label: 'Relatório Estoque', category: 'Estoque', icon: 'fa-chart-bar' },
    { url: 'diagnostico_estoque.php', label: 'Diagnóstico Estoque', category: 'Estoque', icon: 'fa-search-plus' },
    { url: 'catalogo.php', label: 'Catálogo', category: 'Estoque', icon: 'fa-book' },
    { url: 'adicionar_produto.php', label: 'Adicionar Produto', category: 'Estoque', icon: 'fa-plus' },
    { url: 'editar_estoque.php', label: 'Editar Estoque', category: 'Estoque', icon: 'fa-edit' },
    
    // Marketing
    { url: 'REMARK.php', label: 'Remarketing', category: 'Marketing', icon: 'fa-redo' },
    { url: 'remarketing_inteligente.php', label: 'Remarketing Inteligente', category: 'Marketing', icon: 'fa-brain' },
    { url: 'ia_campaigns_dashboard.php', label: 'Campanhas IA', category: 'Marketing', icon: 'fa-paper-plane' },
    
    // WhatsApp
    { url: 'whatsapp_bot_console.php', label: 'Conversas WhatsApp', category: 'WhatsApp', icon: 'fa-comments' },
    { url: 'whatsapp_bot_config.php', label: 'Bot IA Config', category: 'WhatsApp', icon: 'fa-robot' },
    { url: 'flows_manager.php', label: 'Fluxos IA', category: 'WhatsApp', icon: 'fa-project-diagram' },
    { url: 'flows_visual_builder.php', label: 'Visual Builder', category: 'WhatsApp', icon: 'fa-drafting-compass' },
    { url: 'flows_versions.php', label: 'Versões de Fluxos', category: 'WhatsApp', icon: 'fa-code-branch' },
    { url: 'whatsapp_handover.php', label: 'Handover WhatsApp', category: 'WhatsApp', icon: 'fa-user-shield' },
    { url: 'flow_governance.php', label: 'Governança de Fluxos', category: 'WhatsApp', icon: 'fa-shield-alt' },
    { url: 'whatsapp_manual_send.php', label: 'Envio Manual', category: 'WhatsApp', icon: 'fa-paper-plane' },
    
    // Automação
    { url: 'automation_rules.php', label: 'Regras de Automação', category: 'Automação', icon: 'fa-cog' },
    { url: 'automation_runner.php', label: 'Runner de Automação', category: 'Automação', icon: 'fa-play-circle' },
    { url: 'jobs_painel.php', label: 'Jobs / Tarefas', category: 'Automação', icon: 'fa-tasks' },
    { url: 'matching_inteligente.php', label: 'Matching Inteligente', category: 'Automação', icon: 'fa-route' },
    
    // IA & Analytics
    { url: 'admin_assistant.php', label: 'Assistente IA', category: 'IA', icon: 'fa-user-astronaut' },
    { url: 'llm_training_hub.php', label: 'LLM Training Hub', category: 'IA', icon: 'fa-graduation-cap' },
    { url: 'llm_templates.php', label: 'Templates LLM', category: 'IA', icon: 'fa-file-code' },
    { url: 'llm_analytics_dashboard.php', label: 'Analytics IA', category: 'IA', icon: 'fa-chart-line' },
    { url: 'ia_insights_dashboard.php', label: 'Insights IA', category: 'IA', icon: 'fa-lightbulb' },
    { url: 'vendor_ai_prefs.php', label: 'Minhas Preferências IA', category: 'IA', icon: 'fa-sliders-h' },
    { url: 'ia_user_audit.php', label: 'Auditoria IA', category: 'IA', icon: 'fa-clipboard-check' },
    
    // Logística
    { url: 'frete.php', label: 'Frete', category: 'Logística', icon: 'fa-shipping-fast' },
    
    // Configurações
    { url: 'env_editor.php', label: 'Config .env', category: 'Configurações', icon: 'fa-file-code' },
    { url: 'apply_env_dashboard.php', label: 'Status apply-env', category: 'Configurações', icon: 'fa-sync-alt' },
    { url: 'audit_dashboard.php', label: 'Auditoria', category: 'Configurações', icon: 'fa-clipboard-list' },
];
</script>
