document.addEventListener('DOMContentLoaded',async()=>{
  if(!window.CHGPU_API||!window.CHGPU_API.enabled)return;
  const side=document.querySelector('.side');
  if(!side)return;
  try{
    const d=await currentUser();
    const roleNames={admin:'Администратор',editor:'Редактор',reviewer:'Рецензент'};
    const box=document.createElement('div');
    box.className='session-box';
    box.innerHTML='<div class="session-name">'+chgpuEscape(d.user.full_name)+'</div><div class="session-role">'+chgpuEscape(roleNames[d.user.role]||d.user.role)+'</div><button id="logoutBtn" class="session-logout">Выйти / сменить пользователя</button>';
    side.insertBefore(box,side.children[1]||null);
    document.getElementById('logoutBtn').addEventListener('click',async()=>{
      try{await logoutUser();}catch(_){}
      location.href='login.html';
    });
  }catch(err){
    if(err.status===401)location.href='login.html';
  }
});