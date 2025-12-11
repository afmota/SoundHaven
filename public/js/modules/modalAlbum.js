// /public/js/modules/modalAlbum.js
import * as api from './api.js';
import * as u from './utils.js';

let importData = {}; // estado interno do módulo

// Seletores iniciais (assumem HTML com ids correspondentes)
const modalSelector = '#albumModal';
const detailsSelector = '#modal-details';
const loaderSelector = '#modal-loader';
const actionsSelector = '#modal-actions';
const messageAreaSelector = '#import-message-area';
const tracklistUlSelector = '#tracklist-ul';

function $(sel) { return document.querySelector(sel); }
function $all(sel) { return Array.from(document.querySelectorAll(sel)); }

function openModal() {
  const modal = $(modalSelector);
  if (!modal) return;
  modal.style.display = 'flex';
  $(detailsSelector).style.display = 'none';
  $(loaderSelector).style.display = '';
  modal.querySelector('.modal-content')?.classList.remove('loaded');
  clearModalDynamic();
}

function closeModal() {
  const modal = $(modalSelector);
  if (!modal) return;
  modal.style.display = 'none';
  $(detailsSelector).style.display = 'none';
  $(loaderSelector).style.display = '';
  modal.querySelector('.modal-content')?.classList.remove('loaded');
  clearModalDynamic();
}

function clearModalDynamic() {
  const rel = document.getElementById('modal-relacionamentos');
  const actions = document.getElementById('modal-actions');
  if (rel) rel.innerHTML = '';
  if (actions) actions.innerHTML = '';
  const tracklist = document.getElementById('tracklist-ul');
  if (tracklist) {
    tracklist.innerHTML = '';
    const placeholder = u.el('li', { id: 'tracklist-status', text: 'Carregando lista de faixas...' });
    tracklist.appendChild(placeholder);
  }
  const editWarn = document.getElementById('edit-warning');
  if (editWarn) editWarn.remove();
  const manualControls = document.getElementById('manual-edit-controls');
  if (manualControls) manualControls.style.display = 'none';
}

// Rendenriza relacionamentos simples
function renderRelationships(containerId, title, items = []) {
  const container = document.getElementById(containerId);
  if (!container) return;
  if (!items || items.length === 0) return;
  const p = u.el('p', {}, [
    u.el('strong', { text: title + ': ' }),
    items.join(', ')
  ]);
  container.appendChild(p);
}

