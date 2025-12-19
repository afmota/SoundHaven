<?php
// Arquivo: editar_album.php
// Script para edição de um álbum no Catálogo (tabela 'store').

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// --- CONFIGURAÇÃO DE MIGRAÇÃO ---
// ID do status na tabela 'situacao' que dispara a migração para a Coleção
$ID_SITUACAO_ADQUIRIDO = 4; 

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';

// ----------------------------------------------------
// 1. PROCESSAMENTO DO FORMULÁRIO (UPDATE)
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 0. Sanitização e Validação
    $album_id = filter_input(INPUT_POST, 'album_id', FILTER_VALIDATE_INT);
    
    // NOVO: Captura o ID da Situação Original para verificar se houve mudança
    $situacao_original_id = filter_input(INPUT_POST, 'situacao_original_id', FILTER_VALIDATE_INT) ?: null; // <--- RE-INCORPORADO
    
    // CORREÇÃO CRÍTICA APLICADA AQUI: Captura a string pura e decodifica para remover &#39;
    $titulo_input = filter_input(INPUT_POST, 'titulo', FILTER_DEFAULT);
    $titulo = html_entity_decode($titulo_input, ENT_QUOTES, 'UTF-8');
    
    $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
    $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null; // <--- NOVO/CORRIGIDO: URL da Capa
    
    // IDs (Relacionamentos 1:N)
    $artista_id = filter_input(INPUT_POST, 'artista_id', FILTER_VALIDATE_INT) ?: null;
    $tipo_id = filter_input(INPUT_POST, 'tipo_id', FILTER_VALIDATE_INT) ?: null;
    $situacao_id = filter_input(INPUT_POST, 'situacao_id', FILTER_VALIDATE_INT) ?: null;
    $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT) ?: null;

    if (!$album_id || !$titulo) {
        // Se o ID ou o Título estiverem ausentes, algo está errado
        header('Location: store.php?status=erro_validacao');
        exit;
    }

    try {
        // 1. SQL de UPDATE
        $sql = "UPDATE store SET 
                titulo = :titulo, 
                artista_id = :artista_id,
                data_lancamento = :data_lancamento,
                capa_url = :capa_url,          /* <--- NOVO/CORRIGIDO */
                tipo_id = :tipo_id,
                situacao = :situacao_id,
                formato_id = :formato_id,
                atualizado_em = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo, // Usa o valor DECODIFICADO/PURO
            ':artista_id' => $artista_id,
            ':data_lancamento' => $data_lancamento ?: null,
            ':capa_url' => $capa_url,          /* <--- NOVO/CORRIGIDO */
            ':tipo_id' => $tipo_id,
            ':situacao_id' => $situacao_id,
            ':formato_id' => $formato_id,
            ':id' => $album_id
        ]);

        // ------------------------------------------------------
        // LÓGICA DE MIGRAÇÃO: Redireciona para o formulário da Coleção
        // ------------------------------------------------------
        // A migração só ocorre se a nova situação for ADQUIRIDO (4) E a situação original for diferente (ou nula).
        if ($situacao_id == $ID_SITUACAO_ADQUIRIDO && $situacao_original_id != $ID_SITUACAO_ADQUIRIDO) { // <--- RE-INCORPORADO
            
            $url_redirecionamento = "../colecao/adicionar_colecao.php?store_id=" . $album_id;
            
            header("Location: " . $url_redirecionamento);
            exit; // Interrompe o script e redireciona
        } 
        
        // ------------------------------------------------------
        // REDIRECIONAMENTO PADRÃO (Edição normal)
        // ------------------------------------------------------
        header('Location: store.php?status=editado&album=' . urlencode($titulo)); // Usa o título puro
        exit;

    } catch (\PDOException $e) {
        // Se a edição falhar, volta para o store com erro.
        header('Location: store.php?status=erro_db&msg=' . urlencode($e->getMessage()));
        exit;
    }
} 

// ----------------------------------------------------
// 2. EXIBIÇÃO DO FORMULÁRIO (GET)
// ----------------------------------------------------

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$album = null;

