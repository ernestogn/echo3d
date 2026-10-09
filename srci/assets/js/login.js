// Logica del teclado numerico de PIN
const pin = [];
const MAX_PIN = 4;

const campoPin    = document.getElementById('campo-pin');
const campoNombre = document.getElementById('campo-nombre');
const nombreVis   = document.getElementById('nombre-visible');
const btnEnviar   = document.getElementById('btn-enviar');

function actualizarDisplay() {
  for (let i = 0; i < MAX_PIN; i++) {
    const punto = document.getElementById('pt' + i);
    punto.classList.toggle('lleno', i < pin.length);
  }
  btnEnviar.disabled = pin.length < MAX_PIN || nombreVis.value.trim() === '';
}

document.getElementById('pin-teclado').addEventListener('click', (e) => {
  const btn = e.target.closest('.pin-digito');
  if (!btn || btn.id === 'btn-enviar') return;

  if (btn.id === 'btn-borrar') {
    pin.pop();
  } else if (pin.length < MAX_PIN) {
    pin.push(btn.dataset.digito);
  }
  actualizarDisplay();
});

nombreVis.addEventListener('input', actualizarDisplay);

document.getElementById('form-login').addEventListener('submit', (e) => {
  if (pin.length !== MAX_PIN || nombreVis.value.trim() === '') {
    e.preventDefault();
    return;
  }
  campoNombre.value = nombreVis.value.trim();
  campoPin.value    = pin.join('');
});

actualizarDisplay();