// Rendenriza a lista de faixas no formato tabela (sem innerHTML perigoso)
function renderTrackTable(tracklist = []) {
  // criar a tabela via DOM
  const wrapper = u.el('div', { style: 'max-height:250px; overflow-y:auto; border:1px solid var(--cor-borda); padding:0; margin-top:15px; background-color:var(--cor-fundo-card);'});
  const table = u.el('table', { class: 'table table-sm table-borderless mb-0', style: 'width:100%; border-collapse:collapse;' });
  const thead = u.el('thead');
  const trHead = u.el('tr');
  ['#', 'Título', 'Duração', 'Ação'].forEach(t => {
    const th = u.el('th', { style: 'padding:8px; text-align:left; color:var(--cor-destaque);' , text: t});
    trHead.appendChild(th);
  });
  thead.appendChild(trHead);
  table.appendChild(thead);

  const tbody = u.el('tbody', { id: 'tracklist-table-body' });
  tracklist.forEach(t => {
    const tr = u.el('tr', { style: 'border-bottom:1px solid var(--cor-borda-tabela-linha); color:var(--cor-texto-principal);' });
    const tdNum = u.el('td', { class: 'track-number-cell', 'data-faixa-numero': t.numero_faixa, style: 'padding:8px;width:5%;', text: String(t.numero_faixa) });
    const tdTitle = u.el('td', { style: 'padding:8px;', text: t.titulo });
    const tdDur = u.el('td', { style: 'padding:8px;width:15%;', text: t.duracao || 'N/A' });
    const tdActions = u.el('td', { class: 'track-actions', style: 'padding:8px; text-align:center;' });

    tr.append(tdNum, tdTitle, tdDur, tdActions);
    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  wrapper.appendChild(table);
  return wrapper;
}

function showAlbumDetails(album, albumAtivo) {
  // capa, título e campos simples (usar textContent para evitar XSS)
  const img = document.getElementById('modal-capa-img');
  if (img) img.src = album.capa_url || '../assets/no-cover.png';
  const titulo = document.getElementById('modal-titulo'); if (titulo) titulo.textContent = album.titulo || 'Sem título';
  const artistas = document.getElementById('modal-artistas'); if (artistas) artistas.textContent = (album.relacionamentos?.artistas || []).join(', ') || 'N/A';
  const lanc = document.getElementById('modal-lancamento'); if (lanc) lanc.textContent = 'Lançamento: ' + (album.data_lancamento_formatada || 'N/A');
  const grav = document.getElementById('modal-gravadora'); if (grav) grav.textContent = 'Gravadora: ' + (album.gravadora_nome || 'N/A');
  const formato = document.getElementById('modal-formato'); if (formato) formato.textContent = album.formato_descricao || 'N/A';
  const aquis = document.getElementById('modal-aquisicao'); if (aquis) aquis.textContent = album.data_aquisicao_formatada || 'N/A';
  const preco = document.getElementById('modal-preco'); if (preco) preco.textContent = album.preco_formatado || 'N/A';
  const cond = document.getElementById('modal-condicao'); if (cond) cond.textContent = album.condicao || 'N/A';
  const cat = document.getElementById('modal-catalogo'); if (cat) cat.textContent = album.numero_catalogo || 'N/A';

  // observações (aceitar HTML somente se necessário; aqui usamos textContent e pre-wrap)
  const obs = document.getElementById('modal-observacoes');
  if (obs) {
    obs.innerHTML = ''; // limpar
    const h3 = u.el('h3', { text: 'Observações' });
    const p = u.el('p', { id: 'modal-obs-text' });
    p.style.whiteSpace = 'pre-wrap';
    p.textContent = album.observacoes ? decodeHTMLEntities(album.observacoes) : 'Nenhuma observação registrada.';
    obs.append(h3, p);
  }

  // relacionamentos
  const relContainer = document.getElementById('modal-relacionamentos');
  if (relContainer) relContainer.innerHTML = '';
  renderRelationships('modal-relacionamentos', 'Gêneros', album.relacionamentos?.generos);
  renderRelationships('modal-relacionamentos', 'Estilos', album.relacionamentos?.estilos);
  renderRelationships('modal-relacionamentos', 'Produtores', album.relacionamentos?.produtores);

  // tracklist (ul)
  const tracklistUl = document.getElementById('tracklist-ul');
  if (tracklistUl) {
    tracklistUl.innerHTML = '';
    if (album.faixas && album.faixas.length) {
      album.faixas.forEach(f => {
        const li = u.el('li', { text: `${f.numero_faixa}. ${f.titulo} (${f.duracao || 'N/A'})` });
        tracklistUl.appendChild(li);
      });
    } else {
      tracklistUl.appendChild(u.el('li', { id: 'tracklist-status', text: 'Nenhuma lista de faixas registrada.' }));
    }
  }

  // ações (Editar / Remover / Importar)
  const actions = document.getElementById('modal-actions');
  if (!actions) return;
  actions.innerHTML = ''; // limpar
  if (albumAtivo == 1) {
    const catalogo = album.numero_catalogo || '';
    if (catalogo.trim() && catalogo.trim() !== 'N/A') {
      const importBtn = u.el('button', { id: 'btn-importar-faixas', class: 'action-icon' }, [
        u.el('i', { class: 'fas fa-music' }),
        ` Importar Faixas (Discogs)`
      ]);
      importBtn.dataset.colecaoId = album.id;
      importBtn.dataset.catalogo = catalogo;
      importBtn.dataset.titulo = album.titulo;
      actions.appendChild(importBtn);
    } else {
      actions.appendChild(u.el('span', { text: 'Preencha o Nº Catálogo para importar faixas.', style: 'color:var(--cor-texto-secundario); margin-right:10px;' }));
    }

    const editLink = u.el('a', { href: `editar_colecao.php?id=${album.id}`, class: 'edit action-icon', text: ' Editar' });
    const delLink = u.el('a', { href: `excluir_colecao.php?id=${album.id}`, class: 'delete action-icon', text: ' Remover' });
    delLink.addEventListener('click', (ev) => {
      if (!confirm(`Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?`)) ev.preventDefault();
    });

    actions.append(editLink, delLink);
  } else {
    const restore = u.el('a', { href: `restaurar_colecao.php?id=${album.id}`, class: 'restore action-icon', text: ' Restaurar Item' });
    restore.addEventListener('click', (ev) => {
      if (!confirm('Tem certeza que deseja RESTAURAR este item para a sua Coleção?')) ev.preventDefault();
    });
    actions.appendChild(restore);
  }
}

// decode de entidades HTML simples (quando necessário)
function decodeHTMLEntities(text) {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = text;
  return textarea.value;
}

/* ---------- EDITING (manual) ---------- */
function renumberTracksInTable() {
  const rows = document.querySelectorAll('#tracklist-table-body tr');
  let count = 1;
  rows.forEach(r => {
    const numCell = r.querySelector('.track-number-cell');
    if (numCell) {
      numCell.textContent = String(count);
      numCell.dataset.faixaNumero = String(count);
    }
    count++;
  });

  const warn = document.getElementById('edit-warning');
  if (warn) warn.textContent = 'Números de faixas atualizados. Não se esqueça de salvar.';
  setTimeout(() => {
    if (warn) warn.textContent = 'Modo de edição ativo. Use "Adicionar Faixa" e "Renumerar" conforme necessário.';
  }, 3000);
}

function enableManualEditingControls() {
  // tornar colunas editáveis
  const rows = document.querySelectorAll('#tracklist-table-body tr');
  rows.forEach(r => {
    const titleTd = r.children[1];
    const durTd = r.children[2];
    if (titleTd) { titleTd.contentEditable = 'true'; titleTd.classList.add('editable-cell'); }
    if (durTd) { durTd.contentEditable = 'true'; durTd.classList.add('editable-cell'); }

    const actionsTd = r.querySelector('.track-actions');
    if (actionsTd && actionsTd.children.length === 0) {
      const btn = u.el('button', { class: 'btn-remove-track', title: 'Remover Faixa', type: 'button' });
      btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
      btn.addEventListener('click', () => {
        if (confirm('Tem certeza que deseja remover esta faixa?')) {
          r.remove();
          renumberTracksInTable();
        }
      });
      actionsTd.appendChild(btn);
    }
  });

  const manualControls = document.getElementById('manual-edit-controls');
  if (manualControls) manualControls.style.display = '';

  const editWarning = document.getElementById('edit-warning');
  if (!editWarning) {
    const area = document.getElementById('import-message-area');
    const div = u.el('div', { id: 'edit-warning', class: 'alert alert-info mt-2', text: 'Modo de edição ativo. Use "Adicionar Faixa" e "Renumerar" conforme necessário.' });
    area.appendChild(div);
  }
}

function attachManualButtons() {
  // btn-add-track
  const addBtn = document.getElementById('btn-add-track');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const tbody = document.getElementById('tracklist-table-body');
      if (!tbody) return;
      const tr = u.el('tr', { style: 'border-bottom:1px solid var(--cor-borda-tabela-linha); color:var(--cor-texto-principal);' });
      const tdNum = u.el('td', { class: 'track-number-cell', 'data-faixa-numero': '0', style: 'padding:8px;width:5%;', text: '0' });
      const tdTitle = u.el('td', { style: 'padding:8px;', text: 'NOVA FAIXA' }); tdTitle.contentEditable = 'true';
      const tdDur = u.el('td', { style: 'padding:8px;width:15%;', text: '0:00' }); tdDur.contentEditable = 'true';
      const tdActions = u.el('td', { class: 'track-actions', style: 'padding:8px;text-align:center;' });
      const remBtn = u.el('button', { class: 'btn-remove-track', type: 'button' }); remBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
      remBtn.addEventListener('click', () => { if (confirm('Tem certeza que deseja remover esta faixa?')) { tr.remove(); renumberTracksInTable(); } });
      tdActions.appendChild(remBtn);
      tr.append(tdNum, tdTitle, tdDur, tdActions);
      tbody.appendChild(tr);
      renumberTracksInTable();
    });
  }

  // btn-renumber-tracks
  const renumBtn = document.getElementById('btn-renumber-tracks');
  if (renumBtn) renumBtn.addEventListener('click', renumberTracksInTable);
}

