<?php
// Arquivo: index.php (Encarnação Modular)

// Inclui o arquivo de conexão
require_once 'conexao.php';

// --- CONSULTA 1: BUSCA TODOS OS ARTISTAS (para popular o dropdown) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$artistas = [];

try {
    $stmt_artistas = $pdo->query($sql_artistas); 
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    $erro_artistas = "Erro ao buscar artistas: " . $e->getMessage();
}


// --- CONSULTA 2: BUSCA A LISTAGEM PRINCIPAL (Carga Inicial) ---

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

// Inclui o cabeçalho e abre o main-container
require_once 'header.php'; 
?>

    <h1>Listagem de Álbuns</h1>

    <?php 
        // Mensagem de sucesso após edição
        if (isset($_GET['status']) && $_GET['status'] == 'editado'): 
        ?>
            <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album']); ?>" atualizado com sucesso!</p>
        
        <?php 
        // NOVO: Mensagem de sucesso após exclusão
        elseif (isset($_GET['status']) && $_GET['status'] == 'excluido'): 
        ?>
            <p class="sucesso">Álbum excluído logicamente com sucesso.</p>
        
        <?php 
        // NOVO: Mensagem de erro
        elseif (isset($_GET['status']) && strpos($_GET['status'], 'erro') !== false): 
        ?>
            <p class="erro">Erro ao processar a exclusão. Tente novamente.</p>
            
        <?php endif; ?>
    
    <div class="  s-container">
    
        <div class="search-container">
            <label for="search_titulo">Buscar Título do Álbum:</label>
            <input type="text" id="search_titulo" name="search_titulo" placeholder="Digite o título do álbum..." autocomplete="off">
        </div>

        <div class="search-container">
            <label for="  _artista">Filtrar por Artista:</label>
            <select id="  _artista" name="  _artista">
                <option value="">-- Selecione um Artista --</option>
                <?php if (!empty($artistas)): ?>
                    <?php foreach ($artistas as $artista): ?>
                        <option value="<?php echo htmlspecialchars($artista['id']); ?>">
                            <?php echo htmlspecialchars($artista['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        
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

                    <td>
                        <a href="editar.php?id=<?php echo $album['id']; ?>" title="Editar Álbum">
                            <i class="fa-solid fa-pencil" style="color: #007bff; cursor: pointer;"></i>
                       </a>

                        <a href="excluir.php?id=<?php echo $album['id']; ?>"
                            title="Excluir Álbum"
                            onclick="return confirm('Tem certeza que deseja marcar este álbum (ID: <?php echo $album['id']; ?>) como excluído?');">
                            <i class="fa-solid fa-trash-can" style="color: #dc3545; cursor: pointer; margin-left: 8px;"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php
// Inclui o fechamento do main-container, footer e scripts
require_once 'footer.php';