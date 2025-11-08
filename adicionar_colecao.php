<?php
// Arquivo: adicionar_colecao.php
// Formulário e lógica para adicionar um item à COLEÇÃO PESSOAL (tabela 'colecao').
// SUPORTE TOTAL a Gêneros e Estilos separados.

require_once 'conexao.php';
require_once 'funcoes.php'; 

// ----------------------------------------------------
// 1. CARREGAR DADOS DAS TABELAS DE APOIO (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
    'artistas' => "SELECT id, nome FROM artistas ORDER BY nome ASC",
    'produtores' => "SELECT id, nome FROM produtores ORDER BY nome ASC",
    'gravadoras' => "SELECT id, nome FROM gravadoras ORDER BY nome ASC",
    'generos' => "SELECT id, descricao FROM generos ORDER BY descricao ASC",
    'formatos' => "SELECT id, descricao FROM formatos ORDER BY descricao ASC",
    'tipos' => "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC",
    'estilos' => "SELECT id, descricao FROM estilos ORDER BY descricao ASC", // <--- NOVO
];

try {
    foreach ($sqls as $nome => $sql) {
        $stmt = $pdo->query($sql);
        $listas[$nome] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    die("Erro ao carregar listas de apoio: " . $e->getMessage());
}

// ----------------------------------------------------
// 1.5. CARREGAR DADOS DO CATÁLOGO (STORE) SE HOUVER UM ID
// ----------------------------------------------------
$store_id = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
$dados_store = [];
$artistas_pre_selecionados = [];
$tipo_pre_selecionado = null;

if ($store_id) {
    try {
        // Buscando apenas os campos que existem na sua tabela 'store'
        $sql_store = "SELECT 
                        s.titulo, 
                        s.data_lancamento,
                        s.artista_id,
                        s.tipo_id,
                        s.formato_id
                      FROM store AS s 
                      WHERE s.id = :id";
        
        $stmt_store = $pdo->prepare($sql_store);
        $stmt_store->execute([':id' => $store_id]);
        $dados_store = $stmt_store->fetch(PDO::FETCH_ASSOC);

        if ($dados_store) {
            if ($dados_store['artista_id']) {
                $artistas_pre_selecionados = [$dados_store['artista_id']];
            }
            $tipo_pre_selecionado = $dados_store['tipo_id'];
        }

        if (!$dados_store) {
            $mensagem_status = "ID do álbum do Catálogo ({$store_id}) não encontrado.";
            $tipo_mensagem = 'erro';
            $store_id = null; 
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar dados do Catálogo: " . $e->getMessage();
        $tipo_mensagem = 'erro';
        $store_id = null;
    }
}


// ----------------------------------------------------
// 2. PROCESSAMENTO DO FORMULÁRIO (INSERT com Transação SQL)
// ----------------------------------------------------
$mensagem_status = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // 0. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS
        
        // Dados principais (Coleção)
        $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
        $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
        $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null;
        $data_aquisicao = filter_input(INPUT_POST, 'data_aquisicao', FILTER_SANITIZE_SPECIAL_CHARS);
        $numero_catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT) ?: null;
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

        // IDs (Relacionamentos 1:N)
        $gravadora_id = filter_input(INPUT_POST, 'gravadora_id', FILTER_VALIDATE_INT) ?: null;
        $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);
        $store_id_ref = filter_input(INPUT_POST, 'store_id_ref', FILTER_VALIDATE_INT) ?: null;

        // IDs M:N (Recebidos como arrays)
        $artistas_ids = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $produtores_ids = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $generos_ids = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $estilos_ids = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []; // <--- NOVO
        
        // Validação Mínima
        if (!$titulo || !$data_aquisicao || !$formato_id || empty($artistas_ids)) {
            throw new Exception("Campos obrigatórios (Título, Data de Aquisição, Formato, Artista) não preenchidos.");
        }
        
        // INÍCIO DA TRANSAÇÃO: Tudo ou nada.
        $pdo->beginTransaction();

        // 1. INSERT na tabela principal: 'colecao'
        $sql_colecao = "INSERT INTO colecao 
                        (titulo, data_lancamento, capa_url, data_aquisicao, 
                         numero_catalogo, preco, condicao, observacoes, 
                         gravadora_id, formato_id, store_id)
                        VALUES 
                        (:titulo, :data_lancamento, :capa_url, :data_aquisicao, 
                         :numero_catalogo, :preco, :condicao, :observacoes, 
                         :gravadora_id, :formato_id, :store_id)";
                         
        $stmt_colecao = $pdo->prepare($sql_colecao);
        $stmt_colecao->execute([
            ':titulo' => $titulo,
            ':data_lancamento' => $data_lancamento ?: null,
            ':capa_url' => $capa_url,
            ':data_aquisicao' => $data_aquisicao,
            ':numero_catalogo' => $numero_catalogo,
            ':preco' => $preco,
            ':condicao' => $condicao,
            ':observacoes' => $observacoes,
            ':gravadora_id' => $gravadora_id,
            ':formato_id' => $formato_id,
            ':store_id' => $store_id_ref,
        ]);

        // Obtém o ID da coleção recém-inserido
        $colecao_id = $pdo->lastInsertId();
        
        if (!$colecao_id) {
            throw new Exception("Falha ao obter o ID da coleção inserida.");
        }

        // 2. INSERTS para Artistas (M:N)
        $sql_artista = "INSERT INTO colecao_artista (colecao_id, artista_id) VALUES (:cid, :aid)";
        $stmt_artista = $pdo->prepare($sql_artista);
        foreach ($artistas_ids as $artista_id) {
            if (filter_var($artista_id, FILTER_VALIDATE_INT)) {
                $stmt_artista->execute([':cid' => $colecao_id, ':aid' => $artista_id]);
            }
        }

        // 3. INSERTS para Produtores (M:N)
        $sql_produtor = "INSERT INTO colecao_produtor (colecao_id, produtor_id) VALUES (:cid, :pid)";
        $stmt_produtor = $pdo->prepare($sql_produtor);
        foreach ($produtores_ids as $produtor_id) {
            if (filter_var($produtor_id, FILTER_VALIDATE_INT)) {
                $stmt_produtor->execute([':cid' => $colecao_id, ':pid' => $produtor_id]);
            }
        }

        // 4. INSERTS para Gêneros (M:N)
        $sql_genero = "INSERT INTO colecao_genero (colecao_id, genero_id) VALUES (:cid, :gid)";
        $stmt_genero = $pdo->prepare($sql_genero);
        foreach ($generos_ids as $genero_id) {
            if (filter_var($genero_id, FILTER_VALIDATE_INT)) {
                $stmt_genero->execute([':cid' => $colecao_id, ':gid' => $genero_id]);
            }
        }
        
        // 5. INSERTS para Estilos (M:N) <--- NOVO
        $sql_estilo = "INSERT INTO colecao_estilo (colecao_id, estilo_id) VALUES (:cid, :eid)";
        $stmt_estilo = $pdo->prepare($sql_estilo);
        foreach ($estilos_ids as $estilo_id) {
            if (filter_var($estilo_id, FILTER_VALIDATE_INT)) {
                $stmt_estilo->execute([':cid' => $colecao_id, ':eid' => $estilo_id]);
            }
        }

        // FIM DA TRANSAÇÃO: Confirma todas as operações.
        $pdo->commit();
        
        $mensagem_status = "Álbum '{$titulo}' adicionado à sua coleção (ID: {$colecao_id}) com sucesso!";
        $tipo_mensagem = 'sucesso';
        
        $_POST = []; 

    } catch (\PDOException $e) {
        $pdo->rollBack();
        $mensagem_status = "Erro ao salvar na coleção: Falha no banco de dados. " . $e->getMessage();
        $tipo_mensagem = 'erro';
    } catch (Exception $e) {
        $mensagem_status = "Erro de validação: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}


// ----------------------------------------------------
// 3. HTML DO FORMULÁRIO (Com pré-preenchimento aplicado e CORRIGIDO)
// ----------------------------------------------------
require_once 'header.php'; 
?>

<div class="container">
    <div class="form-container">
        <h1>Adicionar Item à Sua Coleção</h1>
        <p>Insira os metadados do álbum e os detalhes da sua cópia física.</p>

        <?php if (!empty($mensagem_status)): ?>
            <p class="<?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
        <?php endif; ?>

        <form method="POST" action="adicionar_colecao.php">
            
            <?php if ($store_id): ?>
                <input type="hidden" name="store_id_ref" value="<?php echo $store_id; ?>">
            <?php endif; ?>
            
            <fieldset>
                <legend>Metadados do Álbum (Cópia)</legend>

                <label for="titulo">Título do Álbum:*</label>
                <input type="text" id="titulo" name="titulo" required 
                       value="<?php echo htmlspecialchars($dados_store['titulo'] ?? ''); ?>">

                <label for="artistas">Artista(s):*</label>
                <select id="artistas" name="artistas[]" multiple required style="min-height: 120px;">
                    <?php 
                    $selecionados = $artistas_pre_selecionados; 
                    foreach ($listas['artistas'] as $artista): 
                        $is_selected = in_array($artista['id'], $selecionados);
                    ?>
                        <option value="<?php echo $artista['id']; ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($artista['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos artistas. **Obrigatório**</small>

                <label for="produtores">Produtor(es):</label>
                <select id="produtores" name="produtores[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php foreach ($listas['produtores'] as $produtor): ?>
                        <option value="<?php echo $produtor['id']; ?>">
                            <?php echo htmlspecialchars($produtor['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos produtores.</small>

                <label for="generos">Gênero(s) Principal(is):</label>
                <select id="generos" name="generos[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php foreach ($listas['generos'] as $genero): ?>
                        <option value="<?php echo $genero['id']; ?>">
                            <?php echo htmlspecialchars($genero['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos gêneros **principais** (Ex: Rock, Jazz).</small>

                <label for="estilos">Estilos/Subgêneros:</label>
                <select id="estilos" name="estilos[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php foreach ($listas['estilos'] as $estilo): ?>
                        <option value="<?php echo $estilo['id']; ?>">
                            <?php echo htmlspecialchars($estilo['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos estilos **detalhados** (Ex: Post-Punk, Bossa Nova).</small>


                <label for="gravadora_id">Gravadora:</label>
                <select id="gravadora_id" name="gravadora_id">
                    <option value="">-- Selecione (Opcional) --</option>
                    <?php foreach ($listas['gravadoras'] as $gravadora): ?>
                        <option value="<?php echo $gravadora['id']; ?>">
                            <?php echo htmlspecialchars($gravadora['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="data_lancamento">Data de Lançamento:</label>
                <input type="date" id="data_lancamento" name="data_lancamento"
                       value="<?php echo htmlspecialchars($dados_store['data_lancamento'] ?? ''); ?>">
                
                <label for="capa_url">URL da Capa (Imagem):</label>
                <input type="url" id="capa_url" name="capa_url" placeholder="http://ouhttps://...">

            </fieldset>

            <fieldset>
                <legend>Detalhes da Sua Cópia (Coleção)</legend>
                
                <label for="data_aquisicao">Data de Aquisição:*</label>
                <input type="date" id="data_aquisicao" name="data_aquisicao" required value="<?php echo date('Y-m-d'); ?>">
                
                <label for="numero_catalogo">Número de Catálogo:</label>
                <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Ex: R-1234567">
                
                <label for="formato_id">Formato:*</label>
                <select id="formato_id" name="formato_id" required>
                    <option value="">-- Selecione o Formato --</option>
                    <?php 
                    $formato_pre = $dados_store['formato_id'] ?? null;
                    foreach ($listas['formatos'] as $formato): 
                    ?>
                        <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                <?php echo ($formato_pre == $formato['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formato['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="preco">Preço Pago:</label>
                <input type="number" step="0.01" min="0" id="preco" name="preco" placeholder="99.99">
                
                <label for="condicao">Condição:</label>
                <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint">
                
                <label for="observacoes">Observações:</label>
                <textarea id="observacoes" name="observacoes" rows="3"><?php
                    $tipo_descricao = null;
                    if ($tipo_pre_selecionado) {
                        // Busca manual pelo tipo na lista de apoio
                        foreach ($listas['tipos'] as $tipo_item) {
                            if ($tipo_item['id'] == $tipo_pre_selecionado) {
                                $tipo_descricao = $tipo_item['descricao'];
                                break;
                            }
                        }
                        
                        if ($tipo_descricao) {
                            echo "Tipo de Álbum Original (Catálogo): " . htmlspecialchars($tipo_descricao) . "\n";
                        }
                    }
                ?></textarea>
                <small>O campo "Tipo" do Catálogo foi movido para cá como observação, pois sua Coleção só exige o Formato.</small>
                
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="save-button">Salvar na Coleção</button>
                <a href="index.php" class="back-link">Voltar ao Catálogo</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>