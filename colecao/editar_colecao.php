<?php
// Arquivo: editar_colecao.php
// Formulário e lógica para EDIÇÃO de um item na COLEÇÃO PESSOAL (tabela 'colecao').

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';

// Variável para armazenar o ID do item que está sendo editado (GET ou POST)
$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT);

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
    'estilos' => "SELECT id, descricao FROM estilos ORDER BY descricao ASC",
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
// 2. PROCESSAMENTO DO FORMULÁRIO (UPDATE com Transação SQL)
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $colecao_id) {
    
    try {
        // 0. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS (Reutilizando a lógica do adicionar)
        $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
        $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
        $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null;
        $data_aquisicao = filter_input(INPUT_POST, 'data_aquisicao', FILTER_SANITIZE_SPECIAL_CHARS);
        $numero_catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT) ?: null;
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

        // **INÍCIO DA CORREÇÃO: Novo Campo Spotify**
        $spotify_embed_url = filter_input(INPUT_POST, 'spotify_embed_url', FILTER_UNSAFE_RAW);
        // Garante que o campo será NULL no banco se estiver vazio ou só com espaços
        if (empty(trim($spotify_embed_url ?? ''))) { 
            $spotify_embed_url = NULL; 
        }
        // **FIM DA CORREÇÃO: Novo Campo Spotify**


        // IDs (Relacionamentos 1:N)
        $gravadora_id = filter_input(INPUT_POST, 'gravadora_id', FILTER_VALIDATE_INT) ?: null;
        $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);
        $store_id_ref = filter_input(INPUT_POST, 'store_id_ref', FILTER_VALIDATE_INT) ?: null;

        // IDs M:N (Recebidos como arrays)
        $artistas_ids = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $produtores_ids = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $generos_ids = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $estilos_ids = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        
        // Validação Mínima
        if (!$titulo || !$data_aquisicao || !$formato_id || empty($artistas_ids)) {
            throw new Exception("Campos obrigatórios (Título, Data de Aquisição, Formato, Artista) não preenchidos.");
        }
        
        // INÍCIO DA TRANSAÇÃO: Tudo ou nada.
        $pdo->beginTransaction();

        // 1. UPDATE na tabela principal: 'colecao'
        $sql_colecao = "UPDATE colecao SET
                            titulo = :titulo, 
                            data_lancamento = :data_lancamento, 
                            capa_url = :capa_url, 
                            data_aquisicao = :data_aquisicao, 
                            numero_catalogo = :numero_catalogo, 
                            preco = :preco, 
                            condicao = :condicao, 
                            observacoes = :observacoes, 
                            gravadora_id = :gravadora_id, 
                            formato_id = :formato_id,
                            spotify_embed_url = :spotify_embed_url,  /* <-- CORREÇÃO: Novo campo no UPDATE */
                            atualizado_em = NOW()
                        WHERE id = :id";
                            
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
            ':spotify_embed_url' => $spotify_embed_url, /* <-- CORREÇÃO: Novo parâmetro */
            ':id' => $colecao_id,
        ]);
        
        // 2. EXCLUSÃO E REINSERÇÃO DOS RELACIONAMENTOS M:N (Transacional)
        
        // Função utilitária para limpar e reinserir M:N
        $reinsertMN = function($pivot, $ids, $coluna_id_externa) use ($pdo, $colecao_id) {
            // A. Limpa todos os registros antigos
            $sql_delete = "DELETE FROM $pivot WHERE colecao_id = :cid";
            $pdo->prepare($sql_delete)->execute([':cid' => $colecao_id]);
            
            // B. Insere os novos (ou vazios, se $ids for vazio)
            if (!empty($ids)) {
                $sql_insert = "INSERT INTO $pivot (colecao_id, $coluna_id_externa) VALUES (:cid, :eid)";
                $stmt_insert = $pdo->prepare($sql_insert);
                foreach ($ids as $id) {
                    if (filter_var($id, FILTER_VALIDATE_INT)) {
                        $stmt_insert->execute([':cid' => $colecao_id, ':eid' => $id]);
                    }
                }
            }
        };

        $reinsertMN('colecao_artista', $artistas_ids, 'artista_id');
        $reinsertMN('colecao_produtor', $produtores_ids, 'produtor_id');
        $reinsertMN('colecao_genero', $generos_ids, 'genero_id');
        $reinsertMN('colecao_estilo', $estilos_ids, 'estilo_id');

        // FIM DA TRANSAÇÃO: Confirma todas as operações.
        $pdo->commit();
        
        // REDIRECIONAMENTO COM SUCESSO
        header('Location: /colecao/detalhes_colecao.php?id=' . $colecao_id . '&status=editado');
        exit; 

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
// 3. CARREGAR DADOS EXISTENTES PARA PRÉ-PREENCHIMENTO (GET ou falha POST)
// ----------------------------------------------------
$item = []; // Dados do item da coleção a ser editado
$artistas_selecionados = [];
$produtores_selecionados = [];
$generos_selecionados = [];
$estilos_selecionados = [];

