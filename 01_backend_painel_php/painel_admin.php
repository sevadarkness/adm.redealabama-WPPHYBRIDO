<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';

// Garante que apenas Administrador acesse
require_role(['Administrador']);

// Inclui navegação principal
include 'menu_navegacao.php';

$erro_add = $erro_add ?? '';

// Lida com a adição de um novo usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Proteção CSRF aplicada apenas ao fluxo de criação de usuário
    csrf_require();

    $nome         = trim($_POST['nome'] ?? '');
    $telefone     = trim($_POST['telefone'] ?? '');
    $senha        = (string)($_POST['senha'] ?? '');
    $nivel_acesso = $_POST['nivel_acesso'] ?? '';

    if ($nome === '' || $telefone === '' || $senha === '' || $nivel_acesso === '') {
        $erro_add = 'Preencha todos os campos para criar o usuário.';
    } else {
        $hash = password_hash($senha, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);

        $stmt = (new \RedeAlabama\Repositories\Screens\PainelAdminRepository($pdo))->prepare_1050();
        $stmt->execute([$nome, $telefone, $hash, $nivel_acesso]);

        log_app_event('usuarios', 'create', [
            'by_user'      => $_SESSION['usuario_id'] ?? null,
            'telefone'     => $telefone,
            'nivel_acesso' => $nivel_acesso,
        ]);

        if (function_exists('log_audit_event')) {
            log_audit_event('usuario_create', 'usuario', (int)$pdo->lastInsertId(), [
                'telefone' => $telefone,
                'nivel_acesso' => $nivel_acesso,
            ]);
        }

        header('Location: painel_admin.php?user_created=1');
        exit;
    }
}

// Busca todos os usuários
$stmt = (new \RedeAlabama\Repositories\Screens\PainelAdminRepository($pdo))->query_1552();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
// ... (código PHP permanece inalterado)
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 0.5rem rgba(0,0,0,.15);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin: 0 3px;
            min-width: 70px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="admin-card">
        <h3 class="text-primary mb-4">👋 Bem-vindo, <?php
            // Padronização: o login seta "nome_usuario".
            // Fallback: se o menu carregou o usuário do banco, usamos também.
            $bemVindoNome = (string)($_SESSION['nome_usuario'] ?? ($usuario['nome'] ?? 'Usuário'));
            echo htmlspecialchars($bemVindoNome, ENT_QUOTES, 'UTF-8');
        ?>!</h3>
        
        <h4 class="border-bottom pb-2 mb-4">📋 Gerenciamento de Usuários</h4>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="bg-light">
                    <tr>
                        <th>#ID</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Nível de Acesso</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td class="text-muted"><?php echo (int)$usuario['id']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['telefone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $usuario['nivel_acesso'] === 'Administrador' ? 'primary' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($usuario['nivel_acesso'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="action-buttons text-center">
                                <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm"
                                   data-toggle="tooltip" title="Editar">
                                   <i class="fas fa-edit"></i>
                                </a>
                                <form action="post_router.php" method="POST" class="d-inline">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="_action" value="usuario.delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">
                                    <button type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            data-toggle="tooltip"
                                            title="Excluir"
                                            onclick="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h4 class="border-bottom pb-2 mt-5 mb-4">➕ Adicionar Novo Usuário</h4>
        <form action="painel_admin.php" method="POST">
            <?= csrf_field(); ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nome"><i class="fas fa-user"></i> Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="telefone"><i class="fas fa-phone"></i> Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="senha"><i class="fas fa-lock"></i> Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nivel_acesso"><i class="fas fa-shield-alt"></i> Nível de Acesso</label>
                        <select class="form-control" id="nivel_acesso" name="nivel_acesso" required>
                            <option value="Gerente">Gerente</option>
                            <option value="Vendedor">Vendedor</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="add_user" class="btn btn-success btn-block">
                <i class="fas fa-user-plus"></i> Adicionar Usuário
            </button>
        </form>
    </div>
</div>

<footer class="footer bg-dark text-center text-white py-3 mt-4">
    <p class="mb-0">AlabamaCMS 1.1 &copy; <?php echo date("Y"); ?> - Todos os direitos reservados</p>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script <?php echo alabama_csp_nonce_attr(); ?>>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
</script>

</body>
</html>