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

// Verifica se o usuário tem o nível de acesso adequado
$menu_vendedor = false;
$acesso_restrito = false;
if ($usuario['nivel_acesso'] == 'Administrador') {
    $menu_vendedor = true;
} elseif ($usuario['nivel_acesso'] == 'Gerente' || $usuario['nivel_acesso'] == 'Administrador') {
    $menu_vendedor = true;
} else {
    $acesso_restrito = true;
}

// Filtros de pesquisa
$pesquisa_nome = isset($_POST['pesquisa_nome']) ? $_POST['pesquisa_nome'] : '';
$pesquisa_telefone = isset($_POST['pesquisa_telefone']) ? $_POST['pesquisa_telefone'] : '';

// Paginação
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Consulta para listar os clientes
$query_clientes = "
    SELECT 
        v.telefone_cliente, 
        v.nome_cliente, 
        SUM(v.valor_total) AS total_gasto,
        GROUP_CONCAT(p.nome ORDER BY p.nome) AS produtos_comprados,
        GROUP_CONCAT(s.sabor ORDER BY s.sabor) AS sabores_comprados,
        GROUP_CONCAT(DATE_FORMAT(v.data_venda, '%d/%m/%Y') ORDER BY v.data_venda) AS datas_compras
    FROM vendas v
    JOIN produtos p ON v.produto_id = p.id
    JOIN sabores s ON v.sabor_id = s.id
    WHERE v.telefone_cliente IS NOT NULL
    AND (v.nome_cliente LIKE :pesquisa_nome OR v.telefone_cliente LIKE :pesquisa_telefone)
    GROUP BY v.telefone_cliente
    ORDER BY total_gasto DESC
    LIMIT :offset, :itens_por_pagina
";

$stmt_clientes = $pdo->prepare($query_clientes);
$stmt_clientes->bindValue(':pesquisa_nome', "%$pesquisa_nome%", PDO::PARAM_STR);
$stmt_clientes->bindValue(':pesquisa_telefone', "%$pesquisa_telefone%", PDO::PARAM_STR);
$stmt_clientes->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_clientes->bindValue(':itens_por_pagina', $itens_por_pagina, PDO::PARAM_INT);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll();

// Contagem total de clientes
$query_total_clientes = "
    SELECT COUNT(DISTINCT v.telefone_cliente) AS total_clientes
    FROM vendas v
    WHERE v.telefone_cliente IS NOT NULL
    AND (v.nome_cliente LIKE :pesquisa_nome OR v.telefone_cliente LIKE :pesquisa_telefone)
";
$stmt_total_clientes = $pdo->prepare($query_total_clientes);
$stmt_total_clientes->bindValue(':pesquisa_nome', "%$pesquisa_nome%", PDO::PARAM_STR);
$stmt_total_clientes->bindValue(':pesquisa_telefone', "%$pesquisa_telefone%", PDO::PARAM_STR);
$stmt_total_clientes->execute();
$total_clientes = $stmt_total_clientes->fetchColumn();

// Total de páginas para paginação
$total_paginas = ceil($total_clientes / $itens_por_pagina);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base de Clientes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
        }

        body {
            background-color: #f8f9fa;
        }

        .container {
            padding: 2rem 1rem;
            max-width: 1200px;
        }

        .custom-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }

        .accordion-button {
            padding: 0.5rem 1rem;
            background-color: var(--secondary-color);
            color: white;
            border-radius: 5px;
        }

        .accordion-button:hover {
            opacity: 0.9;
        }

        .alert-info {
            background-color: #e8f4fc;
            color: var(--primary-color);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="custom-card">
        <h3 class="mb-4" style="color: var(--primary-color);"><i class="fas fa-users me-2"></i>Base de Clientes</h3>

        <?php if ($acesso_restrito): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>Você não tem permissão para acessar esta página.
            </div>
        <?php else: ?>
            <form method="post" class="mb-4">
                <div class="form-row">
                    <div class="col-md-5">
                        <input type="text" name="pesquisa_nome" class="form-control" placeholder="Pesquisar por nome" value="<?php echo htmlspecialchars($pesquisa_nome); ?>">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="pesquisa_telefone" class="form-control" placeholder="Pesquisar por telefone" value="<?php echo htmlspecialchars($pesquisa_telefone); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Pesquisar</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Telefone</th>
                            <th>Nome</th>
                            <th>Total Gasto</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php $id_safe = htmlspecialchars(preg_replace('/[^a-zA-Z0-9]/', '_', $cliente['telefone_cliente'])); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['telefone_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['nome_cliente']); ?></td>
                                <td>R$ <?php echo number_format($cliente['total_gasto'], 2, ',', '.'); ?></td>
                                <td>
                                    <div class="accordion">
                                        <div class="card">
                                            <div class="card-header p-0">
                                                <button class="accordion-button" data-toggle="collapse" 
                                                        data-target="#collapse-<?php echo $id_safe; ?>">
                                                    Detalhes
                                                </button>
                                            </div>
                                            <div id="collapse-<?php echo $id_safe; ?>" class="collapse">
                                                <div class="card-body">
                                                    <strong>Produtos:</strong> <?php echo htmlspecialchars($cliente['produtos_comprados']); ?><br>
                                                    <strong>Sabores:</strong> <?php echo htmlspecialchars($cliente['sabores_comprados']); ?><br>
                                                    <strong>Datas:</strong> <?php echo htmlspecialchars($cliente['datas_compras']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
