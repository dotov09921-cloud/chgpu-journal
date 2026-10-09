document.addEventListener('DOMContentLoaded',async()=>{
 const list=document.getElementById('usersList'),form=document.getElementById('inviteForm'),msg=document.getElementById('inviteMsg');
 function showInvite(r,heading){
   msg.style.display='block';
     const title=document.createElement('strong');
     title.textContent=heading;
     const note=document.createElement('span');
     note.className='note';
     note.textContent='Ссылка действует 72 часа.';
     msg.replaceChildren(title,document.createElement('br'),note);
     if(r.mail_enabled===false){
       const mailNote=document.createElement('p');
       mailNote.textContent='Отправка почты отключена. Передайте ссылку пользователю вручную.';
       msg.append(mailNote);
     }
     if(typeof r.invite_url==='string'&&r.invite_url.trim()){
       try{
         const url=new URL(r.invite_url,window.location.href);
         if(!['http:','https:'].includes(url.protocol)||url.username||url.password)throw new Error('Unsafe invite URL');
         const link=document.createElement('a');
         link.href=url.href;
         link.textContent=r.invite_url;
         link.target='_blank';
         link.rel='noopener noreferrer';
         link.style.overflowWrap='anywhere';
         const line=document.createElement('p');
         line.append(link);
         msg.append(line);
       }catch{
         const warning=document.createElement('p');
         warning.textContent='Ссылка приглашения имеет недопустимый формат.';
         msg.append(warning);
       }
     }
 }
 async function load(){
   if(!window.CHGPU_API.enabled){list.innerHTML='<div class="notice neutral">На GitHub Pages админка работает как макет. Реальные пользователи будут на Beget.</div>';return;}
   try{
     const d=await adminUsers();
     list.innerHTML=d.items.map(u=>'<article class="user-card"><div><h3>'+chgpuEscape(u.full_name)+'</h3><p>'+chgpuEscape(u.email)+'</p><span class="note">Последний вход: '+chgpuEscape(u.last_login_at||'ещё не входил')+'</span></div><div class="user-actions"><select data-role="'+u.id+'"><option value="admin" '+(u.role==='admin'?'selected':'')+'>Администратор</option><option value="editor" '+(u.role==='editor'?'selected':'')+'>Редактор</option><option value="reviewer" '+(u.role==='reviewer'?'selected':'')+'>Рецензент</option></select><button class="btn ghost" data-save="'+u.id+'">Сохранить</button><button class="btn ghost" data-toggle="'+u.id+'" data-active="'+u.is_active+'">'+(Number(u.is_active)?'Заблокировать':'Разблокировать')+'</button>'+'<button class="btn ghost" data-reinvite="'+Number(u.id)+'">Новая ссылка</button>'+'</div></article>').join('');
     list.querySelectorAll('[data-save]').forEach(b=>b.addEventListener('click',async()=>{const id=Number(b.dataset.save),sel=list.querySelector('[data-role="'+id+'"]');await adminUpdateUser({id,role:sel.value});await load()}));
     list.querySelectorAll('[data-toggle]').forEach(b=>b.addEventListener('click',async()=>{await adminUpdateUser({id:Number(b.dataset.toggle),is_active:!Number(b.dataset.active)});await load()}));
     list.querySelectorAll('[data-reinvite]').forEach(b=>b.addEventListener('click',async()=>{
       b.disabled=true;msg.style.display='block';msg.textContent='Создаём новую ссылку…';
       try{
         const r=await adminReinviteUser(Number(b.dataset.reinvite));
         showInvite(r,'Новая ссылка приглашения создана.');
       }catch(err){msg.textContent=err.message}
       finally{b.disabled=false}
     }));
   }catch(e){if(chgpuHandleAccessError(e,document.body))return;throw e}
 }
 form.addEventListener('submit',async e=>{
   e.preventDefault();msg.style.display='block';msg.replaceChildren();
   if(!window.CHGPU_API.enabled){msg.textContent='Демо: приглашение не отправляется.';return;}
   try{
     const r=await adminInviteUser(Object.fromEntries(new FormData(form).entries()));
     showInvite(r,'Приглашение создано.');
     form.reset();await load();
   }catch(err){msg.textContent=err.message}
 });
 load();
});