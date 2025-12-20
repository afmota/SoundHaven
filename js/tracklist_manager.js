/**
 * SoundHaven - Tracklist Manager
 * Gerencia a sincronização com Discogs, edição manual e salvamento final.
 */

document.addEventListener('DOMContentLoaded', () => {

    // --- 1. LÓGICA DE SINCRONIZAÇÃO COM O DISCOGS ---
    const btnImport = document.getElementById('btn-import-discogs');
    if (btnImport) {
        btnImport.addEventListener('click', async () => {
            const catNo = document.getElementById('numero_catalogo').value;
            const colecaoId = document.getElementById('colecao_id').value;

            if (!catNo) {
                alert("Por favor, digite o Número de Catálogo primeiro.");
                return;
            }

            btnImport.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            btnImport.disabled = true;

            const formData = new FormData();
            formData.append('numero_catalogo', catNo);
            formData.append('colecao_id', colecaoId);

            try {
                const response = await fetch('importar_faixas_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.tracklist) {
                    if (confirm(`Encontramos o álbum: ${data.release_title}\n\nDeseja carregar as ${data.tracklist.length} faixas na lista?`)) {
                        
                        const tbody = document.getElementById('tracklist-body');
                        tbody.innerHTML = ''; // Limpa a tabela atual

                        data.tracklist.forEach((track) => {
                            const row = `
                                <tr>
                                    <td>${track.numero_faixa}</td>
                                    <td contenteditable="true" class="editable-title">${track.titulo}</td>
                                    <td contenteditable="true" class="editable-duration">${track.duracao || ''}</td>
                                    <td>
                                        <button type="button" class="btn-action secondary-action btn-sm remove-track">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });
                    }
                } else {
                    alert("Atenção: " + (data.message || "Não foi possível encontrar este álbum no Discogs."));
                }
            } catch (error) {
                console.error("Erro na sincronização:", error);
                alert("Erro ao conectar com o script de importação.");
            } finally {
                btnImport.innerHTML = '<i class="fas fa-sync"></i> Sincronizar Discogs';
                btnImport.disabled = false;
            }
        });
    }

    // --- 2. ADICIONAR FAIXA MANUALMENTE ---
    const btnAddManual = document.getElementById('btn-add-manual');
    if (btnAddManual) {
        btnAddManual.addEventListener('click', () => {
            const tbody = document.getElementById('tracklist-body');
            const nextIndex = tbody.rows.length + 1;
            const row = `
                <tr>
                    <td>${nextIndex}</td>
                    <td contenteditable="true" class="editable-title">Nova Música</td>
                    <td contenteditable="true" class="editable-duration">0:00</td>
                    <td>
                        <button type="button" class="btn-action secondary-action btn-sm remove-track">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
    }

    // --- 3. REMOVER FAIXA (DELEGAÇÃO DE EVENTO) ---
    const tracklistBody = document.getElementById('tracklist-body');
    if (tracklistBody) {
        tracklistBody.addEventListener('click', function(e) {
            const btnRemove = e.target.closest('.remove-track');
            if (btnRemove) {
                if (confirm('Deseja remover esta faixa da lista?')) {
                    btnRemove.closest('tr').remove();
                    reordenarFaixas();
                }
            }
        });
    }

    function reordenarFaixas() {
        const rows = document.querySelectorAll('#tracklist-body tr');
        rows.forEach((row, index) => {
            row.cells[0].textContent = index + 1;
        });
    }

    // --- 4. SALVAMENTO FINAL (CONCLUIR E SALVAR) ---
    const btnSave = document.getElementById('btn-save-full-album');
    if (btnSave) {
        btnSave.addEventListener('click', async () => {
            
            const getVal = (id) => {
                const el = document.getElementById(id);
                return el ? el.value : null; 
            };

            const getSelectValues = (id) => {
                const el = document.getElementById(id);
                return el ? Array.from(el.selectedOptions).map(o => o.value) : [];
            };

            // Coleta todos os dados do formulário
            const payload = {
                colecao_id: getVal('colecao_id'),
                titulo: getVal('titulo'),
                gravadora_id: getVal('gravadora_id'),
                formato_id: getVal('formato_id'),
                numero_catalogo: getVal('numero_catalogo'),
                data_lancamento: getVal('data_lancamento'),
                data_aquisicao: getVal('data_aquisicao'),
                preco: getVal('preco'),
                observacoes: getVal('observacoes'),
                artistas: getSelectValues('artistas'),
                generos: getSelectValues('generos'),
                estilos: getSelectValues('estilos'),
                produtores: getSelectValues('produtores'),
                
                // Coleta as faixas da tabela dinamicamente
                tracks: Array.from(document.querySelectorAll('#tracklist-body tr')).map(row => ({
                    titulo: row.querySelector('.editable-title') ? row.querySelector('.editable-title').textContent.trim() : '',
                    duracao: row.querySelector('.editable-duration') ? row.querySelector('.editable-duration').textContent.trim() : ''
                }))
            };

            if (!payload.colecao_id || !payload.titulo) {
                alert("Erro: ID da coleção ou Título são obrigatórios!");
                return;
            }

            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';

            try {
                const res = await fetch('atualizar_album_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await res.json();
                if(result.success) {
                    alert("Jardim atualizado! Álbum salvo com sucesso.");
                    window.location.href = 'colecao.php'; 
                } else {
                    alert("Erro ao salvar: " + result.error);
                }
            } catch (e) {
                console.error("Erro no Fetch de salvamento:", e);
                alert("Falha de comunicação com o servidor.");
            } finally {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fas fa-check-circle"></i> CONCLUIR E SALVAR ALTERAÇÕES';
            }
        });
    }
});