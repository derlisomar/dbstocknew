(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js');

  /* Tema claro / oscuro */
  function setTheme(t) {
    root.setAttribute('data-theme', t);
    try { localStorage.setItem('dbstock-tema', t); } catch (e) {}
    var b = document.getElementById('tema');
    if (b) b.setAttribute('aria-label', t === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
  }
  var tb = document.getElementById('tema');
  if (tb) {
    setTheme(root.getAttribute('data-theme') || 'light');
    tb.addEventListener('click', function () {
      setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });
  }

  /* Cabecera con fondo al desplazar */
  var top = document.querySelector('.top');
  function onScroll() { if (top) top.classList.toggle('stuck', window.scrollY > 8); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* Menú en móvil */
  var bg = document.getElementById('menu'), nav = document.getElementById('nav');
  if (bg && nav) {
    bg.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      bg.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') { nav.classList.remove('open'); bg.setAttribute('aria-expanded', 'false'); }
    });
  }

  /* Aparición al desplazar */
  var items = [].slice.call(document.querySelectorAll('.rv'));
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('in'); });
  }

  /* Pestañas de capturas */
  var tabs = [].slice.call(document.querySelectorAll('.tab'));
  var imgs = [].slice.call(document.querySelectorAll('.panel-img'));
  var note = document.getElementById('stage-note');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
      imgs.forEach(function (im) { im.classList.toggle('on', im.id === t.getAttribute('aria-controls')); });
      if (note) note.textContent = t.getAttribute('data-note') || '';
    });
    t.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(t), n = null;
      if (e.key === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
      if (e.key === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
      if (n) { n.focus(); n.click(); e.preventDefault(); }
    });
  });

  /* Formulario de demo */
  var form = document.getElementById('form-demo');
  if (!form) return;
  var btn = form.querySelector('button[type=submit]');
  var alertBox = document.getElementById('demo-error');

  function limpiar() {
    [].forEach.call(form.querySelectorAll('.field'), function (f) {
      f.classList.remove('bad');
      var e = f.querySelector('.err'); if (e) e.textContent = '';
    });
    alertBox.classList.remove('show');
  }
  function marcar(campo, msg) {
    var inp = form.querySelector('[name="' + campo + '"]');
    if (!inp) return false;
    var f = inp.closest('.field'); f.classList.add('bad');
    var e = f.querySelector('.err'); if (e) e.textContent = msg;
    return true;
  }
  function mostrarError(msg) {
    alertBox.querySelector('span').textContent = msg;
    alertBox.classList.add('show');
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    limpiar();
    var data = new FormData(form);
    var mail = String(data.get('email') || '').trim();
    var bad = false;
    if (!String(data.get('nombre') || '').trim()) { marcar('nombre', 'Escribí tu nombre.'); bad = true; }
    if (!String(data.get('negocio') || '').trim()) { marcar('negocio', 'Escribí el nombre de tu negocio.'); bad = true; }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) { marcar('email', 'Revisá el correo, parece incompleto.'); bad = true; }
    if (bad) { var first = form.querySelector('.field.bad input'); if (first) first.focus(); return; }

    btn.disabled = true; btn.classList.add('loading');
    fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { return { s: r.status, j: j }; });
    }).then(function (res) {
      if (res.s === 422 && res.j.errors) {
        Object.keys(res.j.errors).forEach(function (k) { marcar(k, res.j.errors[k][0]); });
        return;
      }
      if (res.s === 429) { mostrarError('Hiciste varios intentos seguidos. Esperá un rato y probá de nuevo.'); return; }
      if (res.s >= 200 && res.s < 300 && res.j.ok) {
        form.classList.add('sent');
        var d = document.getElementById('demo-ok');
        d.querySelector('[data-email]').textContent = mail;
        d.classList.add('show'); d.setAttribute('tabindex', '-1'); d.focus({ preventScroll: true });
        return;
      }
      mostrarError(res.j.message || 'No pudimos procesar el pedido. Probá de nuevo en unos minutos.');
    }).catch(function () {
      mostrarError('No hay conexión con el servidor. Revisá tu internet y probá de nuevo.');
    }).then(function () { btn.disabled = false; btn.classList.remove('loading'); });
  });
})();
