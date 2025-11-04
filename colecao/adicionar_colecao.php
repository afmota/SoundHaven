<?php
// Arquivo: adicionar_colecao.php
// Formulário e lógica para adicionar um item à COLEÇÃO PESSOAL (tabela 'colecao').
// SUPORTE TOTAL a Gêneros e Estilos separados.
// NOVIDADE: Suporte à criação de FAIXAS (Músicas)

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';
$colecao_id = null; // Para armazenar o ID após a inserção

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
// 2. PROCESSAMENTO DO FORMULÁRIO (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Filtra e sanitiza todos os campos de entrada
    $input = filter_input_array(INPUT_POST, [
        'titulo' => FILTER_DEFAULT,
        'data_aquisicao' => FILTER_DEFAULT,
        'numero_catalogo' => FILTER_DEFAULT,
        'preco' => FILTER_VALIDATE_FLOAT,
        'condicao' => FILTER_DEFAULT,
        'observacoes' => FILTER_DEFAULT,
        'gravadora_id' => FILTER_VALIDATE_INT,
        'formato_id' => FILTER_VALIDATE_INT,
        'store_id' => FILTER_VALIDATE_INT,
        'ativo' => FILTER_VALIDATE_INT,
        'capa_url' => FILTER_DEFAULT,
    ]);

    // Lida com campos multi-select
    $generos_ids = $_POST['generos_ids'] ?? [];
    $estilos_ids = $_POST['estilos_ids'] ?? [];
    $faixas_data = $_POST['faixas'] ?? []; // Array de faixas

    // Validação básica
    if (empty($input['titulo']) || empty($input['data_aquisicao'])) {
        $mensagem_status = "O título e a data de aquisição são obrigatórios.";
        $tipo_mensagem = 'erro';
    } else {
        
        $pdo->beginTransaction();
        try {
            // 2.1. INSERIR NA TABELA 'colecao'
            $sql_colecao = "INSERT INTO colecao (
                                titulo, data_lancamento, capa_url, data_aquisicao, 
                                numero_catalogo, preco, condicao, observacoes, 
                                gravadora_id, formato_id, ativo, store_id, 
                                criado_em, atualizado_em
                            ) VALUES (
                                :titulo, :data_lancamento, :capa_url, :data_aquisicao, 
                                :numero_catalogo, :preco, :condicao, :observacoes, 
                                :gravadora_id, :formato_id, :ativo, :store_id, 
                                NOW(), NOW()
                            )";
                            
            $stmt_colecao = $pdo->prepare($sql_colecao);
            $stmt_colecao->execute([
                ':titulo' => $input['titulo'],
                ':data_lancamento' => $input['data_lancamento'] ?: null, // Assumindo que este campo está no POST, mas não foi explicitamente listado no filtro.
                ':capa_url' => $input['capa_url'],
                ':data_aquisicao' => $input['data_aquisicao'],
                ':numero_catalogo' => $input['numero_catalogo'],
                ':preco' => $input['preco'],
                ':condicao' => $input['condicao'],
                ':observacoes' => $input['observacoes'],
                ':gravadora_id' => $input['gravadora_id'],
                ':formato_id' => $input['formato_id'],
                ':ativo' => $input['ativo'] ?? 1,
                ':store_id' => $input['store_id'],
            ]);

            $colecao_id = $pdo->lastInsertId();

            // 2.2. INSERIR GÊNEROS E ESTILOS
            $sql_genero = "INSERT INTO colecao_generos (colecao_id, genero_id) VALUES (:colecao_id, :genero_id)";
            $stmt_genero = $pdo->prepare($sql_genero);
            foreach ($generos_ids as $genero_id) {
                if (filter_var($genero_id, FILTER_VALIDATE_INT)) {
                    $stmt_genero->execute([':colecao_id' => $colecao_id, ':genero_id' => $genero_id]);
                }
            }
            
            $sql_estilo = "INSERT INTO colecao_estilos (colecao_id, estilo_id) VALUES (:colecao_id, :estilo_id)";
            $stmt_estilo = $pdo->prepare($sql_estilo);
            foreach ($estilos_ids as $estilo_id) {
                if (filter_var($estilo_id, FILTER_VALIDATE_INT)) {
                    $stmt_estilo->execute([':colecao_id' => $colecao_id, ':estilo_id' => $estilo_id]);
                }
            }

            // 2.3. INSERIR FAIXAS (MÚSICAS)
            if (!empty($faixas_data)) {
                $sql_faixa = "INSERT INTO faixas_colecao (colecao_id, numero_faixa, titulo, duracao, audio_url) 
                              VALUES (:colecao_id, :numero_faixa, :titulo, :duracao, :audio_url)";
                $stmt_faixa = $pdo->prepare($sql_faixa);
                
                // Variável para controle da numeração
                $faixa_num = 1; 

                foreach ($faixas_data as $faixa) {
                    $titulo = trim($faixa['titulo'] ?? '');
                    $duracao = trim($faixa['duracao'] ?? '');
                    $audio_url = trim($faixa['audio_url'] ?? '');
                    
                    if (!empty($titulo)) {
                        $stmt_faixa->execute([
                            ':colecao_id' => $colecao_id,
                            ':numero_faixa' => $faixa_num++,
                            ':titulo' => $titulo,
                            ':duracao' => $duracao,
                            ':audio_url' => $audio_url
                        ]);
                    }
                }
            }

            $pdo->commit();
            $mensagem_status = "Item adicionado à Coleção com sucesso! (ID: {$colecao_id})";
            $tipo_mensagem = 'sucesso';
            
            // Redireciona para a página de detalhes/edição para evitar reenvio
            header("Location: detalhes_colecao.php?id={$colecao_id}&status=adicionado");
            exit();

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $mensagem_status = "Erro ao adicionar item: " . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }
}

