<?php
// Arquivo: usuarios.php
// Gerenciamento de Usuários (Acesso restrito a Administradores)

session_start();
require_once 'db/conexao.php';
require_once 'funcoes.php';

// ----------------------------------------------------
// 1. CHECAGEM DE PERMISSÃO DE ACESSO
// ----------------------------------------------------
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    // Redireciona para o dashboard ou login se não for Admin (tipo 1)
    header('Location: dashboard.php');
    exit();
}

$mensagem = '';
$acao = filter_input(INPUT_GET, 'acao', FILTER_DEFAULT);
$id_usuario_editar = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$usuario_selecionado = null;

// ----------------------------------------------------
// 2. PROCESSAMENTO DE FORMULÁRIO (Salvar/Excluir/Editar)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $post_acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = filter_input(INPUT_POST, 'senha', FILTER_DEFAULT);
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_NUMBER_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

    try {
        if ($post_acao == 'salvar') {
            if ($id) {
                // UPDATE (Edição)
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, tipo = :tipo";
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $sql .= ", senha = :senha";
                }
                $sql .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id);
                if (!empty($senha)) {
                    $stmt->bindParam(':senha', $senha_hash);
                }
                $mensagem = 'Usuário atualizado com sucesso!';
            } else {
                // INSERT (Novo Usuário)
                if (empty($senha)) {
                    throw new Exception("A senha é obrigatória para um novo usuário.");
                }
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':senha', $senha_hash);
                $mensagem = 'Usuário cadastrado com sucesso!';
            }
            
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();

        } elseif ($post_acao == 'excluir' && $id) {
            // DELETE (Excluir) - Usaremos exclusão lógica (ativo=0)
            $sql = "UPDATE usuarios SET ativo = 0 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $mensagem = 'Usuário desativado (excluído) com sucesso!';
            // Previne que o próprio usuário se exclua acidentalmente
            if ($id == $_SESSION['usuario_id']) {
                header('Location: logout.php');
                exit();
            }
        }
        
        // Redireciona para limpar o POST e evitar reenvio
        header('Location: usuarios.php?msg=' . urlencode($mensagem));
        exit();

    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
    }
}

// ----------------------------------------------------
// 3. RECUPERAR DADOS PARA EDIÇÃO
// ----------------------------------------------------
if ($acao == 'editar' && $id_usuario_editar) {
    $sql_usuario = "SELECT id, nome, email, tipo FROM usuarios WHERE id = :id AND ativo = 1";
    $stmt_usuario = $pdo->prepare($sql_usuario);
    $stmt_usuario->bindParam(':id', $id_usuario_editar);
    $stmt_usuario->execute();
    $usuario_selecionado = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
}

// ----------------------------------------------------
// 4. LISTAGEM DE USUÁRIOS ATIVOS
// ----------------------------------------------------
$sql_listagem = "
    SELECT 
        u.id, 
        u.nome, 
        u.email, 
        u.tipo,
        CASE u.tipo
            WHEN 1 THEN 'Administrador'
            WHEN 2 THEN 'Ordinário'
            ELSE 'Desconhecido'
        END AS tipo_descricao
    FROM usuarios AS u
    WHERE ativo = 1
    ORDER BY u.nome";
$stmt_listagem = $pdo->query($sql_listagem);
$usuarios = $stmt_listagem->fetchAll(PDO::FETCH_ASSOC);

// Exibe mensagem de sucesso/erro após redirecionamento
if (isset($_GET['msg'])) {
    $mensagem = htmlspecialchars($_GET['msg']);
}


// ----------------------------------------------------
// HTML DA PÁGINA
// ----------------------------------------------------
require_once 'include/header.php'; 
?>

<div class="container">
    <h1>Gerenciamento de Usuários</h1>
    
    <?php if ($mensagem): ?>
        <p class="alert alert-info" style="padding: 15px; background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $mensagem; ?>
        </p>
    <?php endif; ?>

    <div style="background-color: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #e9ecef;">
        <h2><?php echo $usuario_selecionado ? 'Editar Usuário: ' . htmlspecialchars($usuario_selecionado['nome']) : 'Novo Usuário'; ?></h2>
        
        <form method="POST" action="usuarios.php">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($usuario_selecionado): ?>
                <input type="hidden" name="id" value="<?php echo $usuario_selecionado['id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario_selecionado['nome'] ?? ''); ?>" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_selecionado['email'] ?? ''); ?>" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="senha"><?php echo $usuario_selecionado ? 'Nova Senha (deixe em branco para manter a atual):' : 'Senha:'; ?></label>
                <input type="password" id="senha" name="senha" <?php echo $usuario_selecionado ? '' : 'required'; ?> class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="tipo">Tipo de Usuário:</label>
                <select id="tipo" name="tipo" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="1" <?php echo ($usuario_selecionado && $usuario_selecionado['tipo'] == 1) ? 'selected' : ''; ?>>1 - Administrador</option>
                    <option value="2" <?php echo ($usuario_selecionado && $usuario_selecionado['tipo'] == 2) ? 'selected' : ''; ?>>2 - Ordinário</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Salvar Usuário
            </button>
            <?php if ($usuario_selecionado): ?>
                <a href="usuarios.php" class="btn btn-secondary" style="margin-left: 10px; padding: 10px 15px; background-color: #6c757d; color: white; border: none; border-radius: 4px; text-decoration: none;">Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>Usuários Ativos</h2>
    <table class="data-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #343a40; color: white;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px; text-align: left;">Nome</th>
                <th style="padding: 10px; text-align: left;">E-mail</th>
                <th style="padding: 10px;">Tipo</th>
                <th style="padding: 10px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 10px;">Nenhum usuário ativo encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px; text-align: center;"><?php echo $usuario['id']; ?></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($usuario['nome']); ?></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td style="padding: 10px; text-align: center;"><?php echo htmlspecialchars($usuario['tipo_descricao']); ?></td>
                        <td style="padding: 10px; text-align: center;">
                            <a href="usuarios.php?acao=editar&id=<?php echo $usuario['id']; ?>" style="color: #ffc107; margin-right: 10px;">Editar</a>
                            
                            <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                <form method="POST" action="usuarios.php" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja desativar o usuário <?php echo htmlspecialchars($usuario['nome']); ?>?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer;">Desativar</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6c757d;">(Você)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'include/footer.php'; ?>