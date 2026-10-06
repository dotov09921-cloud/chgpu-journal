document.addEventListener('DOMContentLoaded',async()=>{
 const id=new URLSearchParams(location.search).get('id');
 const head=document.getElementById('issueHead'),list=document.getElementById('issueArticles');
 if(!id){head.innerHTML='<div class="notice">Выпуск не указан.</div>';return;}
 if(!window.CHGPU_API||!window.CHGPU_API.enabled){head.innerHTML='<div class="notice neutral">На GitHub Pages это демонстрация. Реальные выпуски будут загружаться с Beget.</div>';return;}
 try{
   const d=await publicIssue(id),i=d.issue;
   document.title=String(i.number||'Выпуск')+', '+String(i.year||'')+' — Известия ЧГПУ';
   head.innerHTML='<div class="crumb">'+chgpuEscape(i.year)+' / '+chgpuEscape(i.series)+'</div><h1>'+chgpuEscape(i.number)+'</h1><p class="lead">'+chgpuEscape(i.title||i.series)+'</p>';
   list.innerHTML='<div class="article-list">'+d.articles.map(a=>
     '<div class="article-row"><div class="field">'+chgpuEscape(a.pages||'')+'</div><div><h3><a href="published.html?slug='+
     encodeURIComponent(a.slug)+'">'+chgpuEscape(a.title)+'</a></h3><div class="authors">'+chgpuEscape(a.authors)+'</div></div><div></div></div>'
   ).join('')+'</div>';
 }catch(e){head.textContent=e.message;}
});