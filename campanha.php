<?php
/**
 * Disparo em lote.
 * 1) Enfileira mensagens para os contatos selecionados (status = pendente).
 * 2) Botão "Disparar agora" processa um lote da fila com intervalo de segurança.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

// O processamento de um lote pode demorar (intervalos entre envios).
@set_time_limit(0);

Auth::exigirLogin();

$pdo = Database::conn();
$eu = Auth::user();

$loteTamanho = (int) cfg('app.lote_por_clique', 20);
$intMin = (int) cfg('app.intervalo_min', 8);
$intMax = (int) cfg('app.intervalo_max', 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'enfileirar') {
        $texto = $_POST['texto'] ?? '';
        $selecao = $_POST['contatos'] ?? [];
        $todos = ($_POST['todos'] ?? '') === '1';

        if ($todos) {
            $selecao = array_map('intval', $pdo->query('SELECT id FROM contatos')->fetchAll(PDO::FETCH_COLUMN));
        }

        if (trim($texto) === '') {
            flash('error', 'Escreva a mensagem antes de enfileirar.');
        } elseif (empty($selecao)) {
            flash('error', 'Selecione ao menos um contato (ou marque "todos").');
        } else {
            $n = Disparo::enfileirar($selecao, $texto, (int) $eu['id']);
            flash('success', "{$n} mensagem(ns) adicionada(s) à fila. Clique em \"Disparar agora\" para enviar.");
        }
    } elseif ($acao === 'disparar') {
        $r = Disparo::processar($loteTamanho, $intMin, $intMax);
        flash('success', "Disparo concluído: {$r['enviados']} enviada(s), {$r['falhas']} falha(s). Restam {$r['restantes']} na fila.");
    } elseif ($acao === 'limpar') {
        $pdo->exec('DELETE FROM mensagens WHERE status = "pendente"');
        flash('success', 'Fila de pendentes limpa.');
    }
    redirect('campanha.php');
}

$contatos = $pdo->query('SELECT id, nome, telefone FROM contatos ORDER BY nome LIMIT 500')->fetchAll();
$pendentes = Disparo::restantes();

layout_topo('Disparo em lote', 'campanha');
?>

<?php if ($pendentes > 0): ?>
<div class="card">
    <h2>Fila de envio</h2>
    <p><strong><?= $pendentes ?></strong> mensagem(ns) aguardando envio.</p>
    <p class="helptext">
        Cada clique envia até <strong><?= $loteTamanho ?></strong> mensagens, com intervalo de
        <?= $intMin ?>&ndash;<?= $intMax ?>s entre elas (segurança contra bloqueio).
        Se houver mais que isso, clique novamente até zerar a fila.
    </p>
    <div class="acoes">
        <form method="post" onsubmit="return confirm('Iniciar o disparo agora? Não feche a página até terminar.')">
            <?= csrf_input() ?>
            <input type="hidden" name="acao" value="disparar">
            <button type="submit">🚀 Disparar agora (até <?= $loteTamanho ?>)</button>
        </form>
        <form method="post" onsubmit="return confirm('Remover todas as mensagens pendentes da fila?')">
            <?= csrf_input() ?>
            <input type="hidden" name="acao" value="limpar">
            <button type="submit" class="btn btn-perigo">Limpar fila</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>Nova campanha</h2>
    <?php if (empty($contatos)): ?>
        <p class="helptext">Você ainda não tem contatos. <a href="contatos.php">Cadastre ou importe</a> primeiro.</p>
    <?php else: ?>
    <form method="post" novalidate>
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="enfileirar">
        <div class="field">
            <label for="texto">Mensagem</label>
            <textarea id="texto" name="texto" rows="4" required placeholder="Olá {nome}, temos uma novidade..."></textarea>
            <span class="helptext">Use <strong>{nome}</strong> para personalizar com o nome de cada contato.</span>
        </div>

        <div class="field">
            <label>
                <input type="checkbox" id="marcarTodos"> Selecionar todos os contatos abaixo
            </label>
            <label style="font-weight:400;margin-top:0.5rem">
                <input type="checkbox" name="todos" value="1"> Enviar para TODOS os contatos (inclusive além dos 500 listados)
            </label>
        </div>

        <div class="field" style="max-height:280px;overflow:auto;border:1px solid var(--borda);border-radius:8px;padding:0.5rem">
            <?php foreach ($contatos as $c): ?>
                <label style="display:block;font-weight:400;padding:0.2rem 0">
                    <input type="checkbox" class="chkContato" name="contatos[]" value="<?= (int) $c['id'] ?>">
                    <?= e($c['nome']) ?> &mdash; <?= e($c['telefone']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit">Adicionar à fila</button>
    </form>
    <?php endif; ?>
</div>

<script>
    // "Selecionar todos" marca/desmarca a lista visível.
    document.getElementById('marcarTodos')?.addEventListener('change', function () {
        document.querySelectorAll('.chkContato').forEach(cb => cb.checked = this.checked);
    });
</script>

<?php layout_rodape(); ?>
