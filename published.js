document.addEventListener('DOMContentLoaded', async () => {
  const box=document.getElementById('articlePublic');
  const slug=new URLSearchParams(location.search).get('slug');
  if(!slug){box.innerHTML='<div class="notice">Статья не указана.</div>';return;}
  if(!window.CHGPU_API||!window.CHGPU_API.enabled){box.innerHTML='<div class="notice neutral">На GitHub Pages это демонстрационная страница. Реальные статьи появятся после подключения Beget.</div>';return;}
  try{
    const a=(await publicArticle(slug)).article;
    document.title=String(a.title||'Статья')+' — Известия ЧГПУ';
    box.innerHTML=
      '<article class="article-layout"><div class="article-body">'+
      '<div class="crumb">'+chgpuEscape(a.year)+' / '+chgpuEscape(a.number)+' / '+chgpuEscape(a.series)+'</div>'+
      '<h1 style="font-size:42px">'+chgpuEscape(a.title)+'</h1>'+
      '<p class="lead">'+chgpuEscape(a.authors)+'</p>'+
      '<div class="article-meta">Страницы: '+chgpuEscape(a.pages||'—')+
      (a.doi?' • DOI: '+chgpuEscape(a.doi):'')+(a.udc?' • УДК: '+chgpuEscape(a.udc):'')+'</div>'+
      '<h2>Аннотация</h2><p>'+chgpuNl2br(a.abstract||'')+'</p>'+
      '<h2>Ключевые слова</h2><p>'+chgpuEscape(a.keywords||'')+'</p>'+
      (a.html_body?'<h2>Текст статьи</h2><div>'+chgpuNl2br(a.html_body)+'</div>':'')+
      '</div><aside class="article-aside"><h4>Выпуск</h4><p>'+chgpuEscape(a.number)+', '+chgpuEscape(a.year)+
      '</p><h4>Серия</h4><p>'+chgpuEscape(a.series)+'</p></aside></article>';
  }catch(e){box.textContent=e.message;}
});