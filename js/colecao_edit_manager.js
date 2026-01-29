// js/colecao_edit_manager.js
document.addEventListener('DOMContentLoaded', () => {
    const tracklistBody = document.getElementById('tracklist-body');
    const btnSave = document.getElementById('btn-save-full-album');
    const btnImport = document.getElementById('btn-import-tracks');
    const albumId = document.getElementById('colecao_id').value;

    // Helper para pegar valores simples
    const getVal = (id) => document.getElementById(id)?.value || '';
    
    // Helper para pegar valores do Select2 (múltiplos ou tags)
    const getSelectValues = (id) => {
        const el = $(`#${id}`);
        return el.val() || [];
    };

    // --- LÓGICA DE IMPORTAÇÃO (DISCOGS via importar_faixas_api.php) ---
    btnImport?.addEventListener('click', async () => {
        const catNo = getVal('numero_catalogo');
        const titulo = getVal('titulo'); // Pega o título para ajudar no refinamento da busca

        if (!catNo) {
            alert("Por favor, digite o Número de Catálogo antes de sincronizar.");
            return;
        }

        btnImport.disabled = true;
        btnImport.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            // Enviamos via POST para o importar_faixas_api.php, conforme a lógica dele
            const response = await fetch('importar_faixas_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    numero_catalogo: catNo,
                    titulo: titulo,
                    colecao_id: albumId
                })
            });

            const data = await response.json();

            if (data.success && data.tracklist) {
                // Limpa a tabela atual para inserir as faixas importadas
                tracklistBody.innerHTML = '';
                
                data.tracklist.forEach((track) => {
                    const row = `
                        <tr>
                            <td class="track-num text-center text-info font-weight-bold">${track.numero_faixa}</td>
                            <td contenteditable="true" class="editable-cell editable-title">${track.titulo}</td>
                            <td contenteditable="true" class="editable-cell editable-duration">${track.duracao || '--:--'}</td>
                            <td class="text-center"><i class="fas fa-times btn-remove text-danger" style="cursor:pointer"></i></td>
                        </tr>`;
                    tracklistBody.insertAdjacentHTML('beforeend', row);
                });
                
                // Opcional: Avisa qual álbum foi encontrado para o usuário conferir
                console.log("Sincronizado com: " + data.release_title);
                alert("Tracklist de '" + data.release_title + "' importada com sucesso!");
                
            } else {
                alert(data.message || "Nenhum resultado encontrado no Discogs.");
            }
        } catch (error) {
            console.error("Erro na sincronização:", error);
            alert("Erro ao conectar com a API de importação.");
        } finally {
            btnImport.disabled = false;
            btnImport.innerHTML = '<i class="fas fa-sync"></i>';
        }
    });

    // --- LÓGICA DA TABELA DE FAIXAS (TRACKLIST) ---
    
    // Adicionar faixa manualmente
    document.getElementById('btn-add-manual')?.addEventListener('click', () => {
        const nextNum = tracklistBody.rows.length + 1;
        const row = `
            <tr>
                <td class="track-num text-center text-info font-weight-bold">${nextNum}</td>
                <td contenteditable="true" class="editable-cell editable-title">Nova Faixa</td>
                <td contenteditable="true" class="editable-cell editable-duration">0:00</td>
                <td class="text-center"><i class="fas fa-times btn-remove text-danger" style="cursor:pointer"></i></td>
            </tr>`;
        tracklistBody.insertAdjacentHTML('beforeend', row);
    });

    // Remover faixa e reordenar números
    tracklistBody?.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove')) {
            if (confirm('Deseja remover esta faixa?')) {
                e.target.closest('tr').remove();
                // Re-numera as faixas após a remoção
                Array.from(tracklistBody.querySelectorAll('.track-num')).forEach((td, i) => {
                    td.textContent = i + 1;
                });
            }
        }
    });

    // --- AÇÃO DE SALVAR (UPDATE) ---
    btnSave?.addEventListener('click', async (e) => {
        e.preventDefault();

        if (btnSave.disabled) return;

        const payload = {
            colecao_id: albumId,
            titulo: getVal('titulo'),
            capa_url: getVal('capa_url'),
            gravadora_id: $('#gravadora_id').val(),
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
            tracks: Array.from(tracklistBody.querySelectorAll('tr')).map((row, index) => ({
                posicao: index + 1,
                titulo: row.querySelector('.editable-title')?.textContent.trim() || '',
                duracao: row.querySelector('.editable-duration')?.textContent.trim() || ''
            }))
        };

        if (!payload.titulo) {
            alert("O título do álbum é obrigatório!");
            return;
        }

        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ATUALIZANDO...';

        try {
            const response = await fetch('atualizar_album_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                alert("Álbum atualizado com sucesso!");
                window.location.href = 'colecao.php';
            } else {
                throw new Error(result.error || "Erro desconhecido ao atualizar.");
            }
        } catch (error) {
            alert("Falha: " + error.message);
            btnSave.disabled = false;
            btnSave.innerHTML = 'SALVAR ALTERAÇÕES';
        }
    });
});