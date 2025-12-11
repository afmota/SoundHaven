// Arquivo: js/scripts.js

// Certifica-se de que o código só é executado após o DOM estar pronto e jQuery carregado
$(document).ready(function() {

    // VARIÁVEL GLOBAL PARA ARMAZENAR DADOS DE IMPORTAÇÃO (IMPORTANTE!)
    let importData = {}; 
    
    // Configurações e URLs
    const editUrlBase = 'editar_colecao.php?id=';
    const deleteUrlBase = 'excluir_colecao.php?id=';
    const restoreUrlBase = 'restaurar_colecao.php?id='; 
    const fetchUrlBase = 'fetch_album_details.php?id=';
    
    const $modal = $('#albumModal');
    const $modalContent = $modal.find('.modal-content');
    const $detailsDiv = $('#modal-details');
    const $loaderDiv = $('#modal-loader');
    
    // =========================================================================
    // FUNÇÕES GERAIS DO MODAL
    // =========================================================================

    // Função para fechar o modal
    function closeModal() {
        $modal.css('display', 'none');
        $detailsDiv.hide();
        $loaderDiv.show();
        $modalContent.removeClass('loaded');
        
        // Limpar áreas dinâmicas e restaurar o placeholder da tracklist
        $('#modal-relacionamentos').empty(); 
        $('#modal-actions').empty(); 
        $('#manual-edit-controls').hide(); // Esconde os controles de edição
        
        // Remove o aviso de edição se existir
        $('#edit-warning').remove(); 
        
        $('#import-message-area').html(`
            <ul id="tracklist-ul" style="list-style-type: none; padding-left: 0;">
                <li id="tracklist-status">Carregando lista de faixas...</li>
            </ul>
        `); 
    }

    // Fechar ao clicar no 'x' e fora do modal
    $modal.find('.modal-close').on('click', closeModal);
    $modal.on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Fechar ao pressionar ESC
    $(document).on('keydown', (e) => {
        if (e.key === 'Escape' && $modal.css('display') === 'flex') {
            closeModal();
        }
    });
    
    // =========================================================================
    // FUNÇÕES DE EDIÇÃO MANUAL DE FAIXAS (NOVA FUNCIONALIDADE)
    // =========================================================================

    // ----------------------------------------------------------------
    // FUNÇÃO: REORDENA OS NÚMEROS DAS FAIXAS NA TABELA
    // ----------------------------------------------------------------
    function renumberTracks() {
        let count = 1;
        $('#tracklist-table-body tr').each(function() {
            // Atualiza o texto e o data-atributo da primeira célula (número)
            $(this).find('.track-number-cell').text(count).data('faixa-numero', count);
            count++;
        });
        
        // Adiciona um feedback visual temporário
        $('#edit-warning').html('<i class="fas fa-check-circle"></i> Números de faixas atualizados. Não se esqueça de salvar.');
        setTimeout(() => {
            $('#edit-warning').html('Modo de edição ativo. Use "Adicionar Faixa" e "Renumerar" conforme necessário.');
        }, 3000);
    }

    // ----------------------------------------------------------------
    // FUNÇÃO: ANEXA EVENTOS DE EDIÇÃO MANUAL (REMOVER/ADICIONAR/RENUMERAR)
    // ----------------------------------------------------------------
    function attachManualEditingEvents() {
        
        // 1. Remover Faixa (usa delegação)
        $('#tracklist-table-body').off('click', '.btn-remove-track').on('click', '.btn-remove-track', function() {
            if (confirm('Tem certeza que deseja remover esta faixa?')) {
                $(this).closest('tr').remove();
                renumberTracks(); // Chama a renumerção após remover
            }
        });

        // 2. Adicionar Faixa
        $('#btn-add-track').off('click').on('click', function() {
            const $tableBody = $('#tracklist-table-body');
            // Nota: Não usamos $tableBody.find('tr').length + 1 porque o renumberTracks já fará isso
            
            const newRow = `
                <tr style="border-bottom: 1px solid var(--cor-borda-tabela-linha); color: #fff; background-color: var(--cor-fundo-principal)">
                    <td class="track-number-cell" style="padding: 8px; width: 5%;" data-faixa-numero="0">0</td>
                    <td style="padding: 8px;" contenteditable="true" class="editable-cell">NOVA FAIXA</td>
                    <td style="padding: 8px; width: 15%;" contenteditable="true" class="editable-cell">0:00</td>
                    <td class="track-actions" style="padding: 8px; text-align: center;">
                        <button type="button" class="btn-remove-track" 
                            style="background: none; border: none; color: var(--cor-erro, #dc3545); cursor: pointer;"
                            title="Remover Faixa">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tableBody.append(newRow);
            renumberTracks(); // Chama a renumerção após adicionar
        });
        
        // 3. Renumerar Faixas
        $('#btn-renumber-tracks').off('click').on('click', renumberTracks);
    }
    
    // ----------------------------------------------------------------
    // FUNÇÃO: HABILITA EDIÇÃO MANUAL DA LISTA DE FAIXAS
    // ----------------------------------------------------------------
    function enableManualEditing() {
        const $tableBody = $('#tracklist-table-body');
        
        // 1. Torna Título e Duração editáveis
        $tableBody.find('td:nth-child(2), td:nth-child(3)').each(function() {
            $(this).attr('contenteditable', 'true');
            $(this).addClass('editable-cell'); 
        });

        // 2. Adiciona o botão de Remover Faixa em cada linha
        $tableBody.find('.track-actions').html(`
            <button type="button" class="btn-remove-track" 
                style="background: none; border: none; color: var(--cor-erro, #dc3545); cursor: pointer;"
                title="Remover Faixa">
                <i class="fas fa-trash-alt"></i>
            </button>
        `);
        
        // 3. Mostra os controles globais
        $('#manual-edit-controls').show();

        // 4. Ajusta o visual do botão de confirmação
        const $confirmButton = $('#btn-confirm-import');
        $confirmButton.html('<i class="fas fa-save"></i> Confirmar Edição e Importação');
        $confirmButton.css('background-color', 'var(--cor-sucesso, #28a745)'); 

        // 5. Desativa o botão de edição
        $('#btn-edit-tracks').prop('disabled', true).text('Modo de Edição Ativo');
        $('#btn-edit-tracks').css('color', 'var(--cor-texto-secundario)');
        
        // 6. Adiciona aviso
        $('#edit-warning').remove();
        $('#import-message-area').append('<div id="edit-warning" class="alert alert-info mt-2">Modo de edição ativo. Use "Adicionar Faixa" e "Renumerar" conforme necessário.</div>');
        
        // 7. Anexar eventos de clique
        attachManualEditingEvents();
    }


    // =========================================================================
    // 1. ABERTURA DO MODAL (EVENTO DELEGADO)
    // =========================================================================
    $(document).on('click', '.colecao-item-card.open-modal', async function(e) {
        
        closeModal(); // Limpa e fecha o anterior
        
        const $card = $(this);
        const albumId = $card.data('album-id');
        const albumAtivo = $card.data('ativo'); 
        
        // 1. Mostrar modal e loader
        $modal.css('display', 'flex');
        
        try {
            // 2. Fazer requisição AJAX
            const response = await fetch(fetchUrlBase + albumId);
            const result = await response.json();

            if (result.success && result.album) {
                const album = result.album;
                const catalogo = album.numero_catalogo; 
                
                // 3. Popular o conteúdo do modal
                $('#modal-capa-img').attr('src', album.capa_url || '../assets/no-cover.png');
                $('#modal-titulo').text(album.titulo);
                
                const artistas = album.relacionamentos.artistas ? album.relacionamentos.artistas.join(', ') : 'N/A';
                $('#modal-artistas').text(artistas);
                
                $('#modal-lancamento').text('Lançamento: ' + album.data_lancamento_formatada);
                $('#modal-gravadora').text('Gravadora: ' + (album.gravadora_nome || 'N/A'));

                $('#modal-formato').text(album.formato_descricao || 'N/A');
                $('#modal-aquisicao').text(album.data_aquisicao_formatada || 'N/A');
                $('#modal-preco').text(album.preco_formatado);
                $('#modal-condicao').text(album.condicao || 'N/A');
                $('#modal-catalogo').text(catalogo || 'N/A'); 
                
                // --- Correção de Caracteres Estranhos (Observações) ---
                let obsText = album.observacoes || 'Nenhuma observação registrada.';
                // Decodifica as entidades HTML (ex: &eacute; -> é)
                let decodedObs = $('<div>').html(obsText).text(); 

                $('#modal-observacoes').html(`
                    <h3>Observações</h3>
                    <p id="modal-obs-text" style="white-space: pre-wrap;">${decodedObs}</p>
                `);
                
                // População dos relacionamentos
                const $relContainer = $('#modal-relacionamentos').empty();

                const appendRelationship = (title, items) => {
                    if (items && items.length > 0) {
                        $relContainer.append(`<p><strong>${title}:</strong> ${items.join(', ')}</p>`);
                    }
                };

                appendRelationship('Gêneros', album.relacionamentos.generos);
                appendRelationship('Estilos', album.relacionamentos.estilos);
                appendRelationship('Produtores', album.relacionamentos.produtores);

                // População da lista de faixas
                const $tracklistUl = $('#tracklist-ul'); 
                if (album.faixas && album.faixas.length > 0) {
                    $tracklistUl.empty(); // Limpa o placeholder
                    album.faixas.forEach(faixa => {
                        // Exibe as faixas no formato "Num. Título (Duração)"
                        const li = $(`<li>${faixa.numero_faixa}. ${faixa.titulo} (${faixa.duracao || 'N/A'})</li>`);
                        $tracklistUl.append(li);
                    });
                } else {
                    $tracklistUl.html('<li id="tracklist-status" style="color: var(--cor-texto-secundario);">Nenhuma lista de faixas registrada.</li>');
                }
                
                // Lógica Condicional para Links de Ação
                const $actionsDiv = $('#modal-actions').empty();
                
                if (albumAtivo == 1) { // Item ATIVO
                    
                    let importButtonHtml = '';
                    if (catalogo && catalogo.trim() !== '' && catalogo.trim() !== 'N/A') {
                        
                        // Passando o ID da Coleção (albumId), Catálogo (catalogo) e TÍTULO (album.titulo)
                        importButtonHtml = `
                            <button id="btn-importar-faixas" class="action-icon" 
                                data-colecao-id="${albumId}" 
                                data-catalogo="${catalogo}" 
                                data-titulo="${album.titulo}" 
                                style="background-color: var(--cor-destaque); color: var(--cor-fundo-card); margin-right: 10px; border: none; cursor: pointer; padding: 8px 15px; border-radius: 4px; font-weight: bold;">
                                <i class="fas fa-music"></i> Importar Faixas (Discogs)
                            </button>
                        `;
                    } else {
                        importButtonHtml = `<span style="color: var(--cor-texto-secundario); margin-right: 10px;">Preencha o Nº Catálogo para importar faixas.</span>`;
                    }

                    $actionsDiv.html(`
                        ${importButtonHtml}
                        <a href="${editUrlBase + albumId}" class="edit action-icon"><i class="fa fa-pencil-alt"></i> Editar</a>
                        <a href="${deleteUrlBase + albumId}" class="delete action-icon"
                            onclick="return confirm('Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?');">
                            <i class="fa fa-trash-alt"></i> Remover
                        </a>
                    `);
                    
                } else { // Item REMOVIDO / LIXEIRA
                    $actionsDiv.html(`
                        <a href="${restoreUrlBase + albumId}" class="restore action-icon" style="background-color: #28a745;"
                            onclick="return confirm('Tem certeza que deseja RESTAURAR este item para a sua Coleção?');">
                            <i class="fa fa-undo"></i> Restaurar Item
                        </a>
                    `);
                }


                // 5. Esconder loader e mostrar detalhes
                $loaderDiv.hide();
                $detailsDiv.show();
                $modalContent.addClass('loaded');

            } else {
                alert('Erro ao carregar detalhes: ' + (result.message || 'Resposta inválida.'));
                closeModal();
            }

        } catch (error) {
            console.error('Falha ao conectar ou erro de parsing:', error);
            alert('Falha ao conectar ao servidor para carregar os detalhes.');
            closeModal();
        }
    });


    // ----------------------------------------------------------------
    // 2. INICIADOR DA IMPORTAÇÃO (OUVINTE DE EVENTOS DELEGADO)
    // ----------------------------------------------------------------
    $(document).on('click', '#btn-importar-faixas', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        
        // Pega o ID, Catálogo e Título do atributo data do botão
        const colecaoId = $button.data('colecao-id'); 
        let catalogo = $button.data('catalogo'); 
        let titulo = $button.data('titulo'); 

        // Garante que 'catalogo' é uma string antes de chamar .trim()
        if (typeof catalogo !== 'string') {
            catalogo = String(catalogo || ''); 
        }

        if (!colecaoId || catalogo.trim() === '' || catalogo.trim() === 'N/A') {
             alert("Erro: ID da Coleção não encontrado ou Número de Catálogo inválido.");
             return;
        }
        
        // 1. Prepara a área de mensagens
        $('#import-message-area').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Buscando faixas no Discogs. Por favor, aguarde...</div>');
        
        // 2. Limpa e desativa botões de ação temporariamente
        $('#modal-actions').html(`
            <button type="button" class="btn btn-secondary" id="temp-close-btn">Fechar</button>
        `);
        $('#temp-close-btn').off('click').on('click', closeModal); 

        // 3. Chamada AJAX para importar_faixas_api.php (a primeira etapa: Busca)
        $.ajax({
            url: '/colecao/importar_faixas_api.php',
            type: 'POST',
            data: { 
                colecao_id: colecaoId, 
                numero_catalogo: catalogo,
                titulo_album: titulo // Envia o título (Para ajudar na precisão, se o PHP usar)
            },
            dataType: 'json',
            success: handleImportSuccess, // CHAMA A FUNÇÃO DE MANIPULAÇÃO
            error: function(xhr) {
                let errorMessage = "Erro desconhecido ou de rede.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    errorMessage = "Erro no servidor. Verifique o console para mais detalhes.";
                    console.log("Resposta de erro do servidor:", xhr.responseText);
                }
                handleImportSuccess({ success: false, message: errorMessage });
            }
        });
    });


    // ----------------------------------------------------------------
    // 3. FUNÇÃO: MANIPULAÇÃO DA RESPOSTA E CRIAÇÃO DA INTERFACE DE CONFIRMAÇÃO
    // ----------------------------------------------------------------
    function handleImportSuccess(response) {
        const $messageArea = $('#import-message-area');
        const $buttonsArea = $('#modal-actions'); 
        
        // Se for sucesso na busca
        if (response.success) {
            
            // Se o PHP indicou que precisamos CONFIRMAR as faixas
            if (response.action === 'confirm_tracks') {
                
                // Armazena dados essenciais para o salvamento
                importData = {
                    colecao_id: response.colecao_id, 
                    tracklist: response.tracklist, // Lista de faixas original da API
                    release_title: response.release_title 
                };
                
                // MONTA O HTML DA LISTA DE FAIXAS para revisão do usuário
                let tracklistHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ${response.message}
                    </div>
                    <p>Lançamento encontrado: <strong>${response.release_title}</strong></p>
                    <p class="alert alert-warning p-2">
                        Confirma a importação das ${response.tracklist.length} faixas abaixo?
                        Isso **substituirá** a lista atual do álbum.
                    </p>
                    
                    <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--cor-borda); padding: 0; margin-top: 15px; background-color: var(--cor-fundo-card);">
                        
                        <table class="table table-sm table-borderless mb-0" style="width: 100%; border-collapse: collapse;">
                            
                            <thead>
                                <tr style="border-bottom: 1px solid var(--cor-borda); background-color: var(--cor-fundo-tabela-cabecalho); color: var(--cor-texto-principal);">
                                    <th style="padding: 8px; text-align: left; width: 5%; color: var(--cor-destaque); border-bottom: 2px solid var(--cor-destaque);">#</th>
                                    <th style="padding: 8px; text-align: left; color: var(--cor-destaque); border-bottom: 2px solid var(--cor-destaque);">Título</th>
                                    <th style="padding: 8px; text-align: left; width: 15%; color: var(--cor-destaque); border-bottom: 2px solid var(--cor-destaque);">Duração</th>
                                    <th style="padding: 8px; text-align: center; width: 5%; color: var(--cor-destaque); border-bottom: 2px solid var(--cor-destaque);">Ação</th> </tr>
                            </thead>
                            
                            <tbody id="tracklist-table-body">
                                ${response.tracklist.map(t => `
                                    <tr style="border-bottom: 1px solid var(--cor-borda-tabela-linha); color: var(--cor-texto-principal);">
                                        <td class="track-number-cell" style="padding: 8px; width: 5%;" data-faixa-numero="${t.numero_faixa}">${t.numero_faixa}</td>
                                        <td style="padding: 8px;">${t.titulo}</td>
                                        <td style="padding: 8px; width: 15%;">${t.duracao || 'N/A'}</td>
                                        <td class="track-actions" style="padding: 8px; text-align: center;"></td> </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="manual-edit-controls" style="display: none; margin-top: 10px;">
                        <button type="button" id="btn-add-track" class="btn-custom" 
                            style="background-color: var(--cor-sucesso, #28a745); color: white; margin-right: 10px; padding: 6px 12px; border: none; border-radius: 4px;">
                            <i class="fas fa-plus-circle"></i> Adicionar Faixa
                        </button>
                        <button type="button" id="btn-renumber-tracks" class="btn-custom" 
                            style="background-color: var(--cor-destaque); color: white; padding: 6px 12px; border: none; border-radius: 4px;">
                            <i class="fas fa-sort-numeric-down-alt"></i> Renumerar
                        </button>
                    </div>
                `;

                $messageArea.html(tracklistHTML);
                
                // CRIA OS NOVOS BOTÕES DE AÇÃO (CONFIRMAR/CANCELAR/EDITAR)
                $buttonsArea.html(`
                    <button type="button" class="btn-custom btn-secondary-custom" id="btn-cancel-import" 
                        style="
                            background-color: var(--cor-fundo-card); 
                            color: var(--cor-texto-principal); 
                            border: 1px solid var(--cor-borda); 
                            padding: 8px 15px; 
                            border-radius: 4px; 
                            cursor: pointer;
                            margin-right: 10px;
                        ">
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-custom btn-secondary-custom" id="btn-edit-tracks" 
                        style="
                            background-color: var(--cor-fundo-card); 
                            color: var(--cor-destaque); 
                            border: 1px solid var(--cor-destaque); 
                            padding: 8px 15px; 
                            border-radius: 4px; 
                            cursor: pointer;
                            margin-right: 10px;
                        ">
                        <i class="fas fa-edit"></i> Editar Manualmente
                    </button>
                    
                    <button type="button" class="btn-custom btn-primary-custom" id="btn-confirm-import"
                        style="
                            background-color: var(--cor-destaque); 
                            color: var(--cor-fundo-card); 
                            border: none; 
                            padding: 8px 15px; 
                            border-radius: 4px; 
                            font-weight: bold; 
                            cursor: pointer;
                        ">
                        <i class="fas fa-save"></i> Confirmar Importação
                    </button>
                `);

                // Anexa eventos
                $('#btn-cancel-import').off('click').on('click', closeModal);
                $('#btn-edit-tracks').off('click').on('click', enableManualEditing); 
                $('#btn-confirm-import').off('click').on('click', salvarFaixasConfirmadas);

            } else {
                // Ação de sucesso final (resposta de salvamento do salvar_faixas_confirmadas.php)
                $messageArea.html(`<div class="alert alert-success"><i class="fas fa-thumbs-up"></i> ${response.message}</div>`);
                $buttonsArea.html(`<button type="button" class="btn btn-primary" data-dismiss="modal">OK</button>`);
                
                // Recarrega a página para exibir as novas faixas no modal
                setTimeout(() => { 
                    closeModal();
                    location.reload(); 
                }, 1500); 
            }

        } else {
            // Trata erros (falha na API, Discogs, validação, etc.)
            $messageArea.html(`<div class="alert alert-danger">ERRO: ${response.message}</div>`);
            $buttonsArea.html(`<button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>`);
        }
    }


    // ----------------------------------------------------------------
    // 4. FUNÇÃO: SALVAMENTO DAS FAIXAS (SEGUNDA CHAMADA AJAX) - ATUALIZADA
    // ----------------------------------------------------------------
    function salvarFaixasConfirmadas() {
        let finalTracklist = [];
        
        // Checa se o modo de edição foi ativado (o botão está desativado?)
        const isEditing = $('#btn-edit-tracks').prop('disabled'); 

        if (isEditing) {
            // Modo de EDIÇÃO ATIVO: Lê a tabela diretamente do HTML
            $('#tracklist-table-body tr').each(function() {
                const $row = $(this);
                // Lê as células: [1] Número, [2] Título (editável), [3] Duração (editável)
                const numero_faixa = $row.find('td:nth-child(1)').text().trim();
                const titulo = $row.find('td:nth-child(2)').text().trim();
                const duracao = $row.find('td:nth-child(3)').text().trim();

                if (titulo) { // Garante que não estamos salvando linhas vazias
                    finalTracklist.push({
                        // Mapeamento dos campos que o PHP espera
                        numero_faixa: numero_faixa,
                        titulo: titulo,
                        duracao: (duracao === 'N/A' || duracao === '0:00' || duracao === '') ? null : duracao
                    });
                }
            });
            
            // Atualiza os dados de importação com a lista editada
            importData.tracklist = finalTracklist;
        } 
        // Se o modo de edição não estiver ativo, ele usa o importData.tracklist original (da API)
        
        
        // Validação básica (agora usando a tracklist final)
        if (!importData.colecao_id || importData.tracklist.length === 0) {
            $('#import-message-area').html(`<div class="alert alert-danger">Erro Interno: Dados de importação ou lista de faixas ausentes.</div>`);
            return;
        }

        // Desativa o botão e informa o usuário
        $('#btn-confirm-import').prop('disabled', true);
        $('#modal-actions').html(`
             <span class="text-secondary ml-3"><i class="fas fa-spinner fa-spin"></i> Salvando faixas...</span>
        `);
        
        $('#import-message-area').html(`
            <div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> 
            Salvando faixas de **${importData.release_title || 'o álbum'}**... Por favor, aguarde a atualização.
            </div>
        `);

        $.ajax({
            url: '/colecao/salvar_faixas_confirmadas.php',
            type: 'POST',
            contentType: 'application/json', // CRUCIAL
            data: JSON.stringify(importData), // Envia os dados, incluindo a tracklist editada
            dataType: 'json',
            success: handleImportSuccess, 
            error: function(xhr) {
                let errorMessage = "Erro desconhecido ou de rede ao salvar.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                handleImportSuccess({ success: false, message: errorMessage }); 
            }
        });
    }

}); // Fim do $(document).ready