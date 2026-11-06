const Cart = {
  key: 'echo_cart_v1',
  wa: '5491155550000',

  items() {
    try {
      return JSON.parse(localStorage.getItem(this.key) || '[]');
    } catch (e) {
      return [];
    }
  },

  save(items) {
    localStorage.setItem(this.key, JSON.stringify(items));
    this.ui();
  },

  add(id, nombre, precioTxt, img) {
    const items = this.items();
    const found = items.find(i => i.id === id);
    if (found) found.qty += 1;
    else items.push({ id, nombre, precio: precioTxt, img, qty: 1 });
    this.save(items);
    this.toast('Agregado: ' + nombre);
  },

  remove(id) {
    this.save(this.items().filter(i => i.id !== id));
  },

  change(id, delta) {
    const items = this.items();
    const found = items.find(i => i.id === id);
    if (!found) return;
    found.qty += delta;
    if (found.qty <= 0) this.remove(id);
    else this.save(items);
  },

  count() {
    return this.items().reduce((a, i) => a + i.qty, 0);
  },

  render() {
    const wrap = document.querySelector('.drawer-body');
    if (!wrap) return;
    const items = this.items();
    if (!items.length) {
      wrap.innerHTML = '<div class="empty-hint">Tu carrito está vacío<br>😶‍🌫️</div>';
    } else {
      wrap.innerHTML = items.map(i => `
        <div class="cart-item">
          <img src="${i.img}" alt="">
          <div>
            <b>${i.nombre}</b>
            <div class="qty">
              <button onclick="Cart.change(${i.id}, -1)">−</button>
              <span>${i.qty}</span>
              <button onclick="Cart.change(${i.id}, 1)">+</button>
              <span style="margin-left:8px">${i.precio}</span>
            </div>
          </div>
          <button class="cart-remove" onclick="Cart.remove(${i.id})" title="Quitar">✕</button>
        </div>`).join('');
    }
    const foot = document.querySelector('.cart-total span');
    if (foot) foot.textContent = this.totalTxt();
  },

  totalTxt() {
    const items = this.items();
    const total = items.reduce((a, i) => {
      const n = parseInt(i.precio.replace(/[^0-9]/g, ''), 10) || 0;
      return a + n * i.qty;
    }, 0);
    return '$' + total.toLocaleString('es-AR');
  },

  checkout() {
    const items = this.items();
    if (!items.length) {
      this.toast('El carrito está vacío');
      return;
    }
    const lines = items.map(i => '• ' + i.nombre + ' x' + i.qty + ' (' + i.precio + ')').join('\n');
    const msg = '¡Hola! Quiero pedir:\n\n' + lines + '\n\nTOTAL: ' + this.totalTxt() + '\n\nGracias 😀';
    window.open('https://wa.me/' + this.wa + '?text=' + encodeURIComponent(msg), '_blank');
  },

  ui() {
    const badge = document.querySelector('.cart-count');
    if (badge) badge.textContent = this.count();
    this.render();
  },

  abrir() {
    const d = document.querySelector('.drawer');
    if (d) {
      d.classList.add('abierto');
      this.render();
    }
  },

  cerrar() {
    const d = document.querySelector('.drawer');
    if (d) d.classList.remove('abierto');
  },

  toast(msg) {
    let t = document.querySelector('.cart-toast');
    if (!t) {
      t = document.createElement('div');
      t.className = 'cart-toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('visible');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('visible'), 1800);
  },

  init() {
    document.querySelectorAll('[data-open-cart]').forEach(b =>
      b.addEventListener('click', () => this.abrir()));
    const close = document.querySelector('.drawer-close');
    if (close) close.addEventListener('click', () => this.cerrar());
    const waBtn = document.querySelector('.drawer-checkout');
    if (waBtn) waBtn.addEventListener('click', () => this.checkout());
    document.querySelector('.drawer')?.addEventListener('click', e => {
      if (e.target === e.currentTarget) this.cerrar();
    });
    this.ui();
  },
};

document.addEventListener('DOMContentLoaded', () => Cart.init());