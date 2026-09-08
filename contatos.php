<?php
/**
 * Gestão de contatos: listar, cadastrar manualmente, importar planilha CSV e excluir.
 *
 * Formato do CSV esperado (separador vírgula ou ponto-e-vírgula):
 *   nome,telefone
 *   Maria Silva,5581999999999
 * A primeira linha pode ser um cabeçalho (é detectada e ignorada).
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

Auth::exigirLogin();

$pdo = Database::conn();
$eu = Auth::user();

/**
 * Insere ou atualiza um contato pelo telefone. Retorna 'novo', 'atualizado' ou 'invalido'.
 */
function salvar_contato(PDO $pdo, string $nome, string $telefone, ?string $tags, int $userId): string
{
    $nome = trim($nome);
    $telefone = normalizar_telefone($telefone);
    if ($nome === '' || strlen($telefone) < 10) {
        return 'invalido';
    }
    // UPSERT pelo telefone (único).
    $stmt = $pdo->prepare(
        'INSERT INTO contatos (nome, telefone, tags, criado_por)
         VALUES (:nome, :tel, :tags, :uid)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), tags = VALUES(tags)'
    );
    $stmt->execute([
        ':nome' => $nome,
        ':tel'  => $telefone,
        ':tags' => ($tags !== null && trim($tags) !== '') ? trim($tags) : null,
        ':uid'  => $userId,
    ]);
    // rowCount: 1 = inserido, 2 = atualizado (MySQL).
    return $stmt->rowCount() === 1 ? 'novo' : 'atualizado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $r = salvar_contato($pdo, $_POST['nome'] ?? '', $_POST['telefone'] ?? '', $_POST['tags'] ?? '', (int) $eu['id']);
        if ($r === 'invalido') {
            flash('error', 'Nome e telefone válidos são obrigatórios (telefone com DDI+DDD).');
        } else {
            flash('success', $r === 'novo' ? 'Contato adicionado.' : 'Contato atualizado.');
        }

    } elseif ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM contatos WHERE id = ?')->execute([$id]);
        flash('success', 'Contato removido.');

    } elseif ($acao === 'validar') {
        // Consulta a Z-API para checar quais contatos têm WhatsApp.
        // Processa um lote por clique para não estourar o tempo de execução.
        @set_time_limit(0);
        $limite = 50;
        $pendentes = $pdo->query(
            "SELECT id, telefone FROM contatos WHERE whatsapp = 'nao_verificado' ORDER BY id ASC LIMIT {$limite}"
        )->fetchAll();

        if (empty($pendentes)) {
            flash('info', 'Todos os contatos já foram verificados.');
        } else {
            try {
                $zapi = new ZApi();
                $validos = 0; $invalidos = 0;
                $upd = $pdo->prepare('UPDATE contatos SET whatsapp = ?, verificado_em = NOW() WHERE id = ?');
                foreach ($pendentes as $p) {
                    try {
                        $existe = $zapi->phoneExists($p['telefone']);
                    } catch (Throwable $e) {
                        // Falha de conexão: interrompe e mantém como não verificado.
                        flash('error', 'Erro ao consultar a Z-API: ' . $e->getMessage());
                        break;
                    }
                    $upd->execute([$existe ? 'valido' : 'invalido', (int) $p['id']]);
                    $existe ? $validos++ : $invalidos++;
                }
                $restantes = (int) $pdo->query("SELECT COUNT(*) FROM contatos WHERE whatsapp = 'nao_verificado'")->fetchColumn();
                flash('success', "Verificados: {$validos} válido(s), {$invalidos} inválido(s). Faltam {$restantes}.");
            } catch (Throwable $e) {
                flash('error', 'Não foi possível validar: ' . $e->getMessage());
            }
        }

    } elseif ($acao === 'importar') {
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Selecione um arquivo CSV válido.');
        } else {
            $novos = 0; $atualizados = 0; $invalidos = 0;
            if (($fh = fopen($_FILES['arquivo']['tmp_name'], 'r')) !== false) {
                $primeira = true;
                while (($linha = fgetcsv($fh, 0, detectar_separador($_FILES['arquivo']['tmp_name']))) !== false) {
                    // Ignora linhas vazias
                    if ($linha === [null] || count($linha) === 0) {
                        continue;
                    }
                    $nome = $linha[0] ?? '';
                    $tel  = $linha[1] ?? '';
                    $tags = $linha[2] ?? '';

                    // Detecta e pula cabeçalho na primeira linha.
                    if ($primeira) {
                        $primeira = false;
                        if (normalizar_telefone($tel) === '' && stripos($tel, 'tel') !== false) {
                            continue;
                        }
                        if (stripos($nome, 'nome') !== false) {
                            continue;
                        }
                    }

                    $r = salvar_contato($pdo, (string) $nome, (string) $tel, (string) $tags, (int) $eu['id']);
                    if ($r === 'novo') { $novos++; }
                    elseif ($r === 'atualizado') { $atualizados++; }
                    else { $invalidos++; }
                }
                fclose($fh);
                flash('success', "Importação concluída: {$novos} novos, {$atualizados} atualizados, {$invalidos} ignorados.");
            } else {
                flash('error', 'Não foi possível ler o arquivo.');
            }
        }
    }
    redirect('contatos.php');
}

