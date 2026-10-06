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
