document.addEventListener('DOMContentLoaded',async()=>{
 const box=document.getElementById('auditList');
 if(!window.CHGPU_API.enabled){box.innerHTML='<div class="notice neutral">Реальный журнал будет доступен после подключения Beget.</div>';return;}
 try{
   const d=await adminAuditLog();
   box.innerHTML='<table class="table"><thead><tr><th>Дата</th><th>Пользователь</th><th>Событие</th><th>Объект</th><th>IP</th></tr></thead><tbody>'+d.items.map(x=>'<tr><td>'+chgpuEscape(x.created_at)+'</td><td>'+chgpuEscape(x.actor_name||'Система')+'<br><span class="note">'+chgpuEscape(x.actor_email||'')+'</span></td><td>'+chgpuEscape(x.event_type)+'</td><td>'+chgpuEscape(x.entity_type)+' '+(x.entity_id||'')+'</td><td>'+chgpuEscape(x.ip_address||'—')+'</td></tr>').join('')+'</tbody></table>';
 }catch(e){if(chgpuHandleAccessError(e,document.body))return;throw e}
});