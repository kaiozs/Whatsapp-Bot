<?php
/**
 * Tela de login.
 */

require __DIR__ . '/../src/bootstrap.php';

// Já logado? vai pro dashboard.
if (Auth::check()) {
    redirect('dashboard.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (Auth::login($email, $senha)) {
        redirect('dashboard.php');
    }
    $erro = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - <?= e(cfg('app.nome', 'Z-API CRM')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="tela-login">
    <div class="card-login">
        <h1>📲 <?= e(cfg('app.nome', 'Z-API CRM')) ?></h1>
        <p class="subtitle">Acesse o painel</p>

        <?php if ($erro): ?>
            <div class="alert alert-error"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_input() ?>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="field">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
