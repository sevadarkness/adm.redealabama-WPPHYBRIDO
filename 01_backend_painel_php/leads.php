<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente', 'Vendedor'));

include __DIR__ . '/menu_navegacao.php';

// Usuário atual
$user = current_user();
$usuarioId = $user['id'] ?? null;
$nivelAcesso = $user['nivel_acesso'] ?? null;

$statuses = array('novo', 'em_atendimento', 'convertido', 'perdido', 'arquivado');
$statusFiltro = isset($_GET['status']) && in_array($_GET['status'], $statuses, true) ? $_GET['status'] : 'novo';
$busca = isset($_GET['q']) ? trim($_GET['q']) : '';
$vendedorFiltro = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : 0;

// Atualização de status (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'mudar_status') {
    $leadId = (int) ($_POST['lead_id'] ?? 0);
    $novoStatus = $_POST['status'] ?? '';
    if ($leadId > 0 && in_array($novoStatus, $statuses, true)) {
        // Regra simples: vendedor só pode alterar leads próprios
        $extraWhere = '';
        $params = [
            ':id' => $leadId,
            ':status' => $novoStatus,
        ];

        if ($nivelAcesso === 'Vendedor') {
            $extraWhere = ' AND (vendedor_responsavel_id = :vendedor_id OR vendedor_responsavel_id IS NULL)';
            $params[':vendedor_id'] = $usuarioId;
        }

        $sql = "UPDATE leads SET status = :status, atualizado_em = NOW() WHERE id = :id" . $extraWhere;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    header('Location: leads.php?' . http_build_query([
        'status'      => $statusFiltro,
        'q'           => $busca,
        'vendedor_id' => $vendedorFiltro ?: null,
    ]));
    exit;
}

// Busca de vendedores para filtro
$stmtVend = (new \RedeAlabama\Repositories\Screens\LeadsRepository($pdo))->query_1749();
$vendedores = $stmtVend->fetchAll(PDO::FETCH_ASSOC);

// Buscar leads por status para o gráfico
$leadsStatusStats = [];
try {
    $sql = "SELECT 
                status,
                COUNT(*) as quantidade
            FROM leads
            GROUP BY status
            ORDER BY quantidade DESC";
    
    $stmt = $pdo->query($sql);
    $leadsStatusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Silently fail
}

// Calcular totais para o rodapé
$totalLeads = array_sum(array_column($leadsStatusStats, 'quantidade'));
$convertidos = 0;
foreach ($leadsStatusStats as $stat) {
    if ($stat['status'] === 'convertido') {
        $convertidos = (int)$stat['quantidade'];
        break;
    }
}
$taxaConversao = $totalLeads > 0 ? number_format(($convertidos / $totalLeads) * 100, 1) : 0;

// Monta consulta de leads
$where = array();
$params = array();

// Filtro de acesso do vendedor
if ($nivelAcesso === 'Vendedor') {
    $where[] = '(vendedor_responsavel_id = :vend_atual OR vendedor_responsavel_id IS NULL)';
    $params[':vend_atual'] = $usuarioId;
}

if ($statusFiltro && $statusFiltro !== 'todos') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFiltro;
}

