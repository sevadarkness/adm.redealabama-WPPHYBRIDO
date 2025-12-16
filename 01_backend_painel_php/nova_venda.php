<?php
// Verificando e iniciando a sessão
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Validando a sessão do usuário
if (!isset($_SESSION['mensagem'])) $_SESSION['mensagem'] = '';
if (!isset($_SESSION['tipo_mensagem'])) $_SESSION['tipo_mensagem'] = '';
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Configurando erro para PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Inicializa variáveis ANTES do try/catch para evitar undefined em caso de erro
$produtos_grouped = [];
$acesso_restrito = true; // Default seguro

try {
    // Obtendo os dados do usuário
    $usuario_id = $_SESSION['usuario_id'];
    $stmt_usuario = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_693();
    $stmt_usuario->execute([$usuario_id]);
    $usuario = $stmt_usuario->fetch();
    $acesso_restrito = !in_array($usuario['nivel_acesso'], ['Vendedor', 'Gerente', 'Administrador']);

    // Obtendo os produtos disponíveis no estoque
    $stmt_produtos = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_1014();
    $stmt_produtos->execute([$usuario_id]);
    $produtos = $stmt_produtos->fetchAll();

    // Organizando os produtos em um array
    foreach ($produtos as $produto) {
        $produtos_grouped[$produto['id']]['nome'] = $produto['nome'];
        $produtos_grouped[$produto['id']]['preco'] = $produto['preco'];
        $produtos_grouped[$produto['id']]['sabores'][] = [
            'sabor_id' => $produto['sabor_id'],
            'sabor' => $produto['sabor'],
            'quantidade' => $produto['quantidade']
        ];
    }

} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro no sistema: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "danger";
}

