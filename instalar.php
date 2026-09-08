<?php
/**
 * Instalador: cria o usuário admin inicial com senha segura.
 * Execute UMA vez após importar o schema.sql e configurar o banco.
 * Depois de criar o admin, APAGUE este arquivo do servidor.
 */

require __DIR__ . '/../src/bootstrap.php';

$pdo = Database::conn();

// Já existe algum usuário? Então bloqueia para não recriar sem querer.
$total = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

$mensagem = '';
$criado = false;

if ($total > 0) {
    $mensagem = 'O sistema já possui usuários cadastrados. Por segurança, apague este arquivo (instalar.php) do servidor.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || strlen($senha) < 6) {
        $mensagem = 'Preencha nome, e-mail e uma senha de pelo menos 6 caracteres.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo)
             VALUES (?, ?, ?, "admin", 1)'
        );
        $stmt->execute([$nome, $email, Auth::hash($senha)]);
        $criado = true;
        $mensagem = 'Administrador criado com sucesso! Agora APAGUE o arquivo instalar.php e faça login.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar - <?= e(cfg('app.nome', 'Z-API CRM')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="tela-login">
    <div class="card-login">
        <h1>⚙️ Instalação</h1>
        <p class="subtitle">Criação do administrador inicial</p>

        <?php if ($mensagem): ?>
            <div class="alert <?= $criado ? 'alert-success' : 'alert-error' ?>"><?= e($mensagem) ?></div>
        <?php endif; ?>

        <?php if (!$criado && $total === 0): ?>
        <form method="post" novalidate>
            <?= csrf_input() ?>
            <div class="field">
                <label for="nome">Seu nome</label>
                <input type="text" id="nome" name="nome" required autofocus>
            </div>
            <div class="field">
                <label for="email">E-mail (login)</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="field">
                <label for="senha">Senha (mín. 6 caracteres)</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit">Criar administrador</button>
        </form>
        <?php else: ?>
            <a class="btn-link" href="index.php">Ir para o login</a>
        <?php endif; ?>
    </div>
</body>
</html>
