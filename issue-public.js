document.addEventListener('DOMContentLoaded', async () => {
  const id = new URLSearchParams(location.search).get('id');
  const head = document.getElementById('issueHead');
  const list = document.getElementById('issueArticles');
  if (!id) { head.innerHTML = '<div class="notice">Выпуск не указан.</div>'; return; }
  if (!window.CHGPU_API || !window.CHGPU_API.enabled) {
    head.innerHTML = '<div class="notice neutral">На GitHub Pages это демонстрация. Реальные выпуски будут загружаться с Beget.</div>';
    return;
  }
  try {
    const d = await publicIssue(id), i = d.issue;
    document.title = i.number + ', ' + i.year + ' — Известия ЧГПУ';
    head.innerHTML = '<div class="crumb">' + i.year + ' / ' + i.series + '</div><h1>' + i.number + '</h1><p class="lead">' + (i.title || i.series) + '</p>';
    list.innerHTML = '<div class="article-list">' + d.articles.map(a =>
      '<div class="article-row"><div class="field">' + (a.pages || '') + '</div><div><h3><a href="published.html?slug=' +
      encodeURIComponent(a.slug) + '">' + a.title + '</a></h3><div class="authors">' + a.authors + '</div></div><div></div></div>'
    ).join('') + '</div>';
  } catch (e) {
    head.innerHTML = '<div class="notice">' + e.message + '</div>';
  }
});