// Lógica de envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_venda'])) {
    // Validação CSRF
    require_once __DIR__ . '/csrf.php';
    if (!csrf_validate()) {
        $_SESSION['mensagem'] = 'Sessão expirada. Recarregue a página.';
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: nova_venda.php');
        exit;
    }

    try {
        // Verificando se o produto e sabor foram selecionados
        if (empty($_POST['produto_id']) || empty($_POST['sabor_id'])) {
            throw new Exception("Selecione um produto e um sabor!");
        }

        $produto_id = $_POST['produto_id'];
        $sabor_id = $_POST['sabor_id'];
        $cliente_nome = $_POST['cliente_nome'] ?? '';
        $cliente_telefone = $_POST['cliente_telefone'] ?? '';
        
        // Validando os dados do cliente
        if (empty($cliente_nome) || empty($cliente_telefone)) {
            throw new Exception("Preencha todos os campos do cliente!");
        }

        // Status e motivo são opcionais
        $status = $_POST['produto_status'] ?? 'normal';
        $produto_avariado = (in_array($status, ['avariado', 'perdido'])) ? 1 : 0;
        $motivo_avariado = ($produto_avariado) ? ($_POST['motivo_avariado'] ?? '') : null;
        
        $preco_produto = $_POST['preco_produto'];
        $valor_total = $produto_avariado ? 0 : $preco_produto; // Para status "normal", o preço será o valor original

        // Validações específicas para produtos danificados ou perdidos
        if ($produto_avariado) {
            if (empty($motivo_avariado)) {
                throw new Exception("Informe o motivo da baixa!");
            }
            if ($status === 'avariado' && empty($_FILES['evidencia']['name'])) {
                throw new Exception("Foto obrigatória para produto avariado!");
            }
        }

        // Verificando estoque disponível
        $stmt_estoque = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_3694();
        $stmt_estoque->execute([$produto_id, $sabor_id, $usuario_id]);
        $quantidade_estoque = $stmt_estoque->fetchColumn();
        if ($quantidade_estoque < 1) {
            throw new Exception("Estoque insuficiente para este sabor!");
        }

        // Inicia transação para garantir atomicidade das operações de venda
        $pdo->beginTransaction();

        // Registrando a venda
        $stmt_venda = (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_4120();
        $stmt_venda->execute([ 
            $produto_id, 
            $sabor_id, 
            $cliente_nome, 
            $cliente_telefone, 
            $valor_total, 
            $usuario_id, 
            $produto_avariado, 
            $motivo_avariado
        ]);

        // Atualizando o estoque
        (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_4649()
            ->execute([$produto_id, $sabor_id, $usuario_id]);

        // Registrando prejuízo, se necessário
        if ($produto_avariado) {
            (new \RedeAlabama\Repositories\Screens\NovaVendaRepository($pdo))->prepare_4937()->execute([$produto_id, $sabor_id, $usuario_id, $motivo_avariado, $preco_produto]);
        }

        // Confirma transação
        $pdo->commit();

        $_SESSION['mensagem'] = $produto_avariado 
            ? "Baixa registrada! Gerência notificada." 
            : "Venda concluída com sucesso!";
        $_SESSION['tipo_mensagem'] = $produto_avariado ? "warning" : "success";

        // Redirecionando para nova venda
        header("Location: nova_venda.php");
        exit;

    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['mensagem'] = "Erro: " . $e->getMessage();
        $_SESSION['tipo_mensagem'] = "danger";
        header("Location: nova_venda.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Venda - Alabama CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
    <style>
        /* Override já aplica estilos premium de cards e forms */
        /* Mantém apenas customizações específicas da nova venda */
        .form-control-custom:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
        }

        .form-check-label {
            margin-left: 8px;
            color: #444;
        }

        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            z-index: 1000;
            border-radius: 8px;
        }

        .sabor-item {
            padding: 10px;
            margin: 4px 0;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.2s;
        }

        .sabor-item:hover {
            background: #f1f3f5;
            transform: translateX(4px);
        }

        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }

        .status-toggle {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
        }

        .btn-modern {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .modern-card {
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="modern-card card p-4">
            <h2 class="mb-4 text-center text-primary">
                <i class="fas fa-cash-register me-2"></i>Nova Venda
            </h2>

            <?php if (!empty($_SESSION['mensagem'])): ?>
                <div class="floating-alert alert alert-<?= $_SESSION['tipo_mensagem'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['mensagem'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']); ?>
            <?php endif; ?>

            <?php if (!$acesso_restrito): ?>
                <form method="POST" id="formVenda" enctype="multipart/form-data">
                    <?php require_once __DIR__ . '/csrf.php'; echo csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Selecione o Produto</label>
                        <select class="form-select form-control-custom" name="produto_id" id="produtoSelect" required>
                            <option value="">Escolha um produto...</option>
                            <?php foreach ($produtos_grouped as $id => $produto): ?>
                                <option value="<?= $id ?>" data-preco="<?= $produto['preco'] ?>">
                                    <?= $produto['nome'] ?> - R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Sabores Disponíveis</label>
                        <div id="saboresList" class="p-3 bg-light rounded-2">
                            <div class="text-muted small">Selecione um produto primeiro</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nome do Cliente</label>
                            <input type="text" name="cliente_nome" id="clienteNome" 
                                   class="form-control form-control-custom" 
                                   placeholder="Digite o nome completo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Telefone</label>
                            <input type="text" name="cliente_telefone" id="clienteTelefone" 
                                   class="form-control form-control-custom" 
                                   placeholder="(00) 00000-0000" 
                                   data-inputmask="'mask': '(99) 99999-9999'" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Status do Produto</label>
                        <div class="status-toggle">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="checkNormal" name="produto_status" value="normal" checked>
                                <label class="form-check-label text-success" for="checkNormal">
                                    <i class="fas fa-check-circle me-2"></i>Venda Normal
                                </label>
                            </div>
                        </div>
                        <div class="status-toggle">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="checkPerdido" name="produto_status" value="perdido">
                                <label class="form-check-label text-danger" for="checkPerdido">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Produto Perdido
                                </label>
                            </div>
                        </div>
                        <div class="status-toggle">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="checkAvariado" name="produto_status" value="avariado">
                                <label class="form-check-label text-warning" for="checkAvariado">
                                    <i class="fas fa-times-circle me-2"></i>Produto Avariado
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4" id="motivoContainer" style="display: none;">
                        <label class="form-label fw-bold">Motivo da Baixa</label>
                        <textarea name="motivo_avariado" class="form-control form-control-custom" 
                                  rows="2" placeholder="Descreva o motivo..."></textarea>
                    </div>

                    <div class="mb-4" id="evidenciaContainer" style="display: none;">
                        <div class="file-upload">
                            <label class="form-label fw-bold d-block mb-3">
                                <i class="fas fa-camera me-2"></i>Enviar Evidência
                            </label>
                            <input type="file" name="evidencia" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <input type="hidden" name="preco_produto" id="precoProduto">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="confirmar_venda" 
                                class="btn btn-primary btn-modern">
                            <i class="fas fa-check-circle me-2"></i>Confirmar Venda
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-modern" 
                                onclick="limparCampos()">
                            <i class="fas fa-eraser me-2"></i>Limpar Campos
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger text-center py-3">
                    <i class="fas fa-ban me-2"></i>Acesso não autorizado!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script <?php echo alabama_csp_nonce_attr(); ?>>
        function limparCampos() {
            $('#formVenda')[0].reset();
            $('#saboresList').html('');
            $('#motivoContainer').hide();
            $('#evidenciaContainer').hide();
            $('textarea[name="motivo_avariado"]').prop('required', false);
            $('#evidenciaContainer input').prop('required', false);
            $('input[name="produto_status"][value="normal"]').prop('checked', true); // Forçar status "normal"
        }

        $(document).ready(function() {
            $('[name="cliente_telefone"]').inputmask();
            
            $('#produtoSelect').change(function() {
                const produtoId = $(this).val();
                const produtos = <?= json_encode($produtos_grouped) ?>;
                const sabores = produtos[produtoId]?.sabores || [];
                let html = '';
                sabores.forEach(sabor => {
                    html += `
                        <div class="form-check sabor-item">
                            <input class="form-check-input" type="radio" name="sabor_id" value="${sabor.sabor_id}" required>
                            <label class="form-check-label">${sabor.sabor} 
                                <span class="badge bg-primary ms-2">${sabor.quantidade} disponíveis</span>
                            </label>
                        </div>
                    `;
                });
                $('#saboresList').html(html || '<div class="text-danger">Nenhum sabor disponível</div>');
                $('#precoProduto').val($(this).find(':selected').data('preco'));
            });
            
            $('input[name="produto_status"]').change(function() {
                const status = $(this).val();

                // Resetar todos os campos obrigatórios e ocultar os containers
                $('textarea[name="motivo_avariado"]').prop('required', false);
                $('#evidenciaContainer input').prop('required', false);
                $('#motivoContainer').hide();
                $('#evidenciaContainer').hide();

                // Configurar campos obrigatórios e exibir containers conforme o status
                if (status === "avariado") {
                    $('#motivoContainer').slideDown();
                    $('#evidenciaContainer').slideDown();
                    $('textarea[name="motivo_avariado"]').prop('required', true);
                    $('#evidenciaContainer input').prop('required', true);
                } else if (status === "perdido") {
                    $('#motivoContainer').slideDown();
                    $('textarea[name="motivo_avariado"]').prop('required', true);
                }
            });
        });
		
		$(document).ready(function() {
    // Bloqueando os campos de nome e telefone caso o status seja "avariado" ou "perdido"
    $('input[name="produto_status"]').change(function() {
        const status = $(this).val();

        // Alterando os valores do nome e telefone conforme o status
        if (status === 'avariado' || status === 'perdido') {
            $('#clienteNome').val('Perdido/Extraviado').prop('readonly', true);
            $('#clienteTelefone').val('00000000000').prop('readonly', true);
        } else {
            $('#clienteNome').prop('readonly', false);
            $('#clienteTelefone').prop('readonly', false);
        }
    });

    // Definindo o comportamento inicial dos campos com base no status preexistente
    const initialStatus = $('input[name="produto_status"]:checked').val();
    if (initialStatus === 'avariado' || initialStatus === 'perdido') {
        $('#clienteNome').val('Perdido/Extraviado').prop('readonly', true);
        $('#clienteTelefone').val('00000000000').prop('readonly', true);
    }
});
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>