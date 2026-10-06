const statusLabels={new:'Новая рукопись',screening:'Первичная проверка',review:'На рецензировании',revision:'Требуется доработка',accepted:'Принята к публикации',rejected:'Отклонена',published:'Опубликована'};
document.addEventListener('DOMContentLoaded',async()=>{
 const box=document.getElementById('trackBox');
 const p=new URLSearchParams(location.search),id=p.get('id'),token=p.get('token');
 if(!id||!token){box.innerHTML='<div class="notice">Некорректная ссылка отслеживания.</div>';return;}
 if(!window.CHGPU_API.enabled){box.innerHTML='<div class="notice neutral"><strong>Демо-режим.</strong><br>На Beget здесь появится реальный статус и загрузка доработки.</div>';return;}
 try{
   const d=await trackSubmission(id,token),s=d.submission;
   const history=d.history.map(h=>'<div><time>'+chgpuEscape(new Date(h.created_at).toLocaleString('ru-RU'))+'</time><p>'+chgpuNl2br(h.comment||statusLabels[h.to_status]||h.event_type)+'</p></div>').join('');
   const revision=s.status==='revision'?'<div class="revision-box"><h3>Загрузить доработанную версию</h3><form id="revisionForm"><input type="hidden" name="id" value="'+chgpuEscape(s.public_id)+'"><input type="hidden" name="token" value="'+chgpuEscape(token)+'"><div class="form-group"><label>Новая версия DOC/DOCX *</label><input name="manuscript" type="file" accept=".doc,.docx" required></div><div class="form-group"><label>PDF новой версии</label><input name="pdf" type="file" accept=".pdf"></div><div class="form-group"><label>Комментарий</label><textarea name="comment"></textarea></div><button class="btn accent" type="submit">Отправить новую версию</button><div id="revisionMsg" class="note"></div></form></div>':'';
   box.innerHTML='<div class="crumb">'+chgpuEscape(s.public_id)+'</div><h2>'+chgpuEscape(s.title)+'</h2><div class="tracking-status">'+chgpuEscape(statusLabels[s.status]||s.status)+'</div><h3>История</h3><div class="history">'+history+'</div>'+revision;
   const form=document.getElementById('revisionForm');
   if(form)form.addEventListener('submit',async e=>{e.preventDefault();const m=document.getElementById('revisionMsg');try{const r=await uploadRevisionReal(form);m.textContent='Версия '+r.version+' загружена. Статус снова переведён на проверку.';setTimeout(()=>location.reload(),1200)}catch(err){m.textContent=err.message}});
 }catch(err){box.textContent=err.message;}
});