async function handleImportButtonClick(ev) {
  const btn = ev.currentTarget;
  const colecaoId = btn.dataset.colecaoId;
  const catalogo = btn.dataset.catalogo || '';
  const titulo = btn.dataset.titulo || '';

  if (!colecaoId || catalogo.trim() === '' || catalogo.trim() === 'N/A') {
    alert('Erro: ID da Coleção não encontrado ou Número de Catálogo inválido.');
    return;
  }

  // UI: mensagem e botão temporário
  const messageArea = document.getElementById('import-message-area');
  messageArea.innerHTML = ''; // limpar

  const loader = u.el('div', { class: 'alert alert-info' }, [ u.createElement ? null : null ]);
  loader.appendChild(u.createIcon ? u.createIcon('fas fa-spinner fa-spin') : u.el('i', { class: 'fas fa-spinner fa-spin' }));
  loader.append(' Buscando faixas no Discogs. Por favor, aguarde...');
  messageArea.appendChild(loader);

  const actions = document.getElementById('modal-actions');
  actions.innerHTML = '';
  const tempClose = u.el('button', { id: 'temp-close-btn', class: 'btn' , text: 'Fechar' });
  tempClose.addEventListener('click', closeModal);
  actions.appendChild(tempClose);

  // chamada ao backend
  try {
    const response = await api.importTracks({ colecao_id: colecaoId, numero_catalogo: catalogo, titulo_album: titulo });

    if (response.success && response.action === 'confirm_tracks') {
      importData = {
        colecao_id: response.colecao_id,
        tracklist: response.tracklist,
        release_title: response.release_title || response.release_title
      };

      // montar HTML da lista de faixas (com tabela criada via DOM)
      const area = document.getElementById('import-message-area');
      area.innerHTML = '';
      area.appendChild(u.el('div', { class: 'alert alert-success', text: response.message }));
      area.appendChild(u.el('p', { text: `Lançamento encontrado: ${importData.release_title}` }));
      area.appendChild(u.el('p', { class: 'alert alert-warning p-2', text: `Confirma a importação das ${importData.tracklist.length} faixas abaixo? Isso substituirá a lista atual do álbum.` }));

      const tableWrapper = renderTrackTable(importData.tracklist);
      area.appendChild(tableWrapper);

      // botões: cancelar, editar, confirmar
      actions.innerHTML = '';
      const btnCancel = u.el('button', { id: 'btn-cancel-import', class: 'btn' , text: 'Cancelar' });
      btnCancel.addEventListener('click', closeModal);
      const btnEdit = u.el('button', { id: 'btn-edit-tracks', class: 'btn' , text: 'Editar Manualmente' });
      btnEdit.addEventListener('click', () => {
        enableManualEditingControls();
        attachManualButtons();
        // desabilitar o botão editar para indicar modo ativo
        btnEdit.disabled = true;
      });
      const btnConfirm = u.el('button', { id: 'btn-confirm-import', class: 'btn btn-primary' , text: 'Confirmar Importação' });
      btnConfirm.addEventListener('click', saveConfirmedTracks);

      actions.append(btnCancel, btnEdit, btnConfirm);

    } else if (response.success) {
      // resposta de sucesso final (salvamento concluído)
      messageArea.innerHTML = '';
      messageArea.appendChild(u.el('div', { class: 'alert alert-success', text: response.message }));
      actions.innerHTML = '';
      actions.appendChild(u.el('button', { class: 'btn', text: 'OK' }));
      setTimeout(() => { closeModal(); location.reload(); }, 1200);
    } else {
      // erro simples
      messageArea.innerHTML = '';
      messageArea.appendChild(u.el('div', { class: 'alert alert-danger', text: 'ERRO: ' + (response.message || 'Erro desconhecido') }));
      actions.innerHTML = '';
      actions.appendChild(u.el('button', { class: 'btn', text: 'Fechar' }));
    }

  } catch (err) {
    console.error('Erro importTracks:', err);
    messageArea.innerHTML = '';
    messageArea.appendChild(u.el('div', { class: 'alert alert-danger', text: 'Falha ao conectar ao servidor: ' + err.message }));
    actions.innerHTML = '';
    actions.appendChild(u.el('button', { class: 'btn', text: 'Fechar' }));
  }
}

