/**
 * SoundHaven - Tracklist Manager (Versão Consolidada)
 * Gerencia: Sincronização Discogs, Adição Manual, Remoção e Salvamento Inteligente (Insert/Update).
 */

document.addEventListener('DOMContentLoaded', () => {

    // --- 1. SINCRONIZAÇÃO COM O DISCOGS ---
    const btnImport = document.getElementById('btn-import-discogs');
    if (btnImport) {
        btnImport.addEventListener('click', async () => {
            const catNo = document.getElementById('numero_catalogo').value;
            // No caso de inserção, o colecaoId pode ser 0, mas a API precisa do Catálogo
            if (!catNo) {
                alert("Por favor, digite o Número de Catálogo primeiro.");
                return;
            }

            btnImport.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            btnImport.disabled = true;

            const formData = new FormData();
            formData.append('numero_catalogo', catNo);

            try {
                const response = await fetch('importar_faixas_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.tracklist) {
                    if (confirm(`Encontramos: ${data.release_title}\nCarregar ${data.tracklist.length} faixas?`)) {
                        const tbody = document.getElementById('tracklist-body');
                        tbody.innerHTML = ''; 

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
                    alert("Aviso: " + (data.message || "Álbum não encontrado no Discogs."));
                }
            } catch (error) {
                console.error("Erro Discogs:", error);
                alert("Erro ao conectar com a API de importação.");
            } finally {
                btnImport.innerHTML = '<i class="fas fa-sync"></i> Sincronizar Discogs';
                btnImport.disabled = false;
            }
        });
    }

    // --- 2. GESTÃO MANUAL DA TABELA (ADICIONAR/REMOVER) ---
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

    const tracklistBody = document.getElementById('tracklist-body');
    if (tracklistBody) {
        tracklistBody.addEventListener('click', (e) => {
            const btnRemove = e.target.closest('.remove-track');
            if (btnRemove) {
                if (confirm('Remover esta faixa?')) {
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

    // --- 3. SALVAMENTO (LOGICA DUPLA: INSERT OU UPDATE) ---
    const btnSave = document.getElementById('btn-save-full-album');
    if (btnSave) {
        btnSave.addEventListener('click', async () => {
            
            const getVal = (id) => document.getElementById(id)?.value || '';
            const getSelectValues = (id) => {
                const el = document.getElementById(id);
                return el ? Array.from(el.selectedOptions).map(o => o.value) : [];
            };

            const colecaoId = getVal('colecao_id');
            // Se colecaoId for "0" ou vazio, é um novo registro (adicionar_colecao.php)
            const isNew = (colecaoId === "0" || colecaoId === "");
            const endpoint = isNew ? 'inserir_album_action.php' : 'atualizar_album_action.php';

            const payload = {
                colecao_id: colecaoId,
                store_id: getVal('store_id'), // Importante para marcar como 'Adquirido' na loja
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
                tracks: Array.from(document.querySelectorAll('#tracklist-body tr')).map(row => ({
                    titulo: row.querySelector('.editable-title')?.textContent.trim() || '',
                    duracao: row.querySelector('.editable-duration')?.textContent.trim() || ''
                }))
            };

            if (!payload.titulo) {
                alert("O título do álbum é obrigatório!");
                return;
            }

            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await res.json();
                if(result.success) {
                    alert(isNew ? "Álbum adicionado com sucesso!" : "Alterações salvas!");
                    window.location.href = 'colecao.php'; 
                } else {
                    alert("Erro ao processar: " + result.error);
                }
            } catch (e) {
                console.error("Erro Fetch:", e);
                alert("Falha de comunicação com o servidor.");
            } finally {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fas fa-save"></i> SALVAR NA COLEÇÃO';
            }
        });
    }
});