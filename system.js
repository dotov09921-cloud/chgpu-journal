document.addEventListener('DOMContentLoaded',async()=>{
 const box=document.getElementById('systemStatus'),msg=document.getElementById('systemMsg');
 const backupBtn=document.getElementById('backupBtn'),integrityBtn=document.getElementById('integrityBtn'),exportBtn=document.getElementById('exportBtn');
 if(!window.CHGPU_API.enabled){box.innerHTML='<div class="notice neutral">Реальная системная диагностика включится после подключения Beget.</div>';return;}
 exportBtn.href=adminExportUrl();

 async function load(){
   try{
     const s=await adminSystemStatus();
     const b=s.latest_backup;
     box.innerHTML=
       '<div class="system-grid">'+
       '<div class="system-card"><span>База данных</span><strong>'+s.db+'</strong></div>'+
       '<div class="system-card"><span>PHP</span><strong>'+s.php_version+'</strong></div>'+
       '<div class="system-card"><span>Uploads</span><strong>'+(s.uploads_writable?'Запись OK':'Ошибка записи')+'</strong></div>'+
       '<div class="system-card"><span>Почта</span><strong>'+(s.mail_enabled?'Включена':'Выключена')+'</strong></div>'+
       '</div>'+
       '<div class="system-grid metrics">'+
       Object.entries(s.counts).map(([k,v])=>'<div class="system-card"><span>'+k+'</span><strong>'+v+'</strong></div>').join('')+
       '</div>'+
       '<section class="system-section"><h3>Последняя резервная копия</h3>'+(b?'<pre>'+JSON.stringify(b,null,2)+'</pre>':'<div class="empty-state">Резервных копий пока нет.</div>')+'</section>'+
       '<section class="system-section"><h3>Последние системные ошибки</h3>'+(s.recent_errors.length?'<table class="table"><tbody>'+s.recent_errors.map(x=>'<tr><td>'+x.created_at+'</td><td>'+x.level+'</td><td>'+x.message+'</td><td>'+x.request_uri+'</td></tr>').join('')+'</tbody></table>':'<div class="empty-state">Ошибок нет.</div>')+'</section>'+
       '<section class="system-section"><h3>Ошибки отправки писем</h3>'+(s.recent_failed_mail.length?'<table class="table"><tbody>'+s.recent_failed_mail.map(x=>'<tr><td>'+x.created_at+'</td><td>'+x.recipient+'</td><td>'+x.subject+'</td><td>'+x.error_text+'</td></tr>').join('')+'</tbody></table>':'<div class="empty-state">Ошибок отправки нет.</div>')+'</section>';
   }catch(e){location.href='login.html'}
 }

 backupBtn.addEventListener('click',async()=>{msg.style.display='block';msg.textContent='Создание резервной копии…';try{const r=await adminBackupNow();msg.textContent='Backup создан: '+r.manifest.created_at;await load()}catch(e){msg.textContent=e.message}});
 integrityBtn.addEventListener('click',async()=>{msg.style.display='block';msg.textContent='Проверка файлов…';try{const r=await adminIntegrityCheck();msg.textContent='Проверено '+r.total+' файлов. Исправны: '+r.ok+'. Отсутствуют: '+r.missing.length+'. Не совпадает SHA-256/размер: '+r.mismatch.length}catch(e){msg.textContent=e.message}});
 load();
});