async function saveConfirmedTracks() {
  // monta finalTracklist a partir da tabela (se edição ativa) ou usa importData.tracklist
  const isEditing = document.getElementById('btn-edit-tracks')?.disabled;
  let final = [];

  if (isEditing) {
    const rows = document.querySelectorAll('#tracklist-table-body tr');
    rows.forEach(r => {
      const numero = r.querySelector('.track-number-cell')?.textContent.trim() || '';
      const titulo = r.children[1]?.textContent.trim() || '';
      const dur = r.children[2]?.textContent.trim() || '';
      if (titulo) final.push({ numero_faixa: numero, titulo, duracao: (['N/A','0:00',''].includes(dur) ? null : dur) });
    });
    importData.tracklist = final;
  } else {
    final = importData.tracklist || [];
  }

  if (!importData.colecao_id || !importData.tracklist || importData.tracklist.length === 0) {
    document.getElementById('import-message-area').innerHTML = '';
    document.getElementById('import-message-area').appendChild(u.el('div', { class: 'alert alert-danger', text: 'Erro Interno: Dados de importação ou lista de faixas ausentes.' }));
    return;
  }

  // UI: informar usuário
  const confirmBtn = document.getElementById('btn-confirm-import');
  if (confirmBtn) confirmBtn.disabled = true;
  const actions = document.getElementById('modal-actions');
  if (actions) actions.innerHTML = '';
  actions.appendChild(u.el('span', { class: 'text-secondary ml-3', text: ' Salvando faixas... ' }));
  const area = document.getElementById('import-message-area');
  area.innerHTML = '';
  area.appendChild(u.el('div', { class: 'alert alert-info', text: `Salvando faixas de ${importData.release_title || 'o álbum'}... Por favor, aguarde a atualização.` }));

  try {
    const resp = await api.saveConfirmedTracks(importData);
    // reaproveita handle: se rota retorna action confirm_tracks ou success etc.
    if (resp && resp.success) {
      // chamar rotina de sucesso: aqui simplificada
      area.innerHTML = '';
      area.appendChild(u.el('div', { class: 'alert alert-success', text: resp.message || 'Faixas salvas.' }));
      actions.innerHTML = '';
      actions.appendChild(u.el('button', { class: 'btn', text: 'OK' }));
      setTimeout(() => { closeModal(); location.reload(); }, 1200);
    } else {
      throw new Error(resp.message || 'Erro ao salvar faixas.');
    }
  } catch (err) {
    console.error('Erro salvarConfirmedTracks:', err);
    area.innerHTML = '';
    area.appendChild(u.el('div', { class: 'alert alert-danger', text: 'Erro ao salvar: ' + err.message }));
    if (confirmBtn) confirmBtn.disabled = false;
  }
}

