document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('publishForm');
  if (!form) return;

  const sub = document.getElementById('submissionSelect');
  const issue = document.getElementById('issueSelect');
  const msg = document.getElementById('publishMsg');

  if (!window.CHGPU_API || !window.CHGPU_API.enabled) {
    sub.innerHTML = '<option value="47">Демо: CHGPU-2026-0047</option>';
    issue.innerHTML = '<option value="2">Демо: №3 (55), 2026</option>';
  } else {
    try {
      const accepted = await editorList('accepted');
      const issues = await editorIssues();
      sub.innerHTML = accepted.items.map(x =>
        '<option value="' + x.id + '">' + x.public_id + ' — ' + x.title + '</option>'
      ).join('');
      issue.innerHTML = issues.items.map(x =>
        '<option value="' + x.id + '">' + x.year + ' — ' + x.number + ' — ' + x.series + '</option>'
      ).join('');
    } catch (e) {
      location.href = 'login.html';
      return;
    }
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    msg.style.display = 'block';

    if (!window.CHGPU_API.enabled) {
      msg.innerHTML = '<strong>Демо.</strong> На Beget здесь будет создана реальная публичная статья.';
      return;
    }

    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      const result = await editorPublishArticle(payload);
      msg.innerHTML = '<strong>Статья опубликована.</strong><br><a href="' +
        result.public_url + '">Открыть публикацию →</a>';
    } catch (err) {
      msg.textContent = err.message;
    }
  });
});