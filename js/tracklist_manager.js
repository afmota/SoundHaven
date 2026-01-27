// --- TRACKLIST_MANAGER.JS ---

document.addEventListener('DOMContentLoaded', () => {
    const tracklistBody = document.getElementById('tracklist-body');
    const getVal = (id) => document.getElementById(id)?.value || '';
    
    // Elementos do Modal de Gravadora
    const btnSaveNewGravadora = document.getElementById('btn-save-new-gravadora');
    const inputNovaGravadora = document.getElementById('nova_gravadora_nome');

    /**
     * Auxiliar para pegar múltiplos valores de campos Select2 (Artistas, Gêneros, etc.)
     */
    const getSelectValues = (id) => {
        const el = document.getElementById(id);
        if (!el) return [];
        return $(el).val() || []; 
    };

    /**
     * Insere uma nova linha na tabela de faixas
     */
    function inserirLinhaNaTabela(numero, titulo, duracao) {
        if (!tracklistBody) return; 
        const row = `
            <tr>
                <td>${numero}</td>
                <td contenteditable="true" class="editable-title">${titulo}</td>
                <td contenteditable="true" class="editable-duration">${duracao || ''}</td>
                <td>
                    <button type="button" class="btn-action secondary-action btn-sm remove-track">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        tracklistBody.insertAdjacentHTML('beforeend', row);
    }

    // --- LÓGICA DO MODAL: NOVA GRAVADORA ---
    if (btnSaveNewGravadora) {
        btnSaveNewGravadora.addEventListener('click', async () => {
            const nome = inputNovaGravadora.value.trim();
            if (!nome) {
                alert("Por favor, digite o nome da gravadora.");
                return;
            }

            btnSaveNewGravadora.disabled = true;
            btnSaveNewGravadora.textContent = 'Salvando...';

            try {
                const response = await fetch('ajax_salvar_gravadora.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `nome=${encodeURIComponent(nome)}`
                });

                const result = await response.json();

                if (result.success) {
                    // 1. Cria a nova opção e injeta no Select2
                    const newOption = new Option(result.nome, result.id, true, true);
                    $('#gravadora_id').append(newOption).trigger('change');
                    
                    // 2. Limpa o input e fecha o modal via API do Bootstrap
                    inputNovaGravadora.value = '';
                    $('#modalGravadora').modal('hide');

                    // 3. Força a remoção de resíduos do modal (fix para o "trava tela")
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                } else {
                    alert("Erro: " + result.error);
                }
            } catch (error) {
                console.error("Erro ao salvar gravadora:", error);
                alert("Erro de conexão ao salvar gravadora.");
            } finally {
                btnSaveNewGravadora.disabled = false;
                btnSaveNewGravadora.textContent = 'Salvar e Selecionar';
            }
        });
    }

    // --- SINCRONIZAÇÃO DISCOGS ---
    const btnSync = document.getElementById('btn-import-tracks');
    if (btnSync) {
        btnSync.addEventListener('click', async () => {
            const catNo = getVal('numero_catalogo');
            const tituloAlbum = getVal('titulo');

            if (!catNo) { alert("Digite o Número de Catálogo."); return; }

            btnSync.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btnSync.disabled = true;

            try {
                const response = await fetch('importar_faixas_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        numero_catalogo: catNo,
                        titulo: tituloAlbum 
                    })
                });
                const data = await response.json();
                if (data.success && data.tracklist) {
                    tracklistBody.innerHTML = '';
                    data.tracklist.forEach(track => {
                        inserirLinhaNaTabela(track.numero_faixa, track.titulo, track.duracao);
                    });
                } else {
                    alert("Erro: " + (data.message || "Não encontrado no Discogs."));
                }
            } catch (error) {
                alert("Falha na busca.");
            } finally {
                btnSync.innerHTML = '<i class="fas fa-sync"></i> Sincronizar';
                btnSync.disabled = false;
            }
        });
    }

    // --- SALVAMENTO FINAL DO ÁLBUM ---
    const btnSaveFullAlbum = document.getElementById('btn-save-full-album');
    if (btnSaveFullAlbum) {
        btnSaveFullAlbum.addEventListener('click', async (e) => {
            e.preventDefault();
            
            if (btnSaveFullAlbum.disabled) return;

            const payload = {
                store_id: getVal('store_id'),
                titulo: getVal('titulo'),
                gravadora_id: $('#gravadora_id').val(), 
                formato_id: getVal('formato_id'),
                numero_catalogo: getVal('numero_catalogo'),
                data_lancamento: getVal('data_lancamento'),
                data_aquisicao: getVal('data_aquisicao'),
                preco: getVal('preco'),
                observacoes: getVal('observacoes'),
                capa_url: getVal('capa_url'),
                artistas: getSelectValues('artistas'),
                generos: getSelectValues('generos'),
                estilos: getSelectValues('estilos'),
                produtores: getSelectValues('produtores'),
                tracklist: Array.from(tracklistBody.querySelectorAll('tr')).map((row, index) => ({
                    posicao: index + 1,
                    titulo: row.querySelector('.editable-title')?.textContent.trim() || '',
                    duracao: row.querySelector('.editable-duration')?.textContent.trim() || ''
                }))
            };

            if (!payload.titulo || payload.artistas.length === 0) {
                alert("Título e Artista são obrigatórios!");
                return;
            }

            btnSaveFullAlbum.disabled = true;
            btnSaveFullAlbum.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';

            try {
                const res = await fetch('inserir_album_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if(result.success) {
                    alert("Álbum salvo com sucesso!");
                    window.location.href = 'colecao.php';
                } else {
                    alert("Erro ao salvar: " + result.error);
                    btnSaveFullAlbum.disabled = false;
                    btnSaveFullAlbum.innerHTML = '<i class="fas fa-save"></i> SALVAR NA COLEÇÃO';
                }
            } catch (e) {
                alert("Erro de conexão com o servidor.");
                btnSaveFullAlbum.disabled = false;
                btnSaveFullAlbum.innerHTML = '<i class="fas fa-save"></i> SALVAR NA COLEÇÃO';
            }
        });
    }

    // --- BOTÕES DA TRACKLIST (MANUAL E REMOÇÃO) ---
    document.getElementById('btn-add-manual')?.addEventListener('click', () => {
        inserirLinhaNaTabela(tracklistBody.rows.length + 1, 'Nova Música', '0:00');
    });

    tracklistBody?.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-track');
        if (btn && confirm('Remover esta faixa?')) {
            btn.closest('tr').remove();
            // Reordena os números das faixas
            Array.from(tracklistBody.rows).forEach((r, i) => r.cells[0].textContent = i + 1);
        }
    });
});