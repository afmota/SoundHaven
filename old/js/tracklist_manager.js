document.getElementById('btn-save-full-album').addEventListener('click', async () => {
    
    // Função auxiliar para evitar o erro de "null"
    const getVal = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : null; 
    };

    // Função para pegar valores de selects múltiplos (Select2)
    const getSelectValues = (id) => {
        const el = document.getElementById(id);
        return el ? Array.from(el.selectedOptions).map(o => o.value) : [];
    };

    // COLETA DOS DADOS (Ajustado para não dar erro se o campo faltar)
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
        
        // Coleta as faixas da tabela
        tracks: Array.from(document.querySelectorAll('#tracklist-body tr')).map(row => ({
            titulo: row.querySelector('.editable-title') ? row.querySelector('.editable-title').textContent.trim() : '',
            duracao: row.querySelector('.editable-duration') ? row.querySelector('.editable-duration').textContent.trim() : ''
        }))
    };

    // Verificação de segurança
    if (!payload.colecao_id || !payload.titulo) {
        alert("Erro: ID da coleção ou Título não encontrados!");
        return;
    }

    try {
        const res = await fetch('atualizar_album_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await res.json();
        if(result.success) {
            alert("Álbum editado e salvo com sucesso!");
            window.location.reload(); // Recarrega para limpar as pendências
        } else {
            alert("Erro no servidor: " + result.error);
        }
    } catch (e) {
        console.error("Erro no Fetch:", e);
        alert("Falha ao comunicar com o servidor.");
    }
});