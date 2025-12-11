// /public/js/modules/api.js
const CSRF_META_NAME = 'csrf-token';

function getCsrfToken() {
  const meta = document.querySelector(`meta[name="${CSRF_META_NAME}"]`);
  return meta ? meta.getAttribute('content') : null;
}

async function request(url, options = {}) {
  const opts = {
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      ...options.headers
    },
    ...options
  };

  // Incluir token CSRF automaticamente para métodos não-GET
  if (opts.method && opts.method.toUpperCase() !== 'GET') {
    const token = getCsrfToken();
    if (token) {
      opts.headers['X-CSRF-Token'] = token;
    }
  }

  const res = await fetch(url, opts);
  const contentType = res.headers.get('content-type') || '';

  if (!res.ok) {
    // tenta extrair mensagem JSON, se existir
    if (contentType.includes('application/json')) {
      const payload = await res.json();
      throw new Error(payload.message || `HTTP ${res.status}`);
    }
    const text = await res.text();
    throw new Error(text || `HTTP ${res.status}`);
  }

  if (contentType.includes('application/json')) {
    return res.json();
  }

  return res.text();
}

export async function getAlbumDetails(id) {
  if (!id) throw new Error('id ausente');
  return request(`/colecao/fetch_album_details.php?id=${encodeURIComponent(id)}`, { method: 'GET' });
}

export async function fetchArtistHtml(id) {
  if (!id) throw new Error('id ausente');
  // Original devolvia HTML — manter compatibilidade
  return request(`/api/fetch_artista_details.php?id=${encodeURIComponent(id)}`, { method: 'GET' });
}

export async function importTracks(payload) {
  // payload: { colecao_id, numero_catalogo, titulo_album }
  return request('/colecao/importar_faixas_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
}

export async function saveConfirmedTracks(payload) {
  // payload: { colecao_id, release_title, tracklist: [...] }
  return request('/colecao/salvar_faixas_confirmadas.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
}
