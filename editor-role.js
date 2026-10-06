document.addEventListener('DOMContentLoaded',async()=>{
  if(!window.CHGPU_API||!window.CHGPU_API.enabled)return;
  try{
    const data=await currentUser();
    const role=data.user.role;
    document.querySelectorAll('.admin-only').forEach(el=>{
      el.style.display=role==='admin'?'block':'none';
    });
    if(role==='reviewer'){
      location.href='reviewer.html';
    }
  }catch(err){
    if(err.status===401)location.href='login.html';
  }
});