<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/security_helpers.php';

// Garante que apenas Gerente e Administrador acessem
require_role(['Gerente', 'Administrador']);

include 'menu_navegacao.php';

// Usuário atual
$user = current_user();
$nomeUsuario = $user['nome'] ?? 'Gerente';

// ============== KPIs DE VENDAS ==============

// Total de vendas hoje
$stmt_hoje = $pdo->query("
    SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor
    FROM vendas
    WHERE DATE(data_venda) = CURDATE() AND produto_avariado = 0
");
$vendas_hoje = $stmt_hoje->fetch(PDO::FETCH_ASSOC);

// Total de vendas esta semana
$stmt_semana = $pdo->query("
    SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor
    FROM vendas
    WHERE YEARWEEK(data_venda, 1) = YEARWEEK(CURDATE(), 1) AND produto_avariado = 0
");
$vendas_semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);

// Total de vendas este mês
$stmt_mes = $pdo->query("
    SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor
    FROM vendas
    WHERE YEAR(data_venda) = YEAR(CURDATE()) AND MONTH(data_venda) = MONTH(CURDATE()) AND produto_avariado = 0
");
$vendas_mes = $stmt_mes->fetch(PDO::FETCH_ASSOC);

// Ticket médio do mês
$ticket_medio = $vendas_mes['total'] > 0 ? $vendas_mes['valor'] / $vendas_mes['total'] : 0;

// ============== RANKING DE VENDEDORES ==============
$stmt_ranking = $pdo->query("
    SELECT 
        u.nome AS vendedor_nome,
        COUNT(v.id) AS total_vendas,
        COALESCE(SUM(v.valor_total), 0) AS valor_total
    FROM vendas v
    JOIN usuarios u ON v.id_vendedor = u.id
    WHERE YEAR(v.data_venda) = YEAR(CURDATE()) 
        AND MONTH(v.data_venda) = MONTH(CURDATE())
        AND v.produto_avariado = 0
    GROUP BY u.id, u.nome
    ORDER BY valor_total DESC
    LIMIT 10
");
$ranking_vendedores = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

// ============== TOP 5 PRODUTOS DO MÊS ==============
$stmt_produtos = $pdo->query("
    SELECT 
        p.nome AS produto_nome,
        COUNT(v.id) AS vezes_vendido,
        COALESCE(SUM(v.quantidade), 0) AS quantidade_total
    FROM vendas v
    JOIN produtos p ON v.produto_id = p.id
    WHERE YEAR(v.data_venda) = YEAR(CURDATE()) 
        AND MONTH(v.data_venda) = MONTH(CURDATE())
        AND v.produto_avariado = 0
    GROUP BY p.id, p.nome
    ORDER BY quantidade_total DESC
    LIMIT 5
");
$top_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

// ============== MÉTRICAS DE LEADS ==============
$stmt_leads_novos = $pdo->query("
    SELECT COUNT(*) as total FROM leads WHERE status = 'novo'
");
$leads_novos = $stmt_leads_novos->fetchColumn();

$stmt_leads_atendimento = $pdo->query("
    SELECT COUNT(*) as total FROM leads WHERE status = 'em_atendimento'
");
$leads_atendimento = $stmt_leads_atendimento->fetchColumn();

$stmt_leads_convertidos = $pdo->query("
    SELECT COUNT(*) as total FROM leads WHERE status = 'convertido'
");
$leads_convertidos = $stmt_leads_convertidos->fetchColumn();

// ============== VENDAS ÚLTIMOS 7 DIAS (GRÁFICO) ==============
$stmt_grafico = $pdo->query("
    SELECT 
        DATE(data_venda) AS dia,
        COALESCE(SUM(valor_total), 0) AS total_vendas
    FROM vendas
    WHERE DATE(data_venda) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND produto_avariado = 0
    GROUP BY DATE(data_venda)
    ORDER BY DATE(data_venda) ASC
");
$vendas_7_dias = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

// Preenche os dias faltantes com zero
$ultimos_7_dias = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $ultimos_7_dias[$dia] = 0;
}
foreach ($vendas_7_dias as $row) {
    $ultimos_7_dias[$row['dia']] = (float)$row['total_vendas'];
}

// ============== ESTOQUE BAIXO ==============
$stmt_estoque = $pdo->query("
    SELECT 
        p.nome AS produto_nome,
        s.sabor AS sabor_nome,
        SUM(ev.quantidade) AS quantidade_total
    FROM estoque_vendedores ev
    JOIN produtos p ON ev.produto_id = p.id
    LEFT JOIN sabores s ON ev.sabor_id = s.id
    GROUP BY p.id, s.id
    HAVING quantidade_total > 0 AND quantidade_total <= 10
    ORDER BY quantidade_total ASC
    LIMIT 10
");
$estoque_baixo = $stmt_estoque->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Gerente - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .dashboard-header {
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: var(--al-radius-lg);
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--al-primary);
        }
        
        .kpi-label {
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--al-primary);
            padding-bottom: 0.5rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: var(--al-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .icon-box.blue { background-color: rgba(139, 92, 246, 0.1); color: var(--al-primary); }
        .icon-box.green { background-color: rgba(74, 222, 128, 0.1); color: var(--al-success); }
        .icon-box.orange { background-color: rgba(251, 191, 36, 0.1); color: var(--al-warning); }
        .icon-box.purple { background-color: rgba(139, 92, 246, 0.15); color: var(--al-primary-hover); }
    </style>
</head>
<body class="al-body">

<div class="container-fluid mt-4">
    <div class="dashboard-header text-center">
        <h2><i class="fas fa-chart-line"></i> Painel do Gerente</h2>
        <p class="mb-0">Bem-vindo, <?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?>! Visão geral do desempenho de vendas</p>
    </div>

    <!-- KPIs de Vendas -->
    <div class="row">
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box blue">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Vendas Hoje</div>
                        <div class="kpi-value"><?php echo (int)$vendas_hoje['total']; ?></div>
                        <small class="text-muted">R$ <?php echo number_format($vendas_hoje['valor'], 2, ',', '.'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box green">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Vendas Semana</div>
                        <div class="kpi-value"><?php echo (int)$vendas_semana['total']; ?></div>
                        <small class="text-muted">R$ <?php echo number_format($vendas_semana['valor'], 2, ',', '.'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box orange">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Vendas Mês</div>
                        <div class="kpi-value"><?php echo (int)$vendas_mes['total']; ?></div>
                        <small class="text-muted">R$ <?php echo number_format($vendas_mes['valor'], 2, ',', '.'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box purple">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Ticket Médio</div>
                        <div class="kpi-value">R$ <?php echo number_format($ticket_medio, 2, ',', '.'); ?></div>
                        <small class="text-muted">Este mês</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Ranking de Vendedores -->
        <div class="col-md-6">
            <div class="section-card">
                <h5 class="section-title"><i class="fas fa-trophy"></i> Ranking de Vendedores - Mês Atual</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Vendedor</th>
                                <th class="text-center">Vendas</th>
                                <th class="text-right">Total (R$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ranking_vendedores)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhuma venda registrada este mês</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ranking_vendedores as $idx => $vendedor): ?>
                                    <tr>
                                        <td>
                                            <?php if ($idx === 0): ?>
                                                <i class="fas fa-crown text-warning"></i>
                                            <?php else: ?>
                                                <?php echo $idx + 1; ?>º
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($vendedor['vendedor_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo (int)$vendedor['total_vendas']; ?></span>
                                        </td>
                                        <td class="text-right font-weight-bold">
                                            <?php echo number_format($vendedor['valor_total'], 2, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top 5 Produtos -->
        <div class="col-md-6">
            <div class="section-card">
                <h5 class="section-title"><i class="fas fa-box"></i> Top 5 Produtos Mais Vendidos - Mês</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Produto</th>
                                <th class="text-center">Vendas</th>
                                <th class="text-center">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_produtos)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhum produto vendido este mês</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_produtos as $idx => $produto): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?>º</td>
                                        <td><?php echo htmlspecialchars($produto['produto_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-success"><?php echo (int)$produto['vezes_vendido']; ?></span>
                                        </td>
                                        <td class="text-center font-weight-bold">
                                            <?php echo (int)$produto['quantidade_total']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Métricas de Leads -->
        <div class="col-md-4">
            <div class="section-card">
                <h5 class="section-title"><i class="fas fa-users"></i> Métricas de Leads</h5>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-star text-info"></i> Novos</span>
                        <span class="badge badge-info badge-pill"><?php echo (int)$leads_novos; ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-info" style="width: 100%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-comments text-warning"></i> Em Atendimento</span>
                        <span class="badge badge-warning badge-pill"><?php echo (int)$leads_atendimento; ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: 100%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-check-circle text-success"></i> Convertidos</span>
                        <span class="badge badge-success badge-pill"><?php echo (int)$leads_convertidos; ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: 100%"></div>
                    </div>
                </div>
                
                <a href="leads.php" class="btn btn-sm btn-outline-primary btn-block mt-3">
                    <i class="fas fa-eye"></i> Ver Todos os Leads
                </a>
            </div>
        </div>

        <!-- Gráfico de Vendas (7 dias) -->
        <div class="col-md-8">
            <div class="section-card">
                <h5 class="section-title"><i class="fas fa-chart-area"></i> Vendas dos Últimos 7 Dias</h5>
                <div class="chart-container">
                    <canvas id="vendasChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Estoque Baixo -->
    <?php if (!empty($estoque_baixo)): ?>
    <div class="row">
        <div class="col-12">
            <div class="section-card alert-stock">
                <h5 class="section-title"><i class="fas fa-exclamation-triangle text-warning"></i> Alertas de Estoque Baixo</h5>
                <p class="mb-3"><small>Produtos com estoque igual ou menor que 10 unidades</small></p>
                <div class="row">
                    <?php foreach ($estoque_baixo as $item): ?>
                        <div class="col-md-4 mb-2">
                            <div class="alert alert-warning mb-0 py-2">
                                <strong><?php echo htmlspecialchars($item['produto_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($item['sabor_nome']): ?>
                                    - <?php echo htmlspecialchars($item['sabor_nome'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                                <br>
                                <small>Quantidade: <strong><?php echo (int)$item['quantidade_total']; ?></strong> unidades</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ações Rápidas -->
    <div class="row">
        <div class="col-12">
            <div class="section-card">
                <h5 class="section-title"><i class="fas fa-bolt"></i> Ações Rápidas</h5>
                <div class="quick-actions">
                    <a href="relatorios.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Relatórios Completos
                    </a>
                    <a href="vendas.php" class="btn btn-success">
                        <i class="fas fa-shopping-cart"></i> Histórico de Vendas
                    </a>
                    <a href="base_clientes.php" class="btn btn-info">
                        <i class="fas fa-address-book"></i> Base de Clientes
                    </a>
                    <a href="leads.php" class="btn btn-warning">
                        <i class="fas fa-users"></i> Gerenciar Leads
                    </a>
                    <a href="diagnostico_estoque.php" class="btn btn-secondary">
                        <i class="fas fa-boxes"></i> Diagnóstico de Estoque
                    </a>
                    <?php if ($user['nivel_acesso'] === 'Administrador'): ?>
                        <a href="painel_admin.php" class="btn btn-dark">
                            <i class="fas fa-user-shield"></i> Painel Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer bg-dark text-center text-white py-3 mt-4">
    <p class="mb-0">AlabamaCMS 1.1 &copy; <?php echo date("Y"); ?> - Painel Gerencial</p>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script <?php echo alabama_csp_nonce_attr(); ?>>
// Dados para o gráfico
const vendasData = <?php echo json_encode(array_values($ultimos_7_dias), JSON_NUMERIC_CHECK); ?>;
const vendasLabels = <?php echo json_encode(array_keys($ultimos_7_dias)); ?>;

// Formatar labels de data
const formattedLabels = vendasLabels.map(label => {
    const date = new Date(label + 'T00:00:00');
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
});

// Configuração do gráfico
const ctx = document.getElementById('vendasChart').getContext('2d');
const vendasChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: formattedLabels,
        datasets: [{
            label: 'Vendas (R$)',
            data: vendasData,
            backgroundColor: 'rgba(102, 126, 234, 0.2)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toFixed(0);
                    }
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