/**
 * Detecta se o CSV usa ; ou , como separador (olhando a primeira linha).
 */
function detectar_separador(string $arquivo): string
{
    $amostra = (string) file_get_contents($arquivo, false, null, 0, 2048);
    $primeiraLinha = strtok($amostra, "\n");
    return (substr_count((string) $primeiraLinha, ';') > substr_count((string) $primeiraLinha, ',')) ? ';' : ',';
}

$busca = trim($_GET['q'] ?? '');
if ($busca !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM contatos WHERE nome LIKE :q OR telefone LIKE :q ORDER BY nome LIMIT 500'
    );
    $stmt->execute([':q' => '%' . $busca . '%']);
    $contatos = $stmt->fetchAll();
} else {
    $contatos = $pdo->query('SELECT * FROM contatos ORDER BY nome LIMIT 500')->fetchAll();
}
$total = (int) $pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn();

layout_topo('Contatos', 'contatos');
?>

<div class="card">
    <h2>Adicionar contato</h2>
    <form method="post" novalidate>
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="criar">
        <div class="field">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" required>
        </div>
        <div class="field">
            <label for="telefone">Telefone (DDI + DDD + número)</label>
            <input type="text" id="telefone" name="telefone" placeholder="Ex.: 5581999999999" required>
        </div>
        <div class="field">
            <label for="tags">Tags (opcional)</label>
            <input type="text" id="tags" name="tags" placeholder="Ex.: cliente, promocao">
        </div>
        <button type="submit">Adicionar</button>
    </form>
</div>

<div class="card">
    <h2>Importar planilha (CSV)</h2>
    <p class="helptext">
        Colunas na ordem: <strong>nome, telefone</strong> (tags é opcional na 3ª coluna).
        Aceita separador vírgula ou ponto-e-vírgula. Cabeçalho é detectado automaticamente.
        No Excel, use "Salvar como &rarr; CSV".
    </p>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="importar">
        <div class="field">
            <input type="file" name="arquivo" accept=".csv,text/csv" required>
        </div>
        <button type="submit">Importar</button>
    </form>
</div>

<div class="card">
    <div class="toolbar">
        <h2 style="margin:0">Contatos (<?= $total ?>)</h2>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Verificar na Z-API quais contatos têm WhatsApp? (até 50 por vez)')">
                <?= csrf_input() ?>
                <input type="hidden" name="acao" value="validar">
                <button type="submit" class="btn">✔ Validar contatos</button>
            </form>
            <form method="get" style="display:flex;gap:0.5rem">
                <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Buscar nome ou telefone">
                <button type="submit" class="btn btn-secundario">Buscar</button>
            </form>
        </div>
    </div>
    <?php
        $naoVerificados = (int) $pdo->query("SELECT COUNT(*) FROM contatos WHERE whatsapp = 'nao_verificado'")->fetchColumn();
        if ($naoVerificados > 0):
    ?>
        <p class="helptext"><?= $naoVerificados ?> contato(s) ainda não verificado(s). Clique em "Validar contatos" para checar quais têm WhatsApp.</p>
    <?php endif; ?>

    <?php if (empty($contatos)): ?>
        <p class="helptext">Nenhum contato encontrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Nome</th><th>Telefone</th><th>WhatsApp</th><th>Tags</th><th></th></tr>
            </thead>
            <tbody>
                <?php
                $rotuloWpp = [
                    'valido'         => ['✅ Tem WhatsApp', 'badge-enviado'],
                    'invalido'       => ['❌ Sem WhatsApp', 'badge-falha'],
                    'nao_verificado' => ['— não verificado', 'badge-pendente'],
                ];
                foreach ($contatos as $c):
                    [$txtWpp, $classeWpp] = $rotuloWpp[$c['whatsapp']] ?? $rotuloWpp['nao_verificado'];
                ?>
                    <tr>
                        <td><?= e($c['nome']) ?></td>
                        <td><?= e($c['telefone']) ?></td>
                        <td><span class="badge <?= $classeWpp ?>"><?= $txtWpp ?></span></td>
                        <td><?= e($c['tags'] ?? '') ?></td>
                        <td>
                            <a class="btn btn-secundario" style="padding:0.3rem 0.6rem;font-size:0.85rem"
                               href="enviar.php?contato=<?= (int) $c['id'] ?>">Enviar</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Remover este contato?')">
                                <?= csrf_input() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-perigo" style="padding:0.3rem 0.6rem;font-size:0.85rem">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php layout_rodape(); ?>
