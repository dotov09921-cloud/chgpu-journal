document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('passwordForm'),msg=document.getElementById('passwordMsg'),p=new URLSearchParams(location.search);
 form.addEventListener('submit',async e=>{
  e.preventDefault();msg.style.display='block';
  const fd=new FormData(form),a=fd.get('password'),b=fd.get('password2');
  if(a!==b){msg.textContent='Пароли не совпадают.';return;}
  try{await setInvitedPassword(p.get('email')||'',p.get('token')||'',a);msg.innerHTML='<strong>Пароль установлен.</strong><br><a href="login.html">Перейти ко входу →</a>';form.querySelector('button').disabled=true}catch(err){msg.textContent=err.message}
 });
});