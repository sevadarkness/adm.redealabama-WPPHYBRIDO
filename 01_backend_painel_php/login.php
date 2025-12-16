<?php
declare(strict_types=1);

use RedeAlabama\Repositories\UsuarioRepository;
use RedeAlabama\Services\Auth\LoginService;


require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limiter.php';
require_once __DIR__ . '/app/Repositories/BaseRepository.php';
require_once __DIR__ . '/app/Repositories/UsuarioRepository.php';
require_once __DIR__ . '/app/Services/Auth/LoginService.php';


$erro = $erro ?? '';
$telefone_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
        log_app_event('security', 'csrf_login_invalid', [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } else {
        // Rate limiting por IP
        $rateBucket = 'login_ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!rate_limit_or_fail($rateBucket, 10, 300)) { // 10 tentativas em 5 minutos
            $erro = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
            log_app_event('security', 'login_rate_limited', [
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } else {
            $telefone = trim($_POST['telefone'] ?? '');
            $senha    = (string)($_POST['senha'] ?? '');
            $telefone_input = $telefone;
            $lembrar = isset($_POST['lembrar']);

            $repo    = new UsuarioRepository($pdo);
            $service = new LoginService($repo);
            $result  = $service->authenticate($telefone, $senha, $lembrar);

            if ($result['ok'] === true && is_array($result['user'])) {
                $user = $result['user'];

                // Proteção contra fixation: novo ID após login
                session_regenerate_id(true);

                $_SESSION['usuario_id']   = $user['id'] ?? null;
                $_SESSION['nivel_acesso'] = $user['nivel_acesso'] ?? null;
                $_SESSION['nome_usuario'] = $user['nome'] ?? null;

                // Lembrar telefone?
                $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

                if ($lembrar && $telefone !== '') {
                    setcookie('remember_phone', $telefone, [
                        'expires'  => time() + (86400 * 30), // 30 dias
                        'path'     => '/',
                        'secure'   => $secureCookie,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    if (!empty($_COOKIE['remember_phone'])) {
                        setcookie('remember_phone', '', [
                            'expires'  => time() - 3600,
                            'path'     => '/',
                            'secure'   => $secureCookie,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                }

                // Redireciona conforme o nível de acesso
                $nivel = (string)($user['nivel_acesso'] ?? '');
                if ($nivel === 'Administrador') {
                    header('Location: painel_admin.php');
                } elseif ($nivel === 'Gerente') {
                    header('Location: painel_gerente.php');
                } else {
                    header('Location: painel_vendedor_hoje.php');
                }
                exit;
            } else {
                $erro = $result['error'] ?? 'Número de telefone ou senha incorretos!';
                log_app_event('auth', 'login_falha', [
                    'telefone' => $telefone,
                    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
        }
    }
} else {
    if (!empty($_COOKIE['remember_phone'])) {
        $telefone_input = (string)$_COOKIE['remember_phone'];
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlabamaCMS 3.0 - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --background: #0a0a0a;
            --surface: #1a1a1a;
            --text-primary: #f8f9fa;
            --text-secondary: #9e9e9e;
        }

        body {
            background: var(--background);
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            font-family: 'Inter', sans-serif;
            padding: 10px;
        }

        .login-card {
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            padding: 1.5rem; /* Aumentado o padding */
            width: 100%;
            max-width: 400px; /* Aumentado o max-width */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-control {
            background: #2a2a2a;
            border: 1px solid #333;
            color: var(--text-primary);
            padding: 0.6rem 1rem; /* Aumentado o padding */
            font-size: 1rem; /* Aumentado o font-size */
            height: 44px; /* Aumentado o height */
            line-height: 1.2;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }

        .input-group-text {
            background: #333;
            border: 1px solid #404040;
            padding: 0 0.8rem; /* Aumentado o padding */
            height: 44px; /* Aumentado o height */
            min-width: 44px; /* Aumentado o min-width */
            cursor: pointer;
        }

        .btn-primary {
            padding: 0.6rem 1rem; /* Aumentado o padding */
            font-size: 1rem; /* Aumentado o font-size */
            height: 44px; /* Aumentado o height */
            background: var(--primary);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #059669;
        }

        .footer {
            font-size: 0.8rem; /* Aumentado o font-size */
            margin-top: 1rem; /* Aumentado o margin-top */
            padding-top: 1rem; /* Aumentado o padding-top */
            border-top: 1px solid #333;
            color: var(--text-secondary);
        }

        .logo-vape {
            color: var(--primary);
            font-size: 2rem; /* Aumentado o font-size */
            margin-bottom: 0.6rem; /* Aumentado o margin-bottom */
        }

        .text-secondary {
            font-size: 0.9rem; /* Aumentado o font-size */
        }

        .alert {
            font-size: 0.9rem; /* Aumentado o font-size */
        }

        .form-check-label {
            font-size: 0.9rem; /* Aumentado o font-size */
        }

        .form-check-input {
            transform: scale(1); /* Aumentado o tamanho do checkbox */
        }
    </style>
</head>
<body class="alabama-theme">
    <div class="login-card">
        <div class="text-center mb-3">
            <i class="fas fa-smoking logo-vape"></i>
            <h2 class="h4 text-white mb-0">AlabamaCMS 3.0</h2>
            <p class="text-secondary mt-1">Sistema de Gerenciamento</p>
        </div>

        <?php if (!empty($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-2 p-2" role="alert">
            <div><?= $erro ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: invert(1); transform: scale(0.8);"></button>
        </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <?= csrf_field(); ?>
            <!-- Campo Telefone -->
            <div class="mb-3">
                <label class="text-secondary d-block">Telefone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                    <input type="tel" class="form-control" id="telefone" name="telefone" 
                           pattern="\d{11}" placeholder="32999999999" required value="<?= htmlspecialchars($telefone_input, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <small class="text-secondary">Exemplo: 32999999999</small>
            </div>

            <!-- Campo Senha -->
            <div class="mb-3">
                <label class="text-secondary d-block">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="senha" name="senha" placeholder="••••••••" required>
                    <span class="input-group-text password-toggle">
                        <i class="fas fa-eye-slash"></i>
                    </span>
                </div>
            </div>

            <div class="d-flex justify-content-start mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="lembrar" name="lembrar">
                    <label class="form-check-label text-secondary" for="lembrar">Lembrar</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-2">
                <i class="fas fa-sign-in-alt me-1"></i>Acessar
            </button>

            <div class="footer">
                © 2024 AlabamaCMS 3.0<br>
                Todos os direitos reservados
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script <?php echo alabama_csp_nonce_attr(); ?>>
        // Toggle Password Visibility
        document.querySelector('.password-toggle').addEventListener('click', function() {
            const password = document.getElementById('senha');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                password.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        });

        // Phone Number Validation
        document.getElementById('telefone').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });

        // Form Validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>