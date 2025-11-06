<?php
// Arquivo: index.php

// 1. Inclui o arquivo de conexão para ter acesso à variável $pdo
require_once 'db/conexao.php';

// --- Lógica do Negócio: Consulta ao Banco de Dados ---

$sql = "SELECT id, titulo, data_lancamento, criado_em FROM store WHERE deletado = 0 LIMIT 100"; 

try {
    // 2. Prepara a consulta (Prepared Statement - O segredo da segurança)
    $stmt = $pdo->prepare($sql); 
    
    // 3. Executa a consulta
    $stmt->execute();
    
    // 4. Obtém todos os resultados como um array associativo
    $albuns = $stmt->fetchAll();

} catch (\PDOException $e) {
    // Em caso de erro na consulta, exibe uma mensagem
    $erro = "Erro ao buscar álbuns: " . $e->getMessage();
    $albuns = []; // Garante que a variável $albuns seja um array vazio
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SoundHaven - Catálogo de Álbuns</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <h1>Catálogo de Álbuns (SoundHaven)</h1>
    <hr>

    <?php if (isset($erro)): ?>
        <p class="erro"><?php echo $erro; ?></p>
    <?php elseif (empty($albuns)): ?>
        <p>Nenhum álbum encontrado na tabela Store.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título do Álbum</th>
                    <th>Lançamento</th>
                    <th>Criado Em</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Loop PHP para percorrer os dados obtidos
                foreach ($albuns as $album): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($album['id']); ?></td>
                    <td><?php echo htmlspecialchars($album['titulo']); ?></td>
                    <td><?php echo htmlspecialchars($album['data_lancamento']); ?></td>
                    <td><?php echo htmlspecialchars($album['criado_em']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

</body>
</html>