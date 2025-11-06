<?php
// Arquivo: index.php (COM CAMPO DE BUSCA E SCRIPT EXTERNO)

// 1. Inclui o arquivo de conexão
require_once 'conexao.php';

// --- Lógica do Negócio: Consulta ao Banco de Dados (Carga Inicial) ---

$sql = "SELECT
            s.id, 
            s.titulo, 
            a.nome AS nome_artista,
            DATE_FORMAT(s.data_lancamento, '%d/%m/%Y') AS data_lancamento, 
            t.descricao AS tipo,
            sit.descricao AS status,
            f.descricao AS formato
        FROM store AS s
            LEFT JOIN artistas AS a ON s.artista_id = a.id 
            LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
            LEFT JOIN situacao AS sit ON s.situacao = sit.id
            LEFT JOIN formatos AS f ON s.formato_id = f.id
        WHERE s.deletado = 0
        ORDER BY s.data_lancamento
        LIMIT 100;"; 

try {
    $stmt = $pdo->prepare($sql); 
    $stmt->execute();
    $albuns = $stmt->fetchAll();

} catch (\PDOException $e) {
    $erro = "Erro ao buscar álbuns: " . $e->getMessage();
    $albuns = []; 
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acervo Digital</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <h1>Acervo Digital</h1>
    <hr>

        <div class="search-container">
        <label for="search">Buscar Título do Álbum:</label>
        <input type="text" id="search" name="search" placeholder="Digite o título do álbum..." autocomplete="off">
    </div>

    <?php if (isset($erro)): ?>
        <p class="erro"><?php echo $erro; ?></p>
    <?php elseif (empty($albuns)): ?>
        <p>Nenhum álbum encontrado no Acervo Digital.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Artista/Banda</th>
                    <th>Lançamento</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Formato</th>
                    <th>Ações</th> </tr>
            </thead>
            <tbody>
                <?php 
                // Loop para exibir os dados
                foreach ($albuns as $album): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($album['id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['titulo'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['nome_artista'] ?? 'Artista Desconhecido'); ?></td>
                    <td><?php echo htmlspecialchars($album['data_lancamento'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($album['tipo'] ?? 'Não Classificado'); ?></td>
                    <td><?php echo htmlspecialchars($album['status'] ?? 'Desconhecida'); ?></td>
                    <td><?php echo htmlspecialchars($album['formato'] ?? 'Sem Formato'); ?></td>
                    <td></td> 
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="filtro.js"></script>
</body>
</html>