function init() {
  // evento delegado para abrir modal de álbum
  document.addEventListener('click', async (e) => {
    const card = e.target.closest('.colecao-item-card.open-modal');
    if (!card) return;

    e.preventDefault();
    openModal();

    // fechar artista modal se estiver aberto (compatibilidade)
    const artistaModal = document.getElementById('artistaModal');
    if (artistaModal && artistaModal.style.display === 'flex') {
      // assume que modalArtista exporta uma função close — porém apenas feche aqui
      artistaModal.style.display = 'none';
    }

    const albumId = card.dataset.albumId;
    const albumAtivo = card.dataset.ativo;

    try {
      const result = await api.getAlbumDetails(albumId);
      if (result.success && result.album) {
        showAlbumDetails(result.album, albumAtivo);
        document.getElementById('modal-loader').style.display = 'none';
        document.getElementById('modal-details').style.display = '';
        document.querySelector('#albumModal .modal-content')?.classList.add('loaded');
      } else {
        alert('Erro ao carregar detalhes: ' + (result.message || 'Resposta inválida.'));
        closeModal();
      }
    } catch (err) {
      console.error(err);
      alert('Falha ao conectar ao servidor para carregar os detalhes.');
      closeModal();
    }
  });

  // delegado para botão de importar (disparado quando renderizado)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#btn-importar-faixas');
    if (!btn) return;
    e.preventDefault();
    handleImportButtonClick({ currentTarget: btn });
  });

  // binds dos botões de modal (fechar, ESC)
  document.querySelectorAll(`${modalSelector} .modal-close`).forEach(btn => btn.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.querySelector(modalSelector)?.style.display === 'flex') closeModal();
  });
}

export default { init, openModal, closeModal };
