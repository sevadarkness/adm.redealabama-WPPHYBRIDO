<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';

if ($_SESSION['nivel_acesso'] != 'Administrador') {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = (new \RedeAlabama\Repositories\Screens\EditarUsuarioRepository($pdo))->prepare_231();
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo "Usuário não encontrado!";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $nivel_acesso = $_POST['nivel_acesso'];

    $stmt = (new \RedeAlabama\Repositories\Screens\EditarUsuarioRepository($pdo))->prepare_593();
    $stmt->execute([$nome, $telefone, $nivel_acesso, $id]);

    header("Location: painel_admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h4>Editar Usuário</h4>
    <form action="" method="POST">
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $usuario['nome']; ?>" required>
        </div>
        <div class="form-group">
            <label for="telefone">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $usuario['telefone']; ?>" required>
        </div>
        <div class="form-group">
            <label for="nivel_acesso">Nível de Acesso</label>
            <select class="form-control" id="nivel_acesso" name="nivel_acesso" required>
                <option value="Gerente" <?php echo ($usuario['nivel_acesso'] == 'Gerente') ? 'selected' : ''; ?>>Gerente</option>
                <option value="Vendedor" <?php echo ($usuario['nivel_acesso'] == 'Vendedor') ? 'selected' : ''; ?>>Vendedor</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    </form>
</div>
</body>
</html>
