<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Pega as informações do usuário logado
$usuario_id = $_SESSION['usuario_id'];
$query_usuario = "SELECT nome, nivel_acesso FROM usuarios WHERE id = :usuario_id";
$stmt_usuario = $pdo->prepare($query_usuario);
$stmt_usuario->bindParam(':usuario_id', $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->fetch();
$nome_vendedor = $usuario['nome'];

// Define as datas de filtro
$data_atual = date('Y-m-d');
$data_inicio_filtro = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : $data_atual;
$data_fim_filtro = isset($_GET['data_fim']) ? $_GET['data_fim'] : $data_atual;

// Query para pegar as vendas do período selecionado
$query_vendas = "SELECT v.id AS venda_id, v.nome_cliente, v.telefone_cliente, p.nome AS produto_nome, s.sabor AS sabor_nome, v.quantidade, v.valor_total, v.data_venda, v.produto_avariado
                 FROM vendas v
                 JOIN produtos p ON v.produto_id = p.id
                 LEFT JOIN sabores s ON v.sabor_id = s.id
                 WHERE v.id_vendedor = :vendedor_id AND DATE(v.data_venda) BETWEEN :data_inicio AND :data_fim";
$stmt_vendas = $pdo->prepare($query_vendas);
$stmt_vendas->bindParam(':vendedor_id', $usuario_id);
$stmt_vendas->bindParam(':data_inicio', $data_inicio_filtro);
$stmt_vendas->bindParam(':data_fim', $data_fim_filtro);
$stmt_vendas->execute();
$vendas_selecionadas = $stmt_vendas->fetchAll();

// Verificar se as vendas foram retornadas
if (!$vendas_selecionadas) {
    echo '<div class="alert alert-danger" role="alert">Nenhuma venda encontrada no período selecionado. Verifique o filtro de período e tente novamente!</div>';
}

// Query para calcular a comissão acumulada no período
$query_comissao = "SELECT SUM(valor_total) AS total_vendas_value
                   FROM vendas
                   WHERE id_vendedor = :vendedor_id AND DATE(data_venda) BETWEEN :data_inicio AND :data_fim";
$stmt_comissao = $pdo->prepare($query_comissao);
$stmt_comissao->bindParam(':vendedor_id', $usuario_id);
$stmt_comissao->bindParam(':data_inicio', $data_inicio_filtro);
$stmt_comissao->bindParam(':data_fim', $data_fim_filtro);
$stmt_comissao->execute();
$comissao_info = $stmt_comissao->fetch();
$comissao_vendedor = $comissao_info['total_vendas_value'] * 0.19;

// Query para o estoque do vendedor com filtro de quantidade > 1 e ordenação por quantidade
$query_estoque = "
    SELECT p.nome AS produto_nome, 
           s.sabor AS sabor_nome, 
           SUM(e.quantidade) AS quantidade_estoque
    FROM estoque_vendedores e
    JOIN produtos p ON e.produto_id = p.id
    LEFT JOIN sabores s ON e.sabor_id = s.id
    WHERE e.vendedor_id = :vendedor_id
    GROUP BY p.id, s.id
    HAVING SUM(e.quantidade) > 0
    ORDER BY SUM(e.quantidade) DESC
";
$stmt_estoque = $pdo->prepare($query_estoque);
$stmt_estoque->bindParam(':vendedor_id', $usuario_id);
$stmt_estoque->execute();
$estoque_completo = $stmt_estoque->fetchAll();

// Query para pegar a evolução das vendas (para o gráfico)
$query_grafico = "SELECT DATE(data_venda) AS dia, SUM(valor_total) AS total_vendas
                  FROM vendas
                  WHERE id_vendedor = :vendedor_id AND DATE(data_venda) BETWEEN :data_inicio AND :data_fim
                  GROUP BY DATE(data_venda)
                  ORDER BY DATE(data_venda)";
$stmt_grafico = $pdo->prepare($query_grafico);
$stmt_grafico->bindParam(':vendedor_id', $usuario_id);
$stmt_grafico->bindParam(':data_inicio', $data_inicio_filtro);
$stmt_grafico->bindParam(':data_fim', $data_fim_filtro);
$stmt_grafico->execute();
$evolucao_vendas = $stmt_grafico->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Vendedor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --light-bg: #f8f9fa;
            --success-bg: #e8f5e9;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system;
            background-color: var(--light-bg);
        }

        .compact-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .table-sm {
            font-size: 0.85rem;
        }

        .scrollable-table {
            max-height: 200px;
            overflow-y: auto;
        }

        .total-badge {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem;
            font-weight: 500;
        }

        .filter-box {
            background: white;
            border-radius: 10px;
            padding: 1rem;
        }

        .whatsapp-btn {
            padding: 2px 6px;
            font-size: 0.75rem;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        @media (max-width: 768px) {
            .scrollable-table {
                max-height: 150px;
            }
            
            .card-header {
                font-size: 0.9rem;
            }

            .chart-container {
                height: 250px;
            }
        }

        /* Estilo para o fundo vermelho suave */
        .bg-danger-light {
            background-color: rgba(255, 0, 0, 0.1); /* Vermelho suave */
        }
    </style>
</head>
<body>
    <div class="container-fluid py-2">
        <!-- Mensagem de boas-vindas e nome do usuário logado -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-primary" role="alert">
                    <h4 class="alert-heading">Bem-vindo, <?= $nome_vendedor ?>!</h4>
                    <p>Aqui está o seu painel de vendas. Acompanhe suas vendas, estoque e ganhos acumulados.</p>
                </div>
            </div>
        </div>

        <!-- Seção Superior: Ganhos e Filtro -->
        <div class="row mb-2">
            <div class="col-12 mb-2">
                <div class="compact-card" style="background-color: var(--success-bg);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">GANHOS ACUMULADOS</small>
                                <h5 class="mb-0">R$ <?=number_format($comissao_vendedor, 2, ',', '.')?></h5>
                            </div>
                            <i class="fas fa-coins text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mb-2">
                <div class="filter-box">
                    <form method="GET" class="form-inline">
                        <div class="form-group mr-2">
                            <input type="date" class="form-control form-control-sm" 
                                   name="data_inicio" value="<?=$data_inicio_filtro?>">
                        </div>
                        <div class="form-group mr-2">
                            <input type="date" class="form-control form-control-sm" 
                                   name="data_fim" value="<?=$data_fim_filtro?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Seção Meio: Vendas e Estoque -->
        <div class="row">
            <div class="col-md-6 mb-2">
                <div class="compact-card">
                    <div class="card-header">
                        <i class="fas fa-receipt"></i> Vendas Recentes
                    </div>
                    <div class="scrollable-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Valor</th>
                                    <th>Cliente</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vendas_selecionadas): ?>
                                    <?php foreach ($vendas_selecionadas as $venda): ?>
                                        <?php 
                                            // Verificar se a venda é um prejuízo (produto avariado)
                                            $classe_fundo = ($venda['produto_avariado'] == 1) ? 'bg-danger-light' : ''; // Fundo vermelho suave
                                            $exibir_whatsapp = ($venda['produto_avariado'] == 1) ? 'd-none' : ''; // Esconde o WhatsApp
                                        ?>
                                        <tr class="<?=$classe_fundo?>">
                                            <td>
                                                <?=$venda['produto_nome']?>
                                                <?php if ($venda['sabor_nome']): ?>
                                                    <small class="text-muted d-block"><?=$venda['sabor_nome']?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>R$ <?=number_format($venda['valor_total'], 2, ',', '.')?></td>
                                            <td>
                                                <?=$venda['nome_cliente']?>
                                                <small class="text-muted d-block"><?=$venda['telefone_cliente']?></small>
                                            </td>
                                            <td>
                                                <?php if (!$exibir_whatsapp): ?>
                                                    <?php
                                                        $telefone = preg_replace('/\D/', '', $venda['telefone_cliente']);
                                                        $telefone = (substr($telefone, 0, 2) !== '55') ? '55'.$telefone : $telefone;
                                                        $msg = urlencode("Olá {$venda['nome_cliente']}!\nAgradecemos sua compra!");
                                                    ?>
                                                    <a href="https://wa.me/<?=$telefone?>?text=<?=$msg?>" 
                                                       class="btn btn-success whatsapp-btn <?=$exibir_whatsapp?>" 
                                                       target="_blank">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-2 text-muted">Nenhuma venda registrada</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-2">
                <div class="compact-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-boxes"></i> Estoque Atual</span>
                        <?php 
                            $total_estoque = array_sum(array_column($estoque_completo, 'quantidade_estoque'));
                        ?>
                        <span class="total-badge">
                            Total: <?=$total_estoque?> unidades
                        </span>
                    </div>
                    <div class="scrollable-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Sabor</th>
                                    <th class="text-right">Qtd.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($estoque_completo): ?>
                                    <?php foreach ($estoque_completo as $item): ?>
                                        <tr>
                                            <td><?=$item['produto_nome']?></td>
                                            <td><?=$item['sabor_nome']?></td>
                                            <td class="text-right"><?=$item['quantidade_estoque']?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-2 text-muted">Estoque vazio</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção Inferior: Gráfico de Evolução -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="compact-card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Evolução das Vendas
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="grafico-evolucao-vendas"></canvas>
                    </div>
                </div>
            </div>
        </div>

        
    </div>

    <script <?php echo alabama_csp_nonce_attr(); ?>>
        var ctx = document.getElementById('grafico-evolucao-vendas').getContext('2d');
        var vendasData = <?php echo json_encode($evolucao_vendas); ?>;
        
        var labels = vendasData.map(function(item) {
            return item.dia;
        });

        var data = vendasData.map(function(item) {
            return item.total_vendas;
        });

        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Vendas Diárias',
                    data: data,
                    borderColor: 'rgba(67, 97, 238, 1)',
                    backgroundColor: 'rgba(67, 97, 238, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
			<?php include 'footer.php'; ?>
</body>
</html>