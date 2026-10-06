document.addEventListener('DOMContentLoaded',async()=>{
 const list=document.getElementById('usersList'),form=document.getElementById('inviteForm'),msg=document.getElementById('inviteMsg');
 async function load(){
   if(!window.CHGPU_API.enabled){list.innerHTML='<div class="notice neutral">На GitHub Pages админка работает как макет. Реальные пользователи будут на Beget.</div>';return;}
   try{
     const d=await adminUsers();
     list.innerHTML=d.items.map(u=>'<article class="user-card"><div><h3>'+chgpuEscape(u.full_name)+'</h3><p>'+chgpuEscape(u.email)+'</p><span class="note">Последний вход: '+chgpuEscape(u.last_login_at||'ещё не входил')+'</span></div><div class="user-actions"><select data-role="'+u.id+'"><option value="admin" '+(u.role==='admin'?'selected':'')+'>Администратор</option><option value="editor" '+(u.role==='editor'?'selected':'')+'>Редактор</option><option value="reviewer" '+(u.role==='reviewer'?'selected':'')+'>Рецензент</option></select><button class="btn ghost" data-save="'+u.id+'">Сохранить</button><button class="btn ghost" data-toggle="'+u.id+'" data-active="'+u.is_active+'">'+(Number(u.is_active)?'Заблокировать':'Разблокировать')+'</button></div></article>').join('');
     list.querySelectorAll('[data-save]').forEach(b=>b.addEventListener('click',async()=>{const id=Number(b.dataset.save),sel=list.querySelector('[data-role="'+id+'"]');await adminUpdateUser({id,role:sel.value});await load()}));
     list.querySelectorAll('[data-toggle]').forEach(b=>b.addEventListener('click',async()=>{await adminUpdateUser({id:Number(b.dataset.toggle),is_active:!Number(b.dataset.active)});await load()}));
   }catch(e){location.href='login.html'}
 }
 form.addEventListener('submit',async e=>{
   e.preventDefault();msg.style.display='block';
   if(!window.CHGPU_API.enabled){msg.textContent='Демо: приглашение не отправляется.';return;}
   try{
     const r=await adminInviteUser(Object.fromEntries(new FormData(form).entries()));
     msg.innerHTML='<strong>Приглашение создано.</strong><br><span class="note">Ссылка действует 72 часа.</span>';
     form.reset();await load();
   }catch(err){msg.textContent=err.message}
 });
 load();
});