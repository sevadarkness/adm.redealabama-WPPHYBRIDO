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
if ($usuario['nivel_acesso'] != 'Gerente') {
    header("Location: index.php"); // Redireciona se não for gerente
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Gerente</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>


    <div class="container mt-4">
        <h3>Painel do Gerente</h3>
        <p>Bem-vindo ao painel de relatórios, aqui você pode visualizar todas as vendas realizadas e informações do vendedor.</p>
        
        <!-- Coloque aqui as informações específicas que o gerente pode acessar -->
    </div>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
