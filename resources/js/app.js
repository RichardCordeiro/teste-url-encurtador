import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

function startCountdownTimers() {
  const elements = document.querySelectorAll('.js-countdown[data-expires-at]');
  if (!elements.length) return;

  const findContainerWithStatus = (fromEl) => {
    let node = fromEl;
    while (node && node !== document.documentElement) {
      if (node.querySelector && node.querySelector('[data-link-status]')) {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  };

  const applyStatusVisualUpdate = (fromEl, newStatus) => {
    const container = fromEl.closest('[data-link-row]') || findContainerWithStatus(fromEl);
    if (!container) return;
    const statusEl = container.querySelector('[data-link-status]');
    if (!statusEl) return;

    statusEl.textContent = newStatus;

    const statusClasses = {
      active: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
      expired: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
      inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    };
    const allKnownClasses = Object.values(statusClasses)
      .join(' ')
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (allKnownClasses.length) {
      statusEl.classList.remove(...allKnownClasses);
    }
    if (statusClasses[newStatus]) {
      statusEl.classList.add(...statusClasses[newStatus].split(/\s+/));
    }
  };

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

      if (totalSeconds === 0 && !el.dataset.expiredNotified) {
        el.dataset.expiredNotified = '1';
        const expireUrl = el.getAttribute('data-expire-url');
        if (expireUrl) {
          const requestExpireWithRetry = (attempt = 0) => {
            fetch(expireUrl, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
              },
            })
              .then(async (res) => {
                let data = null;
                try {
                  data = await res.json();
                } catch (e) {}
                const newStatus = data && typeof data.status === 'string' ? data.status : null;
                if (newStatus === 'expired') {
                  applyStatusVisualUpdate(el, 'expired');
                } else if (attempt < 6) {
                  setTimeout(() => requestExpireWithRetry(attempt + 1), 800);
                }
              })
              .catch(() => {
                if (attempt < 3) {
                  setTimeout(() => requestExpireWithRetry(attempt + 1), 1200);
                }
              });
          };
          setTimeout(() => requestExpireWithRetry(0), 600);
        }
      }
    });
  };

  tick();
  setInterval(tick, 1000);
}

async function startLinksPolling() {
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
    } catch (_) {}
  };

  tick();
  setInterval(tick, 3000);
}

function startQrCodeToggles() {
  const handleClick = async (event) => {
    const button = event.target.closest('[data-qrcode-toggle]');
    if (!button) return;
    event.preventDefault();

    let scope = button.closest('td');
    if (!scope) scope = button.parentElement;
    const container = scope ? scope.querySelector('[data-qrcode-container]') : null;
    if (!container) return;

    const canvas = container.querySelector('[data-qrcode-canvas]');
    const url = button.getAttribute('data-qrcode-url');
    const isHidden = container.classList.contains('hidden');

    if (isHidden) {
      container.classList.remove('hidden');
      if (canvas && !canvas.dataset.loaded && url) {
        try {
          canvas.textContent = 'Carregando...';
          const res = await fetch(url, { headers: { 'Accept': 'image/svg+xml' } });
          const svg = await res.text();
          canvas.innerHTML = svg;
          canvas.dataset.loaded = '1';
        } catch (e) {
          canvas.textContent = 'Erro ao carregar QR Code';
        }
      }
    } else {
      container.classList.add('hidden');
    }
  };

  document.addEventListener('click', handleClick);
}

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
    } catch (_) {}
  };

  document.querySelectorAll('[data-copy]')?.forEach((btn) => {
    btn.removeEventListener('click', onClick);
    btn.addEventListener('click', onClick);
  });
}

