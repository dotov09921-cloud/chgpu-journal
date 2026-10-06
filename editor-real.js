const realStatusLabels={new:'Новая',screening:'Первичная проверка',review:'Рецензирование',revision:'Доработка',accepted:'Одобрена',rejected:'Отклонена',published:'Опубликована'};
document.addEventListener('DOMContentLoaded',async()=>{
 if(!window.CHGPU_API||!window.CHGPU_API.enabled)return;
 const rows=document.getElementById('submissionRows');
 const kpis=document.getElementById('editorKpis');
 const panel=document.getElementById('submissionPanel');
 if(!rows||!kpis||!panel)return;

 let filter='';
 async function load(){
   try{
     const d=await editorList(filter);
     const items=d.items||[];
     const counts={new:0,review:0,revision:0,accepted:0};
     items.forEach(x=>{if(counts[x.status]!==undefined)counts[x.status]++});
     kpis.innerHTML=Object.entries(counts).map(([s,n])=>'<div class="kpi"><strong>'+n+'</strong><span>'+realStatusLabels[s]+'</span></div>').join('');
     rows.innerHTML=items.map(x=>'<tr class="click-row" data-id="'+x.id+'"><td>'+x.public_id+'</td><td><strong>'+x.title+'</strong><br><span class="note">'+x.section+'</span></td><td>'+x.author_name+'</td><td>'+x.created_at+'</td><td><span class="status-pill status-'+x.status+'">'+(realStatusLabels[x.status]||x.status)+'</span></td></tr>').join('');
     rows.querySelectorAll('.click-row').forEach(r=>r.addEventListener('click',()=>openSubmission(r.dataset.id)));
   }catch(e){location.href='login.html'}
 }

 async function openSubmission(id){
   try{
     const d=await editorGet(id),s=d.submission;
     const files=(d.files||[]).map(f=>'<div class="file-version"><div><strong>Версия '+f.version_no+'</strong> · '+f.file_type+'<br><span class="note">'+f.original_name+' · '+Math.round(f.size_bytes/1024)+' КБ</span><br><code>'+f.sha256+'</code></div><a class="btn ghost" href="/backend/api/file.php?id='+f.id+'">Скачать</a></div>').join('');
     const hist=(d.history||[]).map(h=>'<div><time>'+h.created_at+'</time><p>'+(h.actor_name?'<strong>'+h.actor_name+':</strong> ':'')+(h.comment||((h.from_status||'')+' → '+(h.to_status||'')))+'</p></div>').join('');
     panel.innerHTML='<div class="panel-head"><div><div class="crumb">'+s.public_id+'</div><h3>'+s.title+'</h3></div><span class="status-pill status-'+s.status+'">'+(realStatusLabels[s.status]||s.status)+'</span></div>'+
       '<div class="meta-grid"><div><span>Автор</span><b>'+s.author_name+'</b></div><div><span>Организация</span><b>'+s.organization+'</b></div><div><span>E-mail</span><b>'+s.author_email+'</b></div><div><span>Раздел</span><b>'+s.section+'</b></div></div>'+
       '<h4>Аннотация</h4><p>'+s.abstract+'</p><h4>Файлы и версии</h4><div class="file-versions">'+(files||'<div class="empty-state">Файлов нет</div>')+'</div>'+
       '<h4>Решение</h4><div class="decision-grid"><button data-status="screening" class="btn ghost">На проверку</button><button data-status="review" class="btn ghost">На рецензию</button><button data-status="revision" class="btn ghost">На доработку</button><button data-status="accepted" class="btn ghost">Принять</button><button data-status="rejected" class="btn ghost">Отклонить</button></div>'+
       '<div class="form-group" style="margin-top:14px"><label>Комментарий</label><textarea id="realComment"></textarea></div>'+
       '<h4>История</h4><div class="history">'+hist+'</div>';
     panel.querySelectorAll('[data-status]').forEach(b=>b.addEventListener('click',async()=>{try{await editorSetStatus(s.id,b.dataset.status,document.getElementById('realComment').value,true);await load();await openSubmission(s.id)}catch(e){alert(e.message)}}));
   }catch(e){panel.innerHTML='<div class="notice">'+e.message+'</div>'}
 }
 await load();
});