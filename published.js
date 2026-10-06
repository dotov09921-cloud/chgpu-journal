document.addEventListener('DOMContentLoaded', async () => {
  const box = document.getElementById('articlePublic');
  const slug = new URLSearchParams(location.search).get('slug');
  if (!slug) { box.innerHTML = '<div class="notice">Статья не указана.</div>'; return; }
  if (!window.CHGPU_API || !window.CHGPU_API.enabled) {
    box.innerHTML = '<div class="notice neutral">На GitHub Pages это демонстрационная страница. Реальные статьи появятся после подключения Beget.</div>';
    return;
  }
  try {
    const a = (await publicArticle(slug)).article;
    document.title = a.title + ' — Известия ЧГПУ';
    box.innerHTML =
      '<article class="article-layout"><div class="article-body">' +
      '<div class="crumb">' + a.year + ' / ' + a.number + ' / ' + a.series + '</div>' +
      '<h1 style="font-size:42px">' + a.title + '</h1>' +
      '<p class="lead">' + a.authors + '</p>' +
      '<div class="article-meta">Страницы: ' + (a.pages || '—') +
      (a.doi ? ' • DOI: ' + a.doi : '') +
      (a.udc ? ' • УДК: ' + a.udc : '') + '</div>' +
      '<h2>Аннотация</h2><p>' + (a.abstract || '').replace(/\n/g,'<br>') + '</p>' +
      '<h2>Ключевые слова</h2><p>' + (a.keywords || '') + '</p>' +
      (a.html_body ? '<h2>Текст статьи</h2><div>' + a.html_body + '</div>' : '') +
      '</div><aside class="article-aside"><h4>Выпуск</h4><p>' + a.number + ', ' + a.year +
      '</p><h4>Серия</h4><p>' + a.series + '</p></aside></article>';
  } catch (e) {
    box.innerHTML = '<div class="notice">' + e.message + '</div>';
  }
});