// Inicializa variáveis para o formulário no modo GET (primeira carga)
$input = $input ?? [];

require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1><i class="fas fa-plus-circle"></i> Adicionar Item à Coleção</h1>
                <a href="colecao.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar para Coleção
                </a>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <div class="card" style="margin-top: 20px;">
                <form method="POST" action="adicionar_colecao.php" class="edit-form">

                    <fieldset>
                        <legend>Informações do Álbum (Coleção)</legend>
                        
                        <label for="titulo">Título do Álbum (Coleção):</label>
                        <input type="text" id="titulo" name="titulo" required
                                value="<?php echo htmlspecialchars($input['titulo'] ?? ''); ?>">

                        <label for="store_id">Referência do Catálogo (Store ID):</label>
                        <input type="number" id="store_id" name="store_id" placeholder="ID do álbum na tabela store (opcional)"
                                value="<?php echo htmlspecialchars($input['store_id'] ?? ''); ?>">
                        <small>Ajuda a manter a consistência com o catálogo principal.</small>

                        <label for="capa_url">URL da Capa:</label>
                        <input type="text" id="capa_url" name="capa_url" 
                                value="<?php echo htmlspecialchars($input['capa_url'] ?? ''); ?>">
                        
                    </fieldset>
                    
                    <fieldset style="margin-top: 25px;">
                        <legend>Detalhes da Sua Cópia</legend>
                        
                        <div class="field-row">
                            <div class="field-column">
                                <label for="data_aquisicao">Data de Aquisição:</label>
                                <input type="date" id="data_aquisicao" name="data_aquisicao" required
                                        value="<?php echo htmlspecialchars($input['data_aquisicao'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="field-column">
                                <label for="preco">Preço (R$):</label>
                                <input type="number" id="preco" name="preco" step="0.01" placeholder="0.00"
                                        value="<?php echo htmlspecialchars($input['preco'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="field-row">
                            <div class="field-column">
                                <label for="numero_catalogo">Número de Catálogo:</label>
                                <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Número impresso no vinil/CD"
                                        value="<?php echo htmlspecialchars($input['numero_catalogo'] ?? ''); ?>">
                            </div>
                            <div class="field-column">
                                <label for="condicao">Condição:</label>
                                <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint"
                                        value="<?php echo htmlspecialchars($input['condicao'] ?? ''); ?>">
                            </div>
                        </div>

                        <label for="observacoes">Observações:</label>
                        <textarea id="observacoes" name="observacoes" rows="4"><?php echo htmlspecialchars($input['observacoes'] ?? ''); ?></textarea>
                        
                        <label for="ativo" style="margin-top: 15px;">Status do Item:</label>
                        <select id="ativo" name="ativo">
                            <option value="1" <?php echo (isset($input['ativo']) && $input['ativo'] == 1) ? 'selected' : ''; ?>>1 - Ativo (Visível)</option>
                            <option value="0" <?php echo (isset($input['ativo']) && $input['ativo'] == 0) ? 'selected' : ''; ?>>0 - Excluído (Lógica)</option>
                        </select>
                        
                    </fieldset>

                    <fieldset style="margin-top: 25px;">
                        <legend>Metadados Adicionais</legend>

                        <div class="input-with-add-btn" id="add-gravadora-container">
                            <div class="input-with-select">
                                <label for="gravadora_id">Gravadora:</label>
                                <select id="gravadora_id" name="gravadora_id">
                                    <option value="">-- Selecione uma Gravadora --</option>
                                    <?php foreach ($listas['gravadoras'] as $g): ?>
                                        <option value="<?php echo $g['id']; ?>"
                                                <?php echo (isset($input['gravadora_id']) && $input['gravadora_id'] == $g['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="add-button" data-type="gravadora" data-input-id="gravadora_id" data-url="../api/add_gravadora.php" title="Adicionar nova gravadora">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <label for="formato_id">Formato:</label>
                        <select id="formato_id" name="formato_id">
                            <option value="">-- Selecione um Formato --</option>
                            <?php foreach ($listas['formatos'] as $f): ?>
                                <option value="<?php echo $f['id']; ?>"
                                        <?php echo (isset($input['formato_id']) && $input['formato_id'] == $f['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($f['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label for="generos_ids">Gêneros (Multi-select):</label>
                        <select id="generos_ids" name="generos_ids[]" multiple size="5">
                            <?php foreach ($listas['generos'] as $g): ?>
                                <option value="<?php echo $g['id']; ?>"
                                        <?php echo (in_array($g['id'], $generos_ids)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="estilos_ids">Estilos (Multi-select):</label>
                        <select id="estilos_ids" name="estilos_ids[]" multiple size="5">
                            <?php foreach ($listas['estilos'] as $e): ?>
                                <option value="<?php echo $e['id']; ?>"
                                        <?php echo (in_array($e['id'], $estilos_ids)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                    </fieldset>
                    
                    <fieldset style="margin-top: 30px;">
                        <legend>Faixas do Álbum (Músicas e Áudio Preview)</legend>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button type="button" id="btn-adicionar-faixa" class="btn-adicionar-catalogo" style="flex-grow: 1;">
                                <i class="fas fa-plus"></i> Adicionar Faixa Manualmente
                            </button>
                            <button type="button" id="btn-importar-faixas" class="btn-adicionar-catalogo secondary-action" style="flex-grow: 1; background-color: var(--cor-texto-secundario);" title="Funcionalidade a ser implementada">
                                <i class="fas fa-folder-open"></i> Importar Faixas de Pasta (Pendente)
                            </button>
                        </div>
                        
                        <div id="faixas-container">
                            <?php 
                            // Se houver dados de faixas submetidos, mostre-os novamente (em caso de erro)
                            if (!empty($faixas_data)):
                                $faixa_num = 1;
                                foreach ($faixas_data as $faixa): 
                                    if (!empty($faixa['titulo'])):
                                        // O template de faixa é renderizado para cada uma
                                        include 'faixa_template.php'; 
                                    endif;
                                    $faixa_num++;
                                endforeach;
                            else:
                                echo '<p id="faixas-vazias" class="alerta" style="text-align: center;">Nenhuma faixa adicionada ainda.</p>';
                            endif;
                            ?>
                        </div>

                    </fieldset>


                    <div class="form-actions large-gap">
                        <a href="colecao.php" class="back-link secondary-action">
                            <i class="fas fa-times-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="save-button">
                            <i class="fas fa-save"></i> Salvar na Coleção
                        </button>
                    </div>
                </form>
            </div> 

        </main>
    </div> 
</div> 

<script>
// ==========================================
// 1. MANUSEIO DINÂMICO DE FAIXAS
// ==========================================

const faixasContainer = document.getElementById('faixas-container');
const btnAdicionarFaixa = document.getElementById('btn-adicionar-faixa');
const faixasVazias = document.getElementById('faixas-vazias');
let nextFaixaNum = <?php echo !empty($faixas_data) ? count($faixas_data) + 1 : 1; ?>;


// FUNÇÃO QUE CRIA O HTML DE UMA NOVA FAIXA
function createFaixaHtml(faixaNum, titulo = '', duracao = '', audio_url = '') {
    // O nome do campo usa 'faixas[...]' para ser submetido como um array
    return `
    <div class="faixa-item card" data-faixa-num="${faixaNum}" style="padding: 15px; margin-bottom: 10px; border-left: 3px solid var(--cor-destaque);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0; font-size: 1em;">Faixa #${faixaNum}</h4>
            <button type="button" class="remove-faixa-btn secondary-action" style="background-color: var(--cor-borda); border: none; padding: 5px 10px; border-radius: 4px; color: var(--cor-texto-principal);">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>
        
        <label for="faixa_titulo_${faixaNum}">Título:</label>
        <input type="text" id="faixa_titulo_${faixaNum}" name="faixas[${faixaNum}][titulo]" value="${titulo}" required>
        
        <div class="field-row">
            <div class="field-column" style="flex: 1;">
                <label for="faixa_duracao_${faixaNum}">Duração (MM:SS):</label>
                <input type="text" id="faixa_duracao_${faixaNum}" name="faixas[${faixaNum}][duracao]" value="${duracao}" placeholder="Ex: 3:45">
            </div>
            <div class="field-column" style="flex: 2;">
                <label for="faixa_url_${faixaNum}">URL do Áudio Preview:</label>
                <input type="text" id="faixa_url_${faixaNum}" name="faixas[${faixaNum}][audio_url]" value="${audio_url}" placeholder="Ex: /audio/album_id/01.mp3">
                <small>Caminho do arquivo (MP3, FLAC) no servidor.</small>
            </div>
        </div>
    </div>
    `;
}

// ADICIONAR NOVA FAIXA
btnAdicionarFaixa.addEventListener('click', () => {
    // Remove a mensagem de faixas vazias se existir
    if (faixasVazias) {
        faixasVazias.style.display = 'none';
    }
    
    // Adiciona o novo bloco HTML ao container
    faixasContainer.insertAdjacentHTML('beforeend', createFaixaHtml(nextFaixaNum));
    
    // Incrementa o contador para a próxima faixa
    nextFaixaNum++;
});

// REMOVER FAIXA (usando delegação de eventos no container)
faixasContainer.addEventListener('click', (event) => {
    // Verifica se o clique foi em um botão de remover
    if (event.target.closest('.remove-faixa-btn')) {
        const itemToRemove = event.target.closest('.faixa-item');
        if (itemToRemove) {
            itemToRemove.remove();
            
            // Reajusta a mensagem de faixa vazia, se necessário
            if (faixasContainer.children.length === 0) {
                 if (faixasVazias) {
                     faixasVazias.style.display = 'block';
                 }
            }
            
            // NOTA: A renumeração dos campos para "numero_faixa" no banco será feita 
            // na lógica do PHP (2.3. INSERIR FAIXAS) baseada na ordem do POST.
        }
    }
});


// ==========================================
// 2. MANUSEIO DE ADIÇÃO RÁPIDA DE METADADOS
// (Código JS existente para Gravadoras, etc. mantido)
// ==========================================

// ... (Resto do JavaScript de metadados, como no seu arquivo original) ...
// (Mantido em forma de placeholder para não omitir o resto do seu JS)

// Exemplo da estrutura original:
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.input-with-add-btn').forEach(container => {
        const btn = container.querySelector('.add-button');
        const select = container.querySelector('select');
        const originalContent = btn.innerHTML; // Salva o conteúdo original

        btn.addEventListener('click', () => {
            // MODO: ATIVAR ADIÇÃO
            if (!container.classList.contains('adding-mode')) {
                container.classList.add('adding-mode');
                btn.innerHTML = '<i class="fas fa-save"></i> Salvar';
                
                // Cria o campo de texto para o novo valor
                const input = document.createElement('input');
                input.type = 'text';
                input.placeholder = `Novo ${btn.getAttribute('data-type')}`;
                input.id = `new-${btn.getAttribute('data-type')}-input`;
                
                // Substitui o select pelo input temporariamente
                select.style.display = 'none';
                select.parentNode.insertBefore(input, select);
            
            // MODO: SALVAR VALOR
            } else {
                const input = document.getElementById(`new-${btn.getAttribute('data-type')}-input`);
                const value = input.value.trim();
                const type = btn.getAttribute('data-type');
                const url = btn.getAttribute('data-url');
                
                if (!value) {
                    alert('Insira um valor.');
                    return;
                }
                
                // Lógica AJAX para salvar o novo metadado
                btn.disabled = true;
                input.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

                fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ value: value })
                })
                .then(response => {
                    const status = response.status;
                    return response.json().then(body => ({ status, body }));
                })
                .then(({ status, body }) => {
                    if (status === 201) {
                        // 1. Reexibe o select e remove o input
                        select.style.display = 'block';
                        input.remove();

                        // 2. Adiciona a nova opção ao select
                        const newOption = document.createElement('option');
                        newOption.value = body.id;
                        newOption.textContent = body.value;
                        select.appendChild(newOption);
                        
                        // 3. Seleciona o novo item
                        const isMultiple = select.hasAttribute('multiple');
                        if (isMultiple) {
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

// ==========================================
// 3. MANUSEIO DE IMPORTAÇÃO DE FAIXAS POR PASTA
// ==========================================

const btnImportarFaixas = document.getElementById('btn-importar-faixas');

// AJUSTE: Remove o "(Pendente)" do texto do botão HTML para ficar pronto.
btnImportarFaixas.innerHTML = '<i class="fas fa-folder-open"></i> Importar Faixas de Pasta';

btnImportarFaixas.addEventListener('click', async () => {
    
    // No 'editar_colecao.php', o ID da coleção já existe; no 'adicionar_colecao.php' é nulo.
    const colecaoIdInput = document.querySelector('input[name="colecao_id"]');
    const colecao_id = colecaoIdInput ? colecaoIdInput.value : 0; // 0 é um ID dummy para criação
    
    // Pede o caminho da pasta via prompt
    const default_example = 'Exemplo: sepultura/roots_1996';
    const path = prompt(`Insira o caminho da pasta de áudio (relativo à sua pasta 'audio/'):\n${default_example}`);
    
    if (!path || path.trim() === '') {
        return; // Cancelado
    }
    
    // 1. Desabilita o botão e mostra o loading
    btnImportarFaixas.disabled = true;
    btnImportarFaixas.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
    
    // 2. Chamada AJAX
    try {
        const response = await fetch('../api/importar_faixas_pasta.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ colecao_id: colecao_id, folder_path: path.trim() })
        });

        const data = await response.json();

        if (response.ok && (data.status === 'sucesso' || data.status === 'alerta')) {
            
            // Se for sucesso, limpa e popula; se for alerta (faixas não encontradas), limpa.
            faixasContainer.innerHTML = '';
            nextFaixaNum = 1;

            if (data.faixas && data.faixas.length > 0) {
                // Adiciona as faixas importadas
                data.faixas.forEach(faixa => {
                    // A função createFaixaHtml foi definida no bloco PHP (usando os valores extraídos)
                    faixasContainer.insertAdjacentHTML('beforeend', createFaixaHtml(
                        nextFaixaNum, 
                        faixa.titulo, 
                        faixa.duracao, 
                        faixa.audio_url
                    ));
                    nextFaixaNum++;
                });

                alert('Importação concluída: ' + data.faixas.length + ' faixas encontradas e carregadas no formulário.');
                
            } else {
                 // Adiciona a mensagem de faixas vazias de volta
                 faixasContainer.insertAdjacentHTML('beforeend', '<p id="faixas-vazias" class="alerta" style="text-align: center;">Nenhuma faixa adicionada ainda.</p>');
                 alert(data.message); // Exibe a mensagem de alerta (Ex: "Nenhum arquivo encontrado")
            }
            
        } else {
            // Trata erros 400, 405, 500 etc.
            alert('Erro na importação: ' + (data.message || 'Erro desconhecido. Verifique o caminho da pasta e a instalação da getID3.'));
        }

    } catch (error) {
        console.error('Erro de rede:', error);
        alert('Erro de comunicação com o servidor durante a importação.');
    } finally {
        // 3. Restaura o botão
        btnImportarFaixas.disabled = false;
        btnImportarFaixas.innerHTML = '<i class="fas fa-folder-open"></i> Importar Faixas de Pasta';
    }
});
</script>

<?php require_once '../include/footer.php'; ?>