if ($colecao_id) {
    try {
        // Busca do item (usando a mesma SQL do detalhes_colecao.php)
        // O campo spotify_embed_url é carregado automaticamente com o SELECT c.*
        $sql = "SELECT c.* FROM colecao AS c WHERE c.id = :id AND c.ativo = 1"; 

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $colecao_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $mensagem_status = "Item da Coleção ID {$colecao_id} não encontrado ou inativo.";
            $tipo_mensagem = 'erro';
        }

        if ($item) {
            // Carregar IDs M:N para pré-seleção
            $artistas_selecionados = array_column(fetchRelacionamentosM_N($pdo, 'colecao_artista', 'artistas', 'colecao_id', $colecao_id), 'id');
            $produtores_selecionados = array_column(fetchRelacionamentosM_N($pdo, 'colecao_produtor', 'produtores', 'colecao_id', $colecao_id), 'id');
            $generos_selecionados = array_column(fetchRelacionamentosM_N($pdo, 'colecao_genero', 'generos', 'colecao_id', $colecao_id), 'id');
            $estilos_selecionados = array_column(fetchRelacionamentosM_N($pdo, 'colecao_estilo', 'estilos', 'colecao_id', $colecao_id), 'id');
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar dados para edição: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}


// ----------------------------------------------------
// 4. HTML DO FORMULÁRIO (Com pré-preenchimento e Adição Rápida)
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1><?php echo $item ? 'Editar Item na Coleção' : 'Erro na Edição'; ?></h1>
                <a href="<?php echo $colecao_id ? '/colecao/detalhes_colecao.php?id=' . $colecao_id : 'index.php'; ?>" 
                    class="back-link">
                    <i class="fas fa-chevron-left"></i> Cancelar e Voltar
                </a>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <?php if ($item): ?>
            
                <div class="card">
                    <p class="intro-text">Edite os metadados do álbum e os detalhes da sua cópia física.</p>

                    <form method="POST" action="/colecao/editar_colecao.php" class="edit-form">
                        
                        <input type="hidden" name="colecao_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                        
                        <?php if ($item['store_id']): ?>
                            <input type="hidden" name="store_id_ref" value="<?php echo htmlspecialchars($item['store_id']); ?>">
                        <?php endif; ?>
                        
                        <div class="colecao-grid">
                            
                            <fieldset>
                                <legend><i class="fas fa-compact-disc"></i> Metadados do Álbum</legend>

                                <label for="titulo">Título do Álbum:*</label>
                                <input type="text" id="titulo" name="titulo" required 
                                        value="<?php echo htmlspecialchars($item['titulo'] ?? ''); ?>">

                                <label for="artistas">Artista(s):*</label>
                                <div class="form-group-with-add">
                                    <select id="artistas" name="artistas[]" multiple required style="min-height: 120px;">
                                        <?php 
                                        foreach ($listas['artistas'] as $artista): 
                                            $is_selected = in_array($artista['id'], $artistas_selecionados);
                                        ?>
                                            <option value="<?php echo $artista['id']; ?>"
                                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($artista['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="add-new-controls">
                                        <input type="text" id="artistas_novo_nome" placeholder="Novo Artista" class="small-input">
                                        <button type="button" class="btn-add-entity" data-target-id="artistas" data-table="artistas" data-input-id="artistas_novo_nome">
                                            <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small>Use Ctrl (ou Cmd) para selecionar múltiplos artistas. **Obrigatório**</small>

                                <label for="produtores">Produtor(es):</label>
                                <div class="form-group-with-add">
                                    <select id="produtores" name="produtores[]" multiple style="min-height: 120px;">
                                        <?php foreach ($listas['produtores'] as $produtor): 
                                            $is_selected = in_array($produtor['id'], $produtores_selecionados);
                                        ?>
                                            <option value="<?php echo $produtor['id']; ?>"
                                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($produtor['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="add-new-controls">
                                        <input type="text" id="produtores_novo_nome" placeholder="Novo Produtor" class="small-input">
                                        <button type="button" class="btn-add-entity" data-target-id="produtores" data-table="produtores" data-input-id="produtores_novo_nome">
                                            <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small>Use Ctrl (ou Cmd) para selecionar múltiplos produtores.</small>
                                
                                <label for="generos">Gênero(s) Principal(is):</label>
                                <div class="form-group-with-add">
                                    <select id="generos" name="generos[]" multiple style="min-height: 120px;">
                                        <?php foreach ($listas['generos'] as $genero): 
                                            $is_selected = in_array($genero['id'], $generos_selecionados);
                                        ?>
                                            <option value="<?php echo $genero['id']; ?>"
                                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($genero['descricao']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="add-new-controls">
                                        <input type="text" id="generos_novo_nome" placeholder="Novo Gênero" class="small-input">
                                        <button type="button" class="btn-add-entity" data-target-id="generos" data-table="generos" data-input-id="generos_novo_nome">
                                            <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small>Gêneros **principais** (Ex: Rock, Jazz).</small>

                                <label for="estilos">Estilos/Subgêneros:</label>
                                <div class="form-group-with-add">
                                    <select id="estilos" name="estilos[]" multiple style="min-height: 120px;">
                                        <?php foreach ($listas['estilos'] as $estilo): 
                                            $is_selected = in_array($estilo['id'], $estilos_selecionados);
                                        ?>
                                            <option value="<?php echo $estilo['id']; ?>"
                                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($estilo['descricao']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="add-new-controls">
                                        <input type="text" id="estilos_novo_nome" placeholder="Novo Estilo" class="small-input">
                                        <button type="button" class="btn-add-entity" data-target-id="estilos" data-table="estilos" data-input-id="estilos_novo_nome">
                                            <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small>Estilos **detalhados** (Ex: Post-Punk, Bossa Nova).</small>
                                
                                <label for="data_lancamento">Data de Lançamento:</label>
                                <input type="date" id="data_lancamento" name="data_lancamento"
                                        value="<?php echo htmlspecialchars($item['data_lancamento'] ?? ''); ?>">

                                <label for="gravadora_id">Gravadora:</label>
                                <div class="form-group-with-add">
                                    <select id="gravadora_id" name="gravadora_id">
                                        <option value="">-- Selecione (Opcional) --</option>
                                        <?php foreach ($listas['gravadoras'] as $gravadora): ?>
                                            <option value="<?php echo $gravadora['id']; ?>"
                                                    <?php echo ($item['gravadora_id'] == $gravadora['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gravadora['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="add-new-controls">
                                        <input type="text" id="gravadoras_novo_nome" placeholder="Nova Gravadora" class="small-input">
                                        <button type="button" class="btn-add-entity" data-target-id="gravadora_id" data-table="gravadoras" data-input-id="gravadoras_novo_nome">
                                            <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                        </button>
                                    </div>
                                </div>

                                <label for="capa_url">URL da Capa (Imagem):</label>
                                <input type="url" id="capa_url" name="capa_url" placeholder="http://ouhttps://..."
                                        value="<?php echo htmlspecialchars($item['capa_url'] ?? ''); ?>">

                                <div class="form-group full-width-field">
                                    <label for="spotify_embed_url">Código de Embed do Spotify (Inteiro):</label>
                                    <textarea 
                                        id="spotify_embed_url" 
                                        name="spotify_embed_url" 
                                        rows="4" 
                                        class="form-control" 
                                        placeholder='Cole o código <iframe> inteiro, obtido na opção "Incorporar álbum" do Spotify.'
                                    ><?php echo htmlspecialchars($item['spotify_embed_url'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Apenas para exibição do player de músicas na página de detalhes.</small>
                                </div>
                                </fieldset>

                            <fieldset>
                                <legend><i class="fas fa-tags"></i> Detalhes da Sua Cópia</legend>
                                
                                <label for="data_aquisicao">Data de Aquisição:*</label>
                                <input type="date" id="data_aquisicao" name="data_aquisicao" required 
                                        value="<?php echo htmlspecialchars($item['data_aquisicao'] ?? date('Y-m-d')); ?>">
                                
                                <label for="formato_id">Formato:*</label>
                                <select id="formato_id" name="formato_id" required>
                                    <option value="">-- Selecione o Formato --</option>
                                    <?php foreach ($listas['formatos'] as $formato): ?>
                                        <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                                <?php echo ($item['formato_id'] == $formato['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formato['descricao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="numero_catalogo">Número de Catálogo:</label>
                                <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Ex: R-1234567"
                                        value="<?php echo htmlspecialchars($item['numero_catalogo'] ?? ''); ?>">
                                
                                <label for="preco">Preço Médio:</label>
                                <input type="number" step="0.01" min="0" id="preco" name="preco" placeholder="99.99"
                                        value="<?php echo htmlspecialchars($item['preco'] ?? ''); ?>">
                                
                                <label for="condicao">Condição:</label>
                                <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint"
                                        value="<?php echo htmlspecialchars($item['condicao'] ?? ''); ?>">
                                
                                <label for="observacoes">Observações:</label>
                                <textarea id="observacoes" name="observacoes" rows="8"><?php echo htmlspecialchars($item['observacoes'] ?? ''); ?></textarea>
                                <small>Use este campo para anotações pessoais sobre a cópia.</small>
                                
                            </fieldset>
                        </div> 
                        
                        <div class="form-actions large-gap">
                            <a href="/colecao/detalhes_colecao.php?id=<?php echo htmlspecialchars($colecao_id); ?>" class="back-link secondary-action">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Seleciona todos os botões de Adição Rápida
    const buttons = document.querySelectorAll('.btn-add-entity');
    // NOTE: O endpoint deve ser o caminho correto
    const endpoint = 'add_entity_ajax.php'; 

    buttons.forEach(button => {
        button.addEventListener('click', (e) => {
            const btn = e.currentTarget;
            const container = btn.closest('.form-group-with-add');
            const input = document.getElementById(btn.dataset.inputId);
            const select = document.getElementById(btn.dataset.targetId);
            
            // Alternar modo de adição/salvamento
            if (!container.classList.contains('adding-mode')) {
                // MODO: ATIVAR ADIÇÃO (Mostra o input e muda o botão para salvar)
                container.classList.add('adding-mode');
                input.focus();
            } else {
                // MODO: SALVAR
                const value = input.value.trim();
                const table = btn.dataset.table;

                if (value.length === 0) {
                    alert('Por favor, digite um nome antes de salvar.');
                    return;
                }

                // Bloqueia o botão e input
                btn.disabled = true;
                input.disabled = true;
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; // Ícone de loading

                const formData = new FormData();
                formData.append('table', table);
                formData.append('value', value);

                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(({ status, body }) => {
                    if (status === 200 && body.success) {
                        // 1. Criar a nova opção
                        const newOption = document.createElement('option');
                        newOption.value = body.id;
                        newOption.textContent = body.value;
                        
                        // 2. Adicionar ao SELECT
                        select.appendChild(newOption);

                        // 3. Selecionar o novo item
                        if (select.multiple) {
                            // Para multi-select (Artistas, Produtores, Gêneros, Estilos)
                            newOption.selected = true; 
                        } else {
                            // Para single-select (Gravadora)
                            select.value = body.id; 
                        }
                        
                        // 4. Feedback e Limpeza
                        alert(`Sucesso! "${body.value}" adicionado.`);
                        input.value = '';

                    } else if (status === 409) {
                        alert(body.message + ' Tente selecionar na lista.');
                        input.value = '';
                    } else {
                        alert('Erro ao adicionar: ' + (body.message || 'Erro de comunicação.'));
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    alert('Erro de rede ou servidor ao tentar salvar.');
                })
                .finally(() => {
                    // MODO: DESATIVAR ADIÇÃO (Voltar ao estado original)
                    container.classList.remove('adding-mode');
                    btn.disabled = false;
                    input.disabled = false;
                    btn.innerHTML = originalContent; 
                });
            }
        });
    });
});
</script>

<?php require_once '../include/footer.php'; ?>