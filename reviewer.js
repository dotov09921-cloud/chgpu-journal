document.addEventListener('DOMContentLoaded',async()=>{
 const box=document.getElementById('reviewList');
 if(!window.CHGPU_API||!window.CHGPU_API.enabled){box.innerHTML='<div class="notice neutral">На GitHub Pages кабинет рецензента работает только как макет. Реальные назначения появятся на Beget.</div>';return;}
 try{
   const d=await reviewerAssignments();
   if(!d.items.length){box.innerHTML='<div class="empty-state">Назначенных рукописей нет.</div>';return;}
   box.innerHTML=d.items.map(x=>'<article class="review-card" data-id="'+Number(x.assignment_id)+'"><div class="eyebrow">'+chgpuEscape(x.public_id)+' · '+chgpuEscape(x.section)+'</div><h3>'+chgpuEscape(x.title)+'</h3><p>'+chgpuNl2br(x.abstract)+'</p><div class="review-meta"><span>Срок: '+chgpuEscape(x.deadline||'не указан')+'</span><span>Статус: '+chgpuEscape(x.review_status)+'</span></div>'+(x.latest_file_id?'<p><a class="btn ghost" href="/backend/api/reviewer-file.php?id='+Number(x.latest_file_id)+'">Скачать рукопись</a></p>':'')+'<div class="form-group"><label>Рекомендация</label><select class="recommendation"><option value="accept">Принять</option><option value="revision">Доработать</option><option value="reject">Отклонить</option></select></div><div class="form-group"><label>Заключение</label><textarea class="reviewComment"></textarea></div><button class="btn accent sendReview">Отправить заключение</button><div class="note resultMsg"></div></article>').join('');
   box.querySelectorAll('.review-card').forEach(card=>card.querySelector('.sendReview').addEventListener('click',async()=>{
     const result=card.querySelector('.resultMsg');
     try{await reviewerSubmit({assignment_id:Number(card.dataset.id),recommendation:card.querySelector('.recommendation').value,comment:card.querySelector('.reviewComment').value.trim()});result.textContent='Заключение отправлено.';card.querySelector('.sendReview').disabled=true}catch(e){result.textContent=e.message}
   }));
 }catch(e){location.href='login.html'}
});