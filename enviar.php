<?php
/**
 * Envio individual de mensagem.
 * Aceita ?contato=ID para pré-preencher a partir de um contato salvo.
 * Suporta a variável {nome} no texto.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

Auth::exigirLogin();

$pdo = Database::conn();
$eu = Auth::user();

$prefNome = '';
$prefTelefone = '';

// Pré-preenchimento a partir de um contato.
$contatoId = (int) ($_GET['contato'] ?? 0);
if ($contatoId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM contatos WHERE id = ?');
    $stmt->execute([$contatoId]);
    if ($c = $stmt->fetch()) {
        $prefNome = $c['nome'];
        $prefTelefone = $c['telefone'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome = trim($_POST['nome'] ?? '');
    $telefone = normalizar_telefone($_POST['telefone'] ?? '');
    $texto = trim($_POST['texto'] ?? '');

    // Substitui a variável {nome} pelo nome informado.
    $textoFinal = str_replace('{nome}', $nome, $texto);

    if (strlen($telefone) < 10 || $texto === '') {
        flash('error', 'Informe um telefone válido e a mensagem.');
        redirect('enviar.php');
    }

    // Localiza contato existente (para vincular na tabela mensagens).
    $stmt = $pdo->prepare('SELECT id FROM contatos WHERE telefone = ?');
    $stmt->execute([$telefone]);
    $cid = $stmt->fetchColumn();
    $cid = $cid !== false ? (int) $cid : null;

    // Registra e tenta enviar.
    $status = 'enviado';
    $erro = null;
    $zaapId = null;
    $messageId = null;

    try {
        $zapi = new ZApi();

        // Verifica se o número tem WhatsApp antes de enviar.
        if (!$zapi->phoneExists($telefone)) {
            throw new ZApiException('Este número não possui uma conta de WhatsApp.');
        }

        // Atualiza a validação do contato, se ele existir na base.
        if ($cid !== null) {
            $pdo->prepare('UPDATE contatos SET whatsapp = "valido", verificado_em = NOW() WHERE id = ?')
                ->execute([$cid]);
        }

        $resp = $zapi->sendText($telefone, $textoFinal);
        $zaapId = $resp['zaapId'] ?? null;
        $messageId = $resp['messageId'] ?? ($resp['id'] ?? null);
    } catch (ZApiException $e) {
        $status = 'falha';
        $erro = $e->getMessage();
        // Se o número não existe, marca o contato como inválido.
        if ($cid !== null && stripos($erro, 'não possui') !== false) {
            $pdo->prepare('UPDATE contatos SET whatsapp = "invalido", verificado_em = NOW() WHERE id = ?')
                ->execute([$cid]);
        }
    } catch (Throwable $e) {
        $status = 'falha';
        $erro = $e->getMessage();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO mensagens
            (contato_id, telefone, nome, texto, status, erro, zaap_id, message_id, criado_por, enviado_em)
         VALUES
            (:cid, :tel, :nome, :texto, :status, :erro, :zaap, :msg, :uid,
             CASE WHEN :status2 = "enviado" THEN NOW() ELSE NULL END)'
    );
    $stmt->execute([
        ':cid'    => $cid,
        ':tel'    => $telefone,
        ':nome'   => ($nome !== '' ? $nome : null),
        ':texto'  => $textoFinal,
        ':status' => $status,
        ':erro'   => $erro,
        ':zaap'   => $zaapId,
        ':msg'    => $messageId,
        ':uid'    => (int) $eu['id'],
        ':status2' => $status,
    ]);

    if ($status === 'enviado') {
        flash('success', "Mensagem enviada para {$telefone}.");
    } else {
        flash('error', "Não foi possível enviar: {$erro}");
    }
    redirect('enviar.php');
}

layout_topo('Envio individual', 'enviar');
?>

<div class="card">
    <h2>Enviar mensagem</h2>
    <form method="post" novalidate>
        <?= csrf_input() ?>
        <div class="field">
            <label for="nome">Nome (opcional)</label>
            <input type="text" id="nome" name="nome" value="<?= e($prefNome) ?>" placeholder="Usado para a variável {nome}">
        </div>
        <div class="field">
            <label for="telefone">Telefone (DDI + DDD + número)</label>
            <input type="text" id="telefone" name="telefone" value="<?= e($prefTelefone) ?>" placeholder="Ex.: 5581999999999" required autofocus>
        </div>
        <div class="field">
            <label for="texto">Mensagem</label>
            <textarea id="texto" name="texto" rows="5" required placeholder="Olá {nome}, tudo bem?"></textarea>
            <span class="helptext">Dica: use <strong>{nome}</strong> no texto para inserir o nome automaticamente.</span>
        </div>
        <button type="submit">Enviar mensagem</button>
    </form>
</div>

<?php layout_rodape(); ?>
