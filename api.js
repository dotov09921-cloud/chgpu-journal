window.CHGPU_API = {
  enabled: !location.hostname.endsWith('github.io') && location.protocol !== 'file:',
  base: '/backend/api'
};

async function chgpuApi(path, options = {}) {
  const res = await fetch(window.CHGPU_API.base + path, {
    credentials: 'same-origin',
    ...options
  });
  let data = {};
  try { data = await res.json(); } catch (_) {}
  if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
  return data;
}

async function submitManuscriptReal(form) {
  const fd = new FormData(form);
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
  return chgpuApi('/login.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({email,password})
  });
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
