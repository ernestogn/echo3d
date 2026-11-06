const Products = {
  async fetch(url) {
    const r = await fetch(url);
    if (!r.ok) throw new Error('Error de red');
    return r.json();
  },
  featured() { return this.fetch('api/productos.php?destacados=1'); },
  all() { return this.fetch('api/productos.php'); },
  byCategory(slug) { return this.fetch('api/productos.php?categoria=' + encodeURIComponent(slug)); },
  categories() { return this.fetch('api/categorias.php'); },

  card(p) {
    return `
      <article class="prod-card" data-id="${p.id}">
        <div class="prod-img"><img src="${p.imagen_url}" alt="${p.nombre}" loading="lazy"></div>
        <div class="prod-body">
          <span class="prod-cat">${p.categoria || ''}</span>
          <h3 class="prod-name">${p.nombre}</h3>
          <p class="prod-desc">${p.descripcion}</p>
          <div class="prod-foot">
            <span class="prod-price">${p.precio_txt}</span>
            <button class="btn-add" onclick="Cart.add(${p.id}, '${p.nombre.replace(/'/g, "\\'")}', '${p.precio_txt}', '${p.imagen_url}')">Añadir +</button>
          </div>
        </div>
      </article>`;
  },

  render(rows, cont) {
    const el = document.querySelector(cont);
    if (!el) return;
    if (!rows || !rows.length) {
      el.innerHTML = '<div class="empty-hint">Sin productos por ahora</div>';
      return;
    }
    el.innerHTML = rows.map(p => this.card(p)).join('');
  },

  loader(cont) {
    const el = document.querySelector(cont);
    if (el) el.innerHTML = '<div class="loader"><i></i><i></i><i></i></div>';
  },
};