<?php
/**
 * Gestão de usuários (somente admin).
 * Listar, criar (admin/vendedor) e ativar/desativar.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

Auth::exigirAdmin();

$pdo = Database::conn();
$eu = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $perfil = ($_POST['perfil'] ?? 'vendedor') === 'admin' ? 'admin' : 'vendedor';

        if ($nome === '' || $email === '' || strlen($senha) < 6) {
            flash('error', 'Preencha nome, e-mail e senha (mín. 6 caracteres).');
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo)
                     VALUES (?, ?, ?, ?, 1)'
                );
                $stmt->execute([$nome, $email, Auth::hash($senha), $perfil]);
                flash('success', "Usuário {$nome} criado.");
            } catch (PDOException $e) {
                // 23000 = violação de UNIQUE (e-mail já existe)
                if ($e->getCode() === '23000') {
                    flash('error', 'Já existe um usuário com esse e-mail.');
                } else {
                    flash('error', 'Erro ao criar usuário.');
                }
            }
        }
    } elseif ($acao === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $eu['id']) {
            flash('error', 'Você não pode desativar a si mesmo.');
        } else {
            $pdo->prepare('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
            flash('success', 'Status do usuário atualizado.');
        }
    }
    redirect('usuarios.php');
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY criado_em DESC')->fetchAll();

layout_topo('Usuários', 'usuarios');
?>

<div class="card">
    <h2>Novo usuário</h2>
    <form method="post" novalidate>
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="criar">
        <div class="field">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" required>
        </div>
        <div class="field">
            <label for="email">E-mail (login)</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="field">
            <label for="senha">Senha (mín. 6 caracteres)</label>
            <input type="password" id="senha" name="senha" required>
        </div>
        <div class="field">
            <label for="perfil">Perfil</label>
            <select id="perfil" name="perfil">
                <option value="vendedor">Vendedor</option>
                <option value="admin">Administrador</option>
            </select>
        </div>
        <button type="submit">Criar usuário</button>
    </form>
</div>

<div class="card">
    <h2>Usuários cadastrados</h2>
    <table>
        <thead>
            <tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ação</th></tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= e($u['nome']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td style="text-transform:capitalize"><?= e($u['perfil']) ?></td>
                    <td>
                        <?php if ($u['ativo']): ?>
                            <span class="badge badge-enviado">ativo</span>
                        <?php else: ?>
                            <span class="badge badge-falha">inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $u['id'] !== (int) $eu['id']): ?>
                            <form method="post" style="display:inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="acao" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-secundario" style="padding:0.3rem 0.7rem;font-size:0.85rem">
                                    <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="helptext">você</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php layout_rodape(); ?>
