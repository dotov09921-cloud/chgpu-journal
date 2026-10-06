window.CHGPU_API = {
  enabled: !location.hostname.endsWith('github.io') && location.protocol !== 'file:',
  base: '/backend/api'
};

let CHGPU_CSRF_TOKEN = null;

async function chgpuCsrfToken(force = false) {
  if (CHGPU_CSRF_TOKEN && !force) return CHGPU_CSRF_TOKEN;
  const res = await fetch(window.CHGPU_API.base + '/csrf.php', {
    credentials: 'same-origin',
    cache: 'no-store'
  });
  if (!res.ok) throw new Error('Не удалось получить защитный токен');
  const data = await res.json();
  CHGPU_CSRF_TOKEN = data.token;
  return CHGPU_CSRF_TOKEN;
}

async function chgpuApi(path, options = {}, retry = true) {
  const method = String(options.method || 'GET').toUpperCase();
  const headers = new Headers(options.headers || {});
  if (!['GET','HEAD','OPTIONS'].includes(method)) {
    headers.set('X-CSRF-Token', await chgpuCsrfToken());
  }

  const res = await fetch(window.CHGPU_API.base + path, {
    credentials: 'same-origin',
    cache: 'no-store',
    ...options,
    headers
  });

  let data = {};
  try { data = await res.json(); } catch (_) {}

  if (res.status === 403 && retry && data.error === 'Security token invalid') {
    await chgpuCsrfToken(true);
    return chgpuApi(path, options, false);
  }

  if (!res.ok) {
    const err = new Error(data.error || ('HTTP ' + res.status));
    err.status = res.status;
    err.code = data.error || null;
    throw err;
  }
  return data;
}

async function submitManuscriptReal(form) {
  const fd = new FormData(form);
  if (!fd.has('form_started_at')) {
    fd.set('form_started_at', String(window.CHGPU_FORM_STARTED_AT || Math.floor(Date.now()/1000)));
  }
  const map = {
    author:'author_name',
    email:'author_email',
    org:'organization'
  };
  Object.entries(map).forEach(([from,to]) => {
    if (fd.has(from)) {
      fd.set(to, fd.get(from));
      fd.delete(from);
    }
  });
  return chgpuApi('/submit.php', { method:'POST', body:fd });
}

async function editorLogin(email,password) {
  const result = await chgpuApi('/login.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({email,password})
  });
  if (result.csrf_token) CHGPU_CSRF_TOKEN = result.csrf_token;
  return result;
}

async function editorList(status='') {
  return chgpuApi('/submissions.php' + (status ? '?status='+encodeURIComponent(status) : ''));
}

async function editorGet(id) {
  return chgpuApi('/submission.php?id='+encodeURIComponent(id));
}

async function editorSetStatus(id,status,comment='',visible_to_author=true) {
  return chgpuApi('/update-status.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id,status,comment,visible_to_author})
  });
}

async function trackSubmission(id,token) {
  return chgpuApi('/track.php?id='+encodeURIComponent(id)+'&token='+encodeURIComponent(token));
}


async function editorIssues() {
  return chgpuApi('/issues.php');
}
async function editorCreateIssue(payload) {
  return chgpuApi('/issues.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'create',...payload})
  });
}
async function editorSetIssuePublished(id,is_published) {
  return chgpuApi('/issues.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'set_published',id,is_published})
  });
}
async function editorPublishArticle(payload) {
  return chgpuApi('/publish.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
}
async function publicIssue(id) {
  return chgpuApi('/public-issue.php?id='+encodeURIComponent(id));
}
async function publicArticle(slug) {
  return chgpuApi('/public-article.php?slug='+encodeURIComponent(slug));
}

async function uploadRevisionReal(form) {
  const fd = new FormData(form);
  return chgpuApi('/upload-revision.php', { method:'POST', body:fd });
}

async function editorReviewers() {
  return chgpuApi('/reviewers.php');
}
async function editorAssignReviewer(payload) {
  return chgpuApi('/assign-reviewer.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
}
async function reviewerAssignments() {
  return chgpuApi('/my-reviews.php');
}
async function reviewerSubmit(payload) {
  return chgpuApi('/submit-review.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
}

async function adminUsers() {
  return chgpuApi('/users.php');
}
async function adminInviteUser(payload) {
  return chgpuApi('/users.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'invite',...payload})
  });
}
async function adminUpdateUser(payload) {
  return chgpuApi('/users.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'update',...payload})
  });
}
async function adminAuditLog() {
  return chgpuApi('/audit-log.php');
}
async function setInvitedPassword(email,token,password) {
  return chgpuApi('/set-password.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({email,token,password})
  });
}

async function adminSystemStatus() {
  return chgpuApi('/system-status.php');
}
async function adminIntegrityCheck() {
  return chgpuApi('/integrity.php');
}
async function adminBackupNow() {
  return chgpuApi('/backup-now.php', { method:'POST' });
}
function adminExportUrl() {
  return window.CHGPU_API.base + '/export-data.php';
}

function chgpuEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  })[ch]);
}
function chgpuNl2br(value) {
  return chgpuEscape(value).replace(/\n/g,'<br>');
}

async function publicArchive() {
  return chgpuApi('/public-archive.php');
}
function publicArchivePdfUrl(id) {
  return window.CHGPU_API.base + '/archive-pdf.php?id=' + encodeURIComponent(id);
}

async function currentUser() {
  return chgpuApi('/me.php');
}

function chgpuHandleAccessError(err, target) {
  if (err && err.status === 401) {
    location.href = 'login.html';
    return true;
  }
  if (err && err.status === 403) {
    if (target) {
      target.innerHTML = '<div class="notice"><strong>Недостаточно прав.</strong><br>Этот раздел недоступен для вашей роли.</div><p><a class="btn ghost" href="editor.html">Вернуться в редакцию</a></p>';
    }
    return true;
  }
  return false;
}

async function logoutUser() {
  return chgpuApi('/logout.php', { method:'POST' });
}
