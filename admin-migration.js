document.addEventListener('DOMContentLoaded', async () => {
  const box = document.getElementById('migrationStatus');
  const log = document.getElementById('migrationLog');
  const metadata = document.getElementById('metadataBtn');
  const pdf = document.getElementById('pdfBtn');
  const refresh = document.getElementById('refreshBtn');
  const buttons = [metadata, pdf, refresh];
  let state = null;
  function render(s) {
    state = s;
    box.innerHTML = '<div class="system-grid metrics">' + Object.entries(s.counts)
      .map(([k, v]) => '<div class="system-card"><span>' + chgpuEscape(k) + '</span><strong>' + Number(v) + '</strong></div>').join('') + '</div>';
    const progress = document.createElement('progress');
    progress.max = 31; progress.value = s.counts.imported;
    progress.setAttribute('aria-label', 'Импортировано PDF из 31');
    box.appendChild(progress);
    const text = document.createElement('p');
    text.textContent = 'PDF: ' + s.counts.imported + ' / 31. Всего выпусков: ' + s.total + '.';
    box.appendChild(text);
  }
  function unlock() {
    metadata.disabled = false; refresh.disabled = false;
    pdf.disabled = !state || !state.curl_available || state.counts.ready + state.counts.failed === 0;
  }
  async function run(action) {
    buttons.forEach(b => { b.disabled = true; });
    log.textContent = action === 'pdf_batch' ? 'Скачивание и проверка одного PDF…' : 'Выполнение…';
    try {
      const s = await chgpuApi('/legacy-migration.php', action ? {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action })
      } : {});
      render(s);
      log.textContent = action ? JSON.stringify(s.result, null, 2) : (s.last_action ? s.last_action.created_at + '\n' + s.last_action.event_type + '\n' + JSON.stringify(JSON.parse(s.last_action.details_json), null, 2) : 'Действий пока нет. Статус обновлён.');
      if (!s.curl_available) log.textContent += '\nДля скачивания PDF включите расширение PHP cURL на Beget.';
    } catch (e) {
      if (chgpuHandleAccessError(e, box)) return;
      log.textContent = e.message + '\nОбновите статус перед повтором: запрос мог завершиться на сервере.';
    } finally { unlock(); }
  }
  metadata.addEventListener('click', () => run('metadata'));
  pdf.addEventListener('click', () => run('pdf_batch'));
  refresh.addEventListener('click', () => run(null));
  if (!window.CHGPU_API.enabled) { log.textContent = 'Миграция доступна на сервере Beget.'; return; }
  try {
    const me = await currentUser();
    if ((me.user || me).role !== 'admin') { log.textContent = 'Доступ только для администратора.'; return; }
    await run(null);
  } catch (e) { if (!chgpuHandleAccessError(e, box)) log.textContent = e.message; }
});
