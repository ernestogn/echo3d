const App = {
  init() {
    this.nav();
    this.backdrop();
    this.year();
    this.featured();
    this.filters();
    this.contacto();
    this.marquee();
  },

  nav() {
    const ham = document.querySelector('.hamburger');
    const links = document.querySelector('.nav-links');
    if (ham && links) {
      ham.addEventListener('click', () => links.classList.toggle('abierto'));
      links.addEventListener('click', e => {
        if (e.target.tagName === 'A') links.classList.remove('abierto');
      });
    }
  },

  backdrop() {
    const bd = document.querySelector('.drawer-backdrop');
    const drawer = document.querySelector('.drawer');
    if (!bd || !drawer) return;

    const observer = new MutationObserver(() => {
      bd.classList.toggle('visible', drawer.classList.contains('abierto'));
    });
    observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });
    bd.addEventListener('click', () => Cart.cerrar());
  },

  year() {
    document.querySelectorAll('[data-year]').forEach(el => el.textContent = new Date().getFullYear());
  },

  featured() {
    const load = async () => {
      Products.loader('#featured-grid');
      try {
        const rows = await Products.featured();
        Products.render(rows, '#featured-grid');
      } catch (e) {
        document.querySelector('#featured-grid').innerHTML = '<div class="empty-hint">No se pudieron cargar los productos</div>';
      }
    };
    if (document.querySelector('#featured-grid')) load();
  },

  filters() {
    const grid = document.querySelector('#catalog-grid');
    const filters = document.querySelector('.filters');
    if (!grid || !filters) return;

    const state = { cat: null };

    const load = async () => {
      Products.loader('#catalog-grid');
      try {
        const rows = state.cat ? await Products.byCategory(state.cat) : await Products.all();
        Products.render(rows, '#catalog-grid');
      } catch (e) {
        grid.innerHTML = '<div class="empty-hint">No se pudieron cargar los productos</div>';
      }
    };

    const build = async () => {
      filters.insertAdjacentHTML('afterbegin', '<button class="filter-btn activo" data-slug="">Todo</button>');
      try {
        const cats = await Products.categories();
        cats.forEach(c => {
          const b = document.createElement('button');
          b.className = 'filter-btn';
          b.dataset.slug = c.slug;
          b.textContent = c.icono + ' ' + c.nombre;
          filters.appendChild(b);
        });
      } catch (e) { /* sin categorias */ }

      filters.addEventListener('click', e => {
        const b = e.target.closest('.filter-btn');
        if (!b) return;
        filters.querySelectorAll('.filter-btn').forEach(x => x.classList.remove('activo'));
        b.classList.add('activo');
        state.cat = b.dataset.slug || null;
        load();
      });

      const inicial = window.__catInicial;
      if (inicial) {
        filters.querySelectorAll('.filter-btn').forEach(x => x.classList.remove('activo'));
        const b = filters.querySelector(`.filter-btn[data-slug="${CSS.escape(inicial)}"]`);
        if (b) {
          b.classList.add('activo');
          state.cat = inicial;
        }
      }

      load();
    };

    build();
  },

  contacto() {
    const form = document.querySelector('#contact-form');
    if (!form) return;

    form.addEventListener('submit', async e => {
      e.preventDefault();
      const msg = document.querySelector('.form-msg');
      const btn = form.querySelector('button[type=submit]');
      const old = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Enviando…';

      try {
        const fd = new FormData(form);
        const r = await fetch('api/contacto.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok) {
          msg.className = 'form-msg ok';
          msg.textContent = data.message;
          form.reset();
        } else {
          msg.className = 'form-msg err';
          msg.textContent = data.error || 'Falló el envío';
        }
      } catch (err) {
        msg.className = 'form-msg err';
        msg.textContent = 'Falló el envío. Escribinos por WhatsApp.';
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });
  },

  async marquee() {
    const wrap = document.querySelector('.marquee-wrap');
    if (!wrap) return;
    if (!window.WebGLRenderingContext) {
      wrap.innerHTML = '<div class="empty-hint" style="padding-top:60px">PLA · PETG · TPU · ABS · ASA · PC · NYLON · CARBON</div>';
      return;
    }

    let nombres = [];
    try {
      const data = await fetch('api/marcas.php').then(r => r.json());
      nombres = data.marcas || [];
    } catch (e) { /* empty */ }
    if (!nombres.length) nombres = ['PLA', 'PETG', 'TPU', 'ABS', 'ASA', 'PC', 'NYLON', 'CARBON'];

    const MM = window.MM || {};
    const palette = MM.PALETA || {
      petroleo: '#3f8995', naranja: '#e99619', crema: '#f1e9d4',
      amarillo: '#f4d98a', verde: '#c6d34b', oscuro: '#17363a',
    };

    if (!window.THREE) {
      try {
        await new Promise((res, rej) => {
          const s = document.createElement('script');
          s.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
          s.onload = res;
          s.onerror = rej;
          document.head.appendChild(s);
        });
      } catch (e) {
        wrap.innerHTML = '<div class="empty-hint" style="padding-top:60px">' + nombres.join(' · ') + '</div>';
        return;
      }
    }

    const canvas = document.createElement('canvas');
    wrap.appendChild(canvas);
    const W = wrap.clientWidth, H = 170;

    let renderer, scene, camera, group, rafId;
    try {
      renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    } catch (e) {
      wrap.innerHTML = '<div class="empty-hint" style="padding-top:60px">' + nombres.join(' · ') + '</div>';
      return;
    }

    const resize = () => {
      const w = wrap.clientWidth || W;
      const h = wrap.clientHeight || H;
      renderer.setSize(w, h, false);
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
    };

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(45, W / H, 0.1, 1000);
    camera.position.z = 6;
    group = new THREE.Group();
    scene.add(group);

    const fontMap = {
      PLA: '--amarillo', PETG: '--verde', TPU: '--naranja', ABS: '--petroleo',
      ASA: '--crema', PC: '--verde', NYLON: '--naranja', CARBON: '--amarillo',
    };

    const makeTex = (txt) => {
      const cv = document.createElement('canvas');
      cv.width = 512; cv.height = 256;
      const ctx = cv.getContext('2d');
      ctx.fillStyle = palette.oscuro;
      ctx.fillRect(0, 0, cv.width, cv.height);
      ctx.strokeStyle = palette.crema;
      ctx.lineWidth = 10;
      ctx.beginPath();
      ctx.roundRect(20, 20, cv.width - 40, cv.height - 40, 40);
      ctx.stroke();
      ctx.fillStyle = palette[fontMap[txt]?.slice(2) || 'naranja'] || palette.naranja;
      ctx.font = '900 84px Trebuchet MS';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(txt, cv.width / 2, cv.height / 2);
      const t = new THREE.CanvasTexture(cv);
      t.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);
      return t;
    };

    const spacing = 3.4;
    nombres.forEach((nom, i) => {
      const tex = makeTex(nom);
      const geo = new THREE.BoxGeometry(3, 1.7, 0.12);
      const mat = new THREE.MeshLambertMaterial({ map: tex });
      const mesh = new THREE.Mesh(geo, mat);
      mesh.position.x = (i - (nombres.length - 1) / 2) * spacing;
      mesh.position.y = (Math.random() - 0.5) * 0.3;
      group.add(mesh);
    });

    scene.add(new THREE.AmbientLight(0xffffff, 0.9));
    const dir = new THREE.DirectionalLight(0xffffff, 0.6);
    dir.position.set(2, 4, 5);
    scene.add(dir);

    const totalW = nombres.length * spacing;
    const speed = 1.1;

    const animate = () => {
      group.position.x += speed * 0.0016;
      if (group.position.x > totalW / 2 + 2) group.position.x = -totalW / 2 - 2;
      group.rotation.y = Math.sin(new Date().getTime() * 0.0006) * 0.12;
      renderer.render(scene, camera);
      rafId = requestAnimationFrame(animate);
    };

    resize();
    window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', () => {
      if (document.hidden && rafId) { cancelAnimationFrame(rafId); rafId = null; }
      else if (!document.hidden && !rafId) animate();
    });
    animate();
  },
};

document.addEventListener('DOMContentLoaded', () => App.init());