if ($busca !== '') {
    $where[] = '(telefone_cliente LIKE :busca OR nome_cliente LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}

if ($vendedorFiltro > 0) {
    $where[] = 'vendedor_responsavel_id = :vendedor_id';
    $params[':vendedor_id'] = $vendedorFiltro;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sqlLeads = "
    SELECT l.*, u.nome AS vendedor_nome, v.id AS venda_id_real
    FROM leads l
    LEFT JOIN usuarios u ON u.id = l.vendedor_responsavel_id
    LEFT JOIN vendas v ON v.id = l.venda_id
    {$whereSql}
    ORDER BY l.criado_em DESC
    LIMIT 500
";
$stmtLeads = $pdo->prepare($sqlLeads);
$stmtLeads->execute($params);
$leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang=\"pt-br\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Fila de Leads - Rede Alabama</title>
    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap\" rel=\"stylesheet\">
    <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\">
    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">
    <link rel=\"stylesheet\" href=\"assets/css/alabama-design-system.css\">
    <link rel=\"stylesheet\" href=\"alabama-theme.css\">
    <link rel=\"stylesheet\" href=\"assets/css/alabama-page-overrides.css\">
    <script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js\"></script>
</head>
<body class=\"al-body\">

<div class=\"container-fluid my-4\">
    <div class=\"d-flex justify-content-between align-items-center mb-3\">
        <div>
            <h1 class=\"h3 mb-1\">Fila de Leads</h1>
            <p class=\"text-muted mb-0\">Central de atendimento e captura de contatos da Rede Alabama.</p>
        </div>
        <div class=\"text-end\">
            <span class=\"badge bg-secondary\">Usuário: <?php echo htmlspecialchars($user['nome'] ?? ''); ?> (<?php echo htmlspecialchars($nivelAcesso ?? ''); ?>)</span>
        </div>
    </div>

    <!-- Leads Status Chart -->
    <div class=\"row mb-4\">
        <div class=\"col-12\">
            <div class=\"chart-card\">
                <div class=\"card-header\">
                    <h3>👥 Leads por Status</h3>
                </div>
                <div class=\"card-body\">
                    <canvas id=\"leadsChart\" height=\"300\"></canvas>
                </div>
                <div class=\"card-footer\">
                    <span class=\"stat\">Total de Leads: <?php echo number_format($totalLeads); ?></span>
                    <span class=\"stat success\">Taxa de Conversão: <?php echo $taxaConversao; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <form class=\"row g-2 align-items-end mb-3\" method=\"get\" action=\"leads.php\">
        <div class=\"col-md-3\">
            <label class=\"form-label\">Status</label>
            <select name=\"status\" class=\"form-select\">
                <option value=\"novo\" <?php echo $statusFiltro === 'novo' ? 'selected' : ''; ?>>Novos</option>
                <option value=\"em_atendimento\" <?php echo $statusFiltro === 'em_atendimento' ? 'selected' : ''; ?>>Em atendimento</option>
                <option value=\"convertido\" <?php echo $statusFiltro === 'convertido' ? 'selected' : ''; ?>>Convertidos</option>
                <option value=\"perdido\" <?php echo $statusFiltro === 'perdido' ? 'selected' : ''; ?>>Perdidos</option>
                <option value=\"arquivado\" <?php echo $statusFiltro === 'arquivado' ? 'selected' : ''; ?>>Arquivados</option>
                <option value=\"todos\" <?php echo $statusFiltro === 'todos' ? 'selected' : ''; ?>>Todos</option>
            </select>
        </div>
        <div class=\"col-md-3\">
            <label class=\"form-label\">Vendedor responsável</label>
            <select name=\"vendedor_id\" class=\"form-select\">
                <option value=\"0\">Todos</option>
                <?php foreach ($vendedores as $v): ?>
                    <option value=\"<?php echo (int)$v['id']; ?>\" <?php echo $vendedorFiltro === (int)$v['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($v['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class=\"col-md-3\">
            <label class=\"form-label\">Busca (nome ou telefone)</label>
            <input type=\"text\" name=\"q\" value=\"<?php echo htmlspecialchars($busca); ?>\" class=\"form-control\" placeholder=\"Digite parte do nome ou telefone\">
        </div>
        <div class=\"col-md-3\">
            <button type=\"submit\" class=\"btn btn-primary w-100\">Filtrar</button>
        </div>
    </form>

    <div class=\"card\">
        <div class=\"card-header d-flex justify-content-between align-items-center\">
            <span>Leads encontrados: <?php echo count($leads); ?></span>
            <span class=\"text-muted small\">Máximo de 500 registros mais recentes</span>
        </div>
        <div class=\"card-body p-0\">
            <?php if (empty($leads)): ?>
                <p class=\"p-3 text-muted mb-0\">Nenhum lead encontrado com os filtros atuais.</p>
            <?php else: ?>
                <div class=\"table-responsive\">
                    <table class=\"table table-striped table-hover mb-0 align-middle\">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Telefone</th>
                            <th>Origem</th>
                            <th>Status</th>
                            <th>Vendedor</th>
                            <th>Criado em</th>
                            <th>Atualizado em</th>
                            <th>Observação</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?php echo (int)$lead['id']; ?></td>
                                <td><?php echo htmlspecialchars($lead['nome_cliente'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $rawTel = $lead['telefone_cliente'] ?? '';
                                    $digits = preg_replace('/\D+/', '', $rawTel);
                                    $waUrl  = $digits ? 'https://wa.me/' . $digits : null;
                                    ?>
                                    <span><?php echo htmlspecialchars($rawTel); ?></span>
                                    <?php if ($waUrl): ?>
                                        <br>
                                        <a href="<?php echo $waUrl; ?>" target="_blank" class="btn btn-sm btn-success mt-1">
                                            Iniciar WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($lead['origem'] ?? ''); ?>
                                    <?php if (!empty($lead['url_origem'])): ?>
                                        <br><a href=\"<?php echo htmlspecialchars($lead['url_origem']); ?>\" target=\"_blank\" class=\"small\">abrir origem</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class=\"badge bg-<?php
                                        switch ($lead['status']) {
                                            case 'novo': echo 'info'; break;
                                            case 'em_atendimento': echo 'warning'; break;
                                            case 'convertido': echo 'success'; break;
                                            case 'perdido': echo 'danger'; break;
                                            default: echo 'secondary'; break;
                                        }
                                    ?>\">
                                        <?php echo htmlspecialchars($lead['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead['vendedor_nome'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($lead['criado_em']); ?></td>
                                <td><?php echo htmlspecialchars($lead['atualizado_em']); ?></td>
                                <td class=\"small\">
                                    <?php
                                    $obs = $lead['observacao'] ?? '';
                                    if (mb_strlen($obs) > 60) {
                                        echo htmlspecialchars(mb_substr($obs, 0, 60)) . '...';
                                    } else {
                                        echo htmlspecialchars($obs);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <form method=\"post\" class=\"d-flex flex-column gap-1\" style=\"min-width:150px;\">
                                        <input type=\"hidden\" name=\"acao\" value=\"mudar_status\">
                                        <input type=\"hidden\" name=\"lead_id\" value=\"<?php echo (int)$lead['id']; ?>\">
                                        <select name=\"status\" class=\"form-select form-select-sm mb-1\">
                                            <?php foreach ($statuses as $st): ?>
                                                <option value=\"<?php echo $st; ?>\" <?php echo $lead['status'] === $st ? 'selected' : ''; ?>>
                                                    <?php echo $st; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type=\"submit\" class=\"btn btn-sm btn-primary\">Atualizar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Leads by Status Chart (Horizontal Bar)
document.addEventListener('DOMContentLoaded', function() {
    const leadsCtx = document.getElementById('leadsChart');
    if (!leadsCtx) return;
    
    const statusData = <?php echo json_encode($leadsStatusStats); ?>;
    
    // Tradução de status
    const statusLabels = {
        'novo': 'Novo',
        'em_atendimento': 'Em Atendimento',
        'convertido': 'Convertido',
        'perdido': 'Perdido',
        'arquivado': 'Arquivado'
    };
    
    // Cores por status
    const statusColors = {
        'novo': 'rgba(59, 130, 246, 0.8)',          // Azul - Novo
        'em_atendimento': 'rgba(234, 179, 8, 0.8)', // Amarelo - Em atendimento
        'convertido': 'rgba(34, 197, 94, 0.8)',     // Verde - Convertido
        'perdido': 'rgba(239, 68, 68, 0.8)',        // Vermelho - Perdido
        'arquivado': 'rgba(148, 163, 184, 0.8)'     // Cinza - Arquivado
    };
    
    const labels = statusData.map(item => statusLabels[item.status] || item.status);
    const data = statusData.map(item => parseInt(item.quantidade));
    const colors = statusData.map(item => statusColors[item.status] || 'rgba(139, 92, 246, 0.8)');
    const hasData = data.length > 0 && data.some(val => val > 0);
    
    new Chart(leadsCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantidade',
                data: data,
                backgroundColor: colors,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y', // Barras horizontais
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => 'Total: ' + ctx.raw
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: '#888' },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                y: {
                    ticks: { 
                        color: '#fff',
                        font: { size: 12, weight: 'bold' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
    
    // Show message if no data
    if (!hasData) {
        const chartContainer = leadsCtx.parentElement;
        const noDataMsg = document.createElement('div');
        noDataMsg.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#888;text-align:center;';
        noDataMsg.innerHTML = '<i class="fas fa-chart-bar fa-3x mb-3" style="opacity:0.3;"></i><br>Sem dados de leads no sistema';
        chartContainer.style.position = 'relative';
        chartContainer.appendChild(noDataMsg);
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