if ($id) {
    // 2.1. Buscar dados do álbum
    $sql_album = "SELECT 
                      s.id, s.titulo, s.data_lancamento, s.artista_id, 
                      s.tipo_id, s.situacao, s.formato_id,
                      s.capa_url                       /* <--- NOVO/CORRIGIDO */
                    FROM store AS s
                    WHERE s.id = :id AND s.deletado = 0";
    
    try {
        $stmt_album = $pdo->prepare($sql_album);
        $stmt_album->execute([':id' => $id]);
        $album = $stmt_album->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            $mensagem_status = "Álbum não encontrado ou foi excluído.";
            $tipo_mensagem = 'erro';
            $id = null;
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar álbum: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }

    // 2.2. Carregar listas de apoio (se o álbum foi encontrado)
    if ($album) {
        $sqls = [
            'artistas' => "SELECT id, nome FROM artistas ORDER BY nome ASC",
            'tipos' => "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC",
            'situacoes' => "SELECT id, descricao FROM situacao ORDER BY descricao ASC",
            'formatos' => "SELECT id, descricao FROM formatos ORDER BY descricao ASC",
        ];
        
        $listas = [];
        try {
            foreach ($sqls as $nome => $sql) {
                $stmt = $pdo->query($sql);
                $listas[$nome] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\PDOException $e) {
            $mensagem_status = "Erro ao carregar listas de apoio: " . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }

} else {
    $mensagem_status = "ID do álbum não fornecido.";
    $tipo_mensagem = 'erro';
}

// ----------------------------------------------------
// 3. HTML
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            <div class="page-header-actions">
                <h1>
                    <?php 
                        // CORREÇÃO: Decodificar o título na exibição para evitar &#39;
                        $titulo_display = html_entity_decode($album['titulo'] ?? 'Erro', ENT_QUOTES, 'UTF-8');
                        echo $album ? 'Editar Álbum: ' . htmlspecialchars($titulo_display) : 'Erro'; 
                    ?>
                </h1>
                <a href="store.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar para o Catálogo
                </a>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="<?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <?php if ($album): ?>
            
                <div class="card">
                    <form method="POST" action="editar_album.php" class="edit-form">
                        
                        <input type="hidden" name="album_id" value="<?php echo htmlspecialchars($album['id']); ?>">
                        <input type="hidden" name="situacao_original_id" value="<?php echo htmlspecialchars($album['situacao']); ?>"> 

                        <div class="form-grid">
                            
                            <div>
                                <label for="titulo">Título:*</label>
                                <input type="text" id="titulo" name="titulo" required 
                                        value="<?php 
                                            // CORREÇÃO: Decodificar o título no campo de input.
                                            echo htmlspecialchars(html_entity_decode($album['titulo'], ENT_QUOTES, 'UTF-8')); 
                                        ?>">

                                <label for="artista_id">Artista:</label>
                                <select id="artista_id" name="artista_id">
                                    <option value="">-- Selecione (Opcional) --</option>
                                    <?php foreach ($listas['artistas'] as $artista): ?>
                                        <option value="<?php echo htmlspecialchars($artista['id']); ?>"
                                                <?php echo ($album['artista_id'] == $artista['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($artista['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="data_lancamento">Data de Lançamento:</label>
                                <input type="date" id="data_lancamento" name="data_lancamento"
                                        value="<?php echo htmlspecialchars($album['data_lancamento']); ?>">

                                <label for="capa_url">URL da Capa:</label>
                                <input type="url" id="capa_url" name="capa_url" placeholder="https://..."
                                        value="<?php echo htmlspecialchars($album['capa_url'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="tipo_id">Tipo:</label>
                                <select id="tipo_id" name="tipo_id">
                                    <option value="">-- Selecione (Opcional) --</option>
                                    <?php foreach ($listas['tipos'] as $tipo): ?>
                                        <option value="<?php echo htmlspecialchars($tipo['id']); ?>"
                                                <?php echo ($album['tipo_id'] == $tipo['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['descricao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <label for="formato_id">Formato (Opcional):</label>
                                <select id="formato_id" name="formato_id">
                                    <option value="">-- Selecione (Opcional) --</option>
                                    <?php foreach ($listas['formatos'] as $formato): ?>
                                        <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                                <?php echo ($album['formato_id'] == $formato['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formato['descricao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <label for="situacao_id">Situação:*</label>
                                <select id="situacao_id" name="situacao_id" required>
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($listas['situacoes'] as $situacao): ?>
                                        <option value="<?php echo htmlspecialchars($situacao['id']); ?>"
                                                <?php echo ($album['situacao'] == $situacao['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($situacao['descricao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Se você selecionar **Adquirido** (ID <?php echo $ID_SITUACAO_ADQUIRIDO; ?>) e a situação for alterada, será redirecionado para detalhar a cópia na sua Coleção Pessoal.</small>
                            </div>
                        </div> 
                        
                        <div class="form-actions">
                            <a href="../loja/store.php" class="back-link secondary-action">
                                <i class="fas fa-times-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="save-button">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div> 
            <?php endif; ?>

        </main>
    </div> 
</div> 

<?php require_once '../include/footer.php'; ?>