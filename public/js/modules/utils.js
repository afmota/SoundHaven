// /public/js/modules/utils.js
export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else if (k === 'html') node.innerHTML = v; // usar com cuidado
    else node.setAttribute(k, String(v));
  }
  (Array.isArray(children) ? children : [children]).forEach(c => {
    if (c == null) return;
    if (typeof c === 'string') node.appendChild(document.createTextNode(c));
    else node.appendChild(c);
  });
  return node;
}

export function sanitizeText(s) {
  if (s == null) return '';
  return String(s);
}

export function formatDateSqlToBR(sqlDate) {
  if (!sqlDate) return 'N/D';
  const d = new Date(sqlDate);
  if (Number.isNaN(d.getTime())) return 'N/D';
  return d.toLocaleDateString('pt-BR');
}

export function createIcon(classes = 'fas fa-spinner fa-spin') {
  const i = document.createElement('i');
  i.className = classes;
  return i;
}

export function createButton(text, opts = {}) {
  const btn = document.createElement('button');
  btn.type = opts.type || 'button';
  btn.className = opts.class || 'btn';
  btn.textContent = text;
  if (opts.onClick) btn.addEventListener('click', opts.onClick);
  return btn;
}