function startDashboardMetrics() {
  const totalEl = document.getElementById('metric-total-links');
  const activeEl = document.getElementById('metric-active-links');
  const expiredEl = document.getElementById('metric-expired-links');
  const clicksEl = document.getElementById('metric-clicks-period');
  const topTbody = document.getElementById('metric-top-links');
  const periodSelect = document.getElementById('metric-period');
  if (!totalEl || !activeEl || !expiredEl || !clicksEl || !topTbody || !periodSelect) return;
  const summaryUrl = document.querySelector('meta[name="metrics-summary-url"]')?.content || '/metrics/summary';
  const topUrlBase = document.querySelector('meta[name="metrics-top-url"]')?.content || '/metrics/top';
  const renderSummary = (json) => {
    if (!json) return;
    if (typeof json.total_links === 'number') totalEl.textContent = String(json.total_links);
    if (typeof json.active_links === 'number') activeEl.textContent = String(json.active_links);
    if (typeof json.expired_links === 'number') expiredEl.textContent = String(json.expired_links);
    if (typeof json.total_clicks_in_period === 'number') clicksEl.textContent = String(json.total_clicks_in_period);
  };
  const renderTop = (json) => {
    topTbody.innerHTML = '';
    const rows = Array.isArray(json?.top) ? json.top : [];
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 4;
      td.className = 'py-2 pr-4 text-gray-500';
      td.textContent = 'Sem dados ainda';
      tr.appendChild(td);
      topTbody.appendChild(tr);
      return;
    }
    rows.forEach((r) => {
      const tr = document.createElement('tr');
      const tdSlug = document.createElement('td');
      tdSlug.className = 'py-2 pr-4';
      const a = document.createElement('a');
      a.href = `/s/${encodeURIComponent(r.slug)}`;
      a.target = '_blank';
      a.className = 'text-blue-600 dark:text-blue-400 underline';
      a.textContent = r.slug || '-';
      tdSlug.appendChild(a);
      const tdOriginal = document.createElement('td');
      tdOriginal.className = 'py-2 pr-4 max-w-[28rem]';
      const divOrig = document.createElement('div');
      divOrig.className = 'truncate';
      divOrig.title = r.original_url || '';
      divOrig.textContent = r.original_url || '';
      tdOriginal.appendChild(divOrig);
      const tdClicks = document.createElement('td');
      tdClicks.className = 'py-2 pr-4 text-right';
      tdClicks.textContent = String(r.clicks ?? 0);
      const tdActions = document.createElement('td');
      tdActions.className = 'py-2 pl-4 text-right';
      const wrapper = document.createElement('div');
      wrapper.className = 'flex gap-2 justify-end';
      const btnCopy = document.createElement('button');
      btnCopy.type = 'button';
      btnCopy.setAttribute('data-copy', '');
      btnCopy.setAttribute('data-copy-text', `${window.location.origin}/s/${r.slug}`);
      btnCopy.className = 'inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs hover:bg-gray-200 dark:hover:bg-gray-600';
      btnCopy.textContent = 'Copiar';
      const linkOpen = document.createElement('a');
      linkOpen.href = `/s/${encodeURIComponent(r.slug)}`;
      linkOpen.target = '_blank';
      linkOpen.className = 'inline-flex items-center rounded-md bg-blue-600 text-white px-2 py-1 text-xs hover:bg-blue-700';
      linkOpen.textContent = 'Abrir';
      wrapper.appendChild(btnCopy);
      wrapper.appendChild(linkOpen);
      tdActions.appendChild(wrapper);
      tr.appendChild(tdSlug);
      tr.appendChild(tdOriginal);
      tr.appendChild(tdClicks);
      tr.appendChild(tdActions);
      topTbody.appendChild(tr);
    });
    if (typeof setupCopyButtons === 'function') {
      setupCopyButtons();
    }
  };
  const fetchData = async () => {
    const period = periodSelect.value || '7d';
    const qs = `?period=${encodeURIComponent(period)}`;
    try {
      const [summaryRes, topRes] = await Promise.all([
        fetch(`${summaryUrl}${qs}`, { headers: { Accept: 'application/json' } }),
        fetch(`${topUrlBase}${qs}`, { headers: { Accept: 'application/json' } }),
      ]);
      if (summaryRes.ok) {
        const s = await summaryRes.json();
        renderSummary(s);
      }
      if (topRes.ok) {
        const t = await topRes.json();
        renderTop(t);
      }
    } catch (_) {}
  };
  periodSelect.addEventListener('change', fetchData);
  fetchData();
  setInterval(fetchData, 5000);
}

function initApp() {
  startCountdownTimers();
  startLinksPolling();
  startQrCodeToggles();
  setupCopyButtons();
  startDashboardMetrics();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}
