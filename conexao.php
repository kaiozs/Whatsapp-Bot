<?php
/**
 * Conexão com o WhatsApp via QR Code.
 * Mostra o status e, quando desconectado, exibe o QR Code para parear.
 * A página recarrega sozinha a cada 12s enquanto não estiver conectado.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

Auth::exigirLogin();

$conectado = null;
$qr = null;
$erro = '';

try {
    $zapi = new ZApi();
    $conectado = $zapi->isConnected();
    if (!$conectado) {
        $qr = $zapi->qrCodeImage(); // data URI ou null
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

layout_topo('Conexão WhatsApp', 'conexao');

if (!$conectado && !$erro) {
    // Recarrega automaticamente enquanto tenta conectar.
    echo '<meta http-equiv="refresh" content="12">';
}
?>

<div class="card">
    <h2>Status</h2>

    <?php if ($erro): ?>
        <div class="status-linha"><span class="dot dot-off"></span> Erro ao consultar</div>
        <div class="alert alert-error"><?= e($erro) ?></div>
        <p class="helptext">Confira as credenciais da Z-API em <code>config/config.php</code>.</p>

    <?php elseif ($conectado): ?>
        <div class="status-linha"><span class="dot dot-on"></span> Conectado</div>
        <p class="helptext">Tudo certo! Seu WhatsApp está pareado e pronto para enviar mensagens.</p>
        <a class="btn" href="enviar.php">Enviar uma mensagem</a>

    <?php else: ?>
        <div class="status-linha"><span class="dot dot-off"></span> Desconectado</div>
        <p class="helptext">
            Abra o WhatsApp no celular &rarr; <strong>Aparelhos conectados</strong> &rarr;
            <strong>Conectar um aparelho</strong> e aponte para o QR Code abaixo.
        </p>

        <?php if ($qr): ?>
            <div class="qr-box">
                <img src="<?= e($qr) ?>" alt="QR Code para conectar o WhatsApp">
            </div>
            <p class="helptext" style="text-align:center">
                A página atualiza sozinha. Assim que conectar, o status muda para "Conectado".
            </p>
        <?php else: ?>
            <div class="alert alert-warning">
                Não foi possível obter o QR Code agora. Aguarde alguns segundos e recarregue a página.
            </div>
        <?php endif; ?>

        <a class="btn btn-secundario" href="conexao.php">Atualizar agora</a>
    <?php endif; ?>
</div>

<?php layout_rodape(); ?>
