<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Consulta os produtos mais vendidos com seus sabores e a quantidade total de vendas por sabor
$query_produtos_vendidos = "
    SELECT
        p.nome AS produto_nome,
        IFNULL(s.sabor, 'Sem sabor definido') AS sabor_nome,
        COUNT(v.id) AS vezes_relacionado,
        SUM(v.quantidade) AS total_quantidade,
        COUNT(v.id) AS total_vendas_produto
    FROM vendas v
    JOIN produtos p ON v.produto_id = p.id
    LEFT JOIN sabores s ON v.sabor_id = s.id
    GROUP BY p.nome, sabor_nome
    ORDER BY total_vendas_produto DESC, vezes_relacionado DESC
";

$stmt_produtos_vendidos = $pdo->query($query_produtos_vendidos);
$produtos_vendidos = $stmt_produtos_vendidos->fetchAll();

// Organiza os dados em um formato de array, agrupando por produto
$produtos_grouped = [];
foreach ($produtos_vendidos as $row) {
    $produtos_grouped[$row['produto_nome']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Produtos e Sabores Mais Vendidos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #495057;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            margin-top: 50px;
        }

        h2 {
            font-weight: bold;
            color: #007bff;
            text-align: center;
            margin-bottom: 30px;
        }

        .produto-card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .produto-card:hover {
            transform: translateY(-5px);
        }

        .produto-header {
            font-size: 18px;
            font-weight: 600;
            color: #007bff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .produto-rank {
            font-size: 20px;
            font-weight: bold;
        }

        .total-vendas-produto {
            font-weight: bold;
            color: #28a745;
        }

        .toggle-button {
            font-size: 14px;
            cursor: pointer;
            color: #007bff;
            text-decoration: underline;
        }

        .sabor-list {
            list-style-type: none;
            padding-left: 0;
            display: none;
        }

        .sabor-item {
            background-color: #f1f3f5;
            border-radius: 5px;
            margin: 5px 0;
            font-size: 14px;
            padding: 10px;
            color: #495057;
        }

        .vezes-relacionado {
            font-size: 12px;
            color: #868e96;
        }

        .col-md-6 {
            margin-bottom: 15px;
        }

    </style>
    <script <?php echo alabama_csp_nonce_attr(); ?>>
        // Função para mostrar/esconder sabores
        function toggleSabores(produtoId) {
            var saboresList = document.getElementById('sabores-' + produtoId);
            var button = document.getElementById('toggle-btn-' + produtoId);
            if (saboresList.style.display === 'none') {
                saboresList.style.display = 'block';
                button.textContent = 'Esconder Sabores';
            } else {
                saboresList.style.display = 'none';
                button.textContent = 'Mostrar Sabores';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Top Produtos e Sabores Mais Vendidos</h2>

        <div class="row">
            <?php
            $rank = 1; // Inicializa o rank
            foreach ($produtos_grouped as $produto_nome => $sabores) :
                $total_vendas_produto = 0; // Soma o número de registros de vendas do produto
                foreach ($sabores as $sabor) {
                    $total_vendas_produto += $sabor['total_vendas_produto'];
                }
            ?>
                <div class="col-md-6">
                    <!-- Card do Produto -->
                    <div class="produto-card">
                        <div class="produto-header">
                            <span class="produto-rank"><?= $rank++ ?> - <?= htmlspecialchars($produto_nome) ?></span>
                            <span class="total-vendas-produto"><?= $total_vendas_produto ?> Total Vendidos</span>
                        </div>

                        <!-- Botão para mostrar/esconder sabores -->
                        <div>
                            <span class="toggle-button" id="toggle-btn-<?= $rank ?>" onclick="toggleSabores(<?= $rank ?>)">Mostrar Sabores</span>
                        </div>

                        <!-- Lista de Sabores -->
                        <ul class="sabor-list" id="sabores-<?= $rank ?>">
                            <?php foreach ($sabores as $sabor) : ?>
                                <li class="sabor-item">
                                    <strong><?= htmlspecialchars($sabor['sabor_nome']) ?></strong>
                                    - <?= $sabor['total_quantidade'] ?> Unidades Vendidas
                                    <div class="vezes-relacionado"><?= $sabor['vezes_relacionado'] ?> Vezes Relacionado</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
