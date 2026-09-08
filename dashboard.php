<?php
/**
 * Dashboard principal com métricas resumidas.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

Auth::exigirLogin();

$pdo = Database::conn();

$totalContatos = (int) $pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn();
$totalEnviados = (int) $pdo->query("SELECT COUNT(*) FROM mensagens WHERE status = 'enviado'")->fetchColumn();
$totalPendentes = (int) $pdo->query("SELECT COUNT(*) FROM mensagens WHERE status = 'pendente'")->fetchColumn();
$totalFalhas = (int) $pdo->query("SELECT COUNT(*) FROM mensagens WHERE status = 'falha'")->fetchColumn();

// Status da conexão com o WhatsApp (não quebra a página se a Z-API falhar).
$conectado = null; // null = desconhecido
try {
    $zapi = new ZApi();
    $conectado = $zapi->isConnected();
} catch (Throwable $e) {
    $conectado = null;
}

// Últimas mensagens
$ultimas = $pdo->query(
    'SELECT nome, telefone, status, criado_em FROM mensagens ORDER BY id DESC LIMIT 8'
)->fetchAll();

layout_topo('Dashboard', 'dashboard');
?>

<div class="grid-cards">
    <div class="metric">
        <div class="valor"><?= $totalContatos ?></div>
        <div class="rotulo">Contatos</div>
    </div>
    <div class="metric">
        <div class="valor" style="color:#166534"><?= $totalEnviados ?></div>
        <div class="rotulo">Mensagens enviadas</div>
    </div>
    <div class="metric">
        <div class="valor" style="color:#92400e"><?= $totalPendentes ?></div>
        <div class="rotulo">Na fila (pendentes)</div>
    </div>
    <div class="metric">
        <div class="valor" style="color:#991b1b"><?= $totalFalhas ?></div>
        <div class="rotulo">Falhas</div>
    </div>
</div>

<div class="card">
    <h2>Conexão com o WhatsApp</h2>
    <?php if ($conectado === true): ?>
        <div class="status-linha"><span class="dot dot-on"></span> Conectado</div>
        <p class="helptext">Seu WhatsApp está pareado e pronto para enviar mensagens.</p>
    <?php elseif ($conectado === false): ?>
        <div class="status-linha"><span class="dot dot-off"></span> Desconectado</div>
        <a class="btn" href="conexao.php">Conectar via QR Code</a>
    <?php else: ?>
        <div class="status-linha"><span class="dot dot-off"></span> Não foi possível verificar</div>
        <p class="helptext">Verifique as credenciais da Z-API. <a href="conexao.php">Ver detalhes</a></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Últimas mensagens</h2>
    <?php if (empty($ultimas)): ?>
        <p class="helptext">Nenhuma mensagem enviada ainda.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Nome</th><th>Telefone</th><th>Status</th><th>Quando</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ultimas as $m): ?>
                    <tr>
                        <td><?= e($m['nome'] ?? '-') ?></td>
                        <td><?= e($m['telefone']) ?></td>
                        <td><span class="badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td>
                        <td><?= e($m['criado_em']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php layout_rodape(); ?>
