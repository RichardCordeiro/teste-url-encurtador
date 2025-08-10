import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Contagem regressiva para expiração de links (atualiza a cada segundo)
function startCountdownTimers() {
  const elements = document.querySelectorAll('.js-countdown[data-expires-at]');
  if (!elements.length) return;

  const tick = async () => {
    const now = new Date();
    elements.forEach((el) => {
      const expiresAtIso = el.getAttribute('data-expires-at');
      if (!expiresAtIso) return;
      const expiresAt = new Date(expiresAtIso);
      const diffMs = Math.max(0, expiresAt.getTime() - now.getTime());
      const totalSeconds = Math.floor(diffMs / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      el.textContent = `${minutes}m ${String(seconds).padStart(2, '0')}s`;
      if (totalSeconds === 0) {
        const expireUrl = el.getAttribute('data-expire-url');
        if (expireUrl && !el.dataset.expiredNotified) {
          el.dataset.expiredNotified = '1';
          // Notifica o backend para marcar como expirado
          fetch(expireUrl, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
          }).catch(() => {});
          // Atualiza visualmente após expirar
          const row = el.closest('[data-link-row]') || document;
          const statusCell = row?.querySelector('[data-link-status]');
          if (statusCell) statusCell.textContent = 'expired';
        }
      }
    });
  };

  tick();
  setInterval(tick, 1000);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startCountdownTimers);
} else {
  startCountdownTimers();
}

// Polling simples para atualizar cliques/status a cada 3s
async function startLinksPolling() {
  const table = document.querySelector('[data-link-row]');
  if (!table) return;

  const pollUrl = document.querySelector('meta[name="links-poll-url"]')?.content || '/links/poll';

  const applyData = (data) => {
    const byId = new Map();
    data.links.forEach((l) => byId.set(String(l.id), l));

    document.querySelectorAll('[data-link-row]')?.forEach((row) => {
      const id = row.getAttribute('data-link-id');
      const record = id ? byId.get(String(id)) : null;
      if (!record) return;
      const statusCell = row.querySelector('[data-link-status]');
      const clicksCell = row.querySelector('[data-link-clicks]');
      if (statusCell) statusCell.textContent = record.status;
      if (clicksCell) clicksCell.textContent = record.click_count;
      const countdown = row.querySelector('.js-countdown[data-expires-at]');
      if (countdown && record.expires_at) {
        countdown.setAttribute('data-expires-at', record.expires_at);
      }
    });
  };

  const tick = async () => {
    try {
      const res = await fetch(pollUrl, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      applyData(json);
    } catch (_) {
      // ignora erros de rede
    }
  };

  tick();
  setInterval(tick, 3000);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startLinksPolling);
} else {
  startLinksPolling();
}

// Botão de copiar URL curta
function setupCopyButtons() {
  const onClick = async (ev) => {
    const btn = ev.currentTarget;
    const text = btn.getAttribute('data-copy-text');
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      const original = btn.textContent;
      btn.textContent = 'Copiado!';
      setTimeout(() => (btn.textContent = original), 1500);
    } catch (_) {
      // fallback silencioso
    }
  };

  document.querySelectorAll('[data-copy]')?.forEach((btn) => {
    btn.removeEventListener('click', onClick);
    btn.addEventListener('click', onClick);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', setupCopyButtons);
} else {
  setupCopyButtons();
}
