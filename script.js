const STORAGE_KEY='chgpu_demo_submissions_v2';
const STATUS={new:'Новая',review:'Рецензирование',revision:'Доработка',accepted:'Одобрена',published:'Опубликована',rejected:'Отклонена'};
function loadSubs(){const saved=localStorage.getItem(STORAGE_KEY);if(saved){try{return JSON.parse(saved)}catch(e){}}return (window.CHGPU_DATA?.seedSubmissions||[]).map(x=>JSON.parse(JSON.stringify(x)));}
function saveSubs(items){localStorage.setItem(STORAGE_KEY,JSON.stringify(items));}
function today(){return new Date().toLocaleDateString('ru-RU');}
function showToast(text){const toast=document.getElementById('toast');if(!toast)return;toast.textContent=text;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),2200);}
function nextId(items){const nums=items.map(x=>parseInt((x.id||'').match(/(\d+)$/)?.[1]||0,10));return 'CHGPU-2026-'+String(Math.max(47,...nums)+1).padStart(4,'0');}

document.addEventListener('DOMContentLoaded',()=>{
  // archive
  const catalog=document.getElementById('archiveCatalog');
  if(catalog && window.CHGPU_DATA){
    const filter=document.getElementById('archiveYearFilter');
    CHGPU_DATA.archive.forEach(y=>{const o=document.createElement('option');o.value=y.year;o.textContent=y.year;filter.appendChild(o)});
    const render=()=>{
      const q=(document.getElementById('archiveSearch').value||'').toLowerCase();
      const year=filter.value;
      catalog.innerHTML='';
      CHGPU_DATA.archive.filter(y=>!year||String(y.year)===year).forEach(y=>{
        const matches=y.series.some(s=>(String(y.year)+' '+s.name+' '+s.issues.join(' ')).toLowerCase().includes(q));
        if(!matches)return;
        const sec=document.createElement('section');sec.className='archive-year-block';
        sec.innerHTML='<div class="archive-year-title"><strong>'+y.year+'</strong><span>'+y.series.length+' сер.</span></div>';
        y.series.forEach(s=>{
          if(q && !(String(y.year)+' '+s.name+' '+s.issues.join(' ')).toLowerCase().includes(q))return;
          const group=document.createElement('div');group.className='archive-series';
          group.innerHTML='<h3>'+s.name+'</h3><div class="issue-chips">'+s.issues.map(i=>'<a class="issue-chip" href="article.html">'+i+'</a>').join('')+'</div>';
          sec.appendChild(group);
        });
        catalog.appendChild(sec);
      });
      if(!catalog.children.length)catalog.innerHTML='<div class="empty-state">Ничего не найдено.</div>';
    };
    document.getElementById('archiveSearch').addEventListener('input',render);filter.addEventListener('change',render);render();
  }

  // board
  const board=document.getElementById('boardList');
  if(board && window.CHGPU_DATA){
    board.innerHTML=CHGPU_DATA.editorialBoard.map(p=>'<article class="person-row"><div class="field">'+p.role+'</div><div><h3>'+p.name+'</h3><p>'+p.degree+'</p><span>'+p.org+'</span></div></article>').join('');
  }

  // submit
  const form=document.getElementById('submissionForm');
  if(form){
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      const box=document.getElementById('successBox');
      if(window.CHGPU_API && window.CHGPU_API.enabled){
        box.style.display='block';box.innerHTML='Отправка рукописи…';
        try{
          const result=await submitManuscriptReal(form);
          box.innerHTML='<strong>Материал принят системой.</strong><br>Регистрационный номер: <b>'+result.public_id+'</b>.<br><br><a href="'+result.tracking_url+'">Отслеживать статус статьи →</a>';
          form.reset();window.CHGPU_FORM_STARTED_AT=Math.floor(Date.now()/1000);window.scrollTo({top:0,behavior:'smooth'});
        }catch(err){box.innerHTML='<strong>Не удалось отправить.</strong><br>'+err.message}
        return;
      }
      const fd=new FormData(form), items=loadSubs(), id=nextId(items);
      const item={id,title:fd.get('title'),author:fd.get('author'),email:fd.get('email'),org:fd.get('org'),orcid:fd.get('orcid'),section:fd.get('section'),language:fd.get('language'),abstract:fd.get('abstract'),keywords:fd.get('keywords'),coauthors:fd.get('coauthors'),status:'new',submittedAt:new Date().toISOString().slice(0,10),history:[{date:today(),text:'Рукопись поступила в редакцию'}],files:{manuscript:fd.get('manuscript')?.name||'',pdf:fd.get('pdf')?.name||''}};
      items.unshift(item);saveSubs(items);
      box.style.display='block';box.innerHTML='<strong>Материал принят в демо.</strong><br>Регистрационный номер: <b>'+id+'</b>.';
      form.reset();window.scrollTo({top:0,behavior:'smooth'});
    });
  }

  // editor
  const rows=document.getElementById('submissionRows');
  if(rows && !(window.CHGPU_API && window.CHGPU_API.enabled)){
    let items=loadSubs(), filter='all', selectedId=null;
    const kpis=document.getElementById('editorKpis'), panel=document.getElementById('submissionPanel');
    const badge=s=>'<span class="status-pill status-'+s+'">'+(STATUS[s]||s)+'</span>';
    const counts=()=>['new','review','revision','accepted'].map(s=>({s,n:items.filter(x=>x.status===s).length}));
    const renderKpis=()=>{kpis.innerHTML=counts().map(x=>'<div class="kpi"><strong>'+x.n+'</strong><span>'+STATUS[x.s]+'</span></div>').join('')};
    const renderRows=()=>{const list=items.filter(x=>filter==='all'||x.status===filter);rows.innerHTML=list.map(x=>'<tr class="click-row '+(selectedId===x.id?'selected':'')+'" data-id="'+x.id+'"><td>'+x.id.replace('CHGPU-2026-','')+'</td><td><strong>'+x.title+'</strong><br><span class="note">'+x.section+'</span></td><td>'+x.author+'</td><td>'+x.submittedAt.split('-').reverse().join('.')+'</td><td>'+badge(x.status)+'</td></tr>').join('')||'<tr><td colspan="5" class="empty-state">В этом разделе нет рукописей.</td></tr>';document.querySelectorAll('.click-row').forEach(r=>r.addEventListener('click',()=>{selectedId=r.dataset.id;renderRows();renderPanel()}))};
    const setStatus=(item,status,label)=>{item.status=status;item.history.push({date:today(),text:label});saveSubs(items);renderKpis();renderRows();renderPanel();showToast(label)};
    const renderPanel=()=>{const x=items.find(i=>i.id===selectedId);if(!x){panel.innerHTML='<div class="empty-state">Выберите рукопись слева, чтобы открыть карточку.</div>';return}
      panel.innerHTML='<div class="panel-head"><div><div class="crumb">'+x.id+'</div><h3>'+x.title+'</h3></div>'+badge(x.status)+'</div>'+
      '<div class="meta-grid"><div><span>Автор</span><b>'+x.author+'</b></div><div><span>Организация</span><b>'+x.org+'</b></div><div><span>Направление</span><b>'+x.section+'</b></div><div><span>E-mail</span><b>'+x.email+'</b></div></div>'+
      '<h4>Аннотация</h4><p>'+x.abstract+'</p><h4>Ключевые слова</h4><p>'+x.keywords+'</p>'+
      '<h4>Решение редакции</h4><div class="decision-grid"><button class="btn ghost" data-status="review">На рецензию</button><button class="btn ghost" data-status="revision">На доработку</button><button class="btn ghost" data-status="accepted">Принять</button><button class="btn ghost" data-status="rejected">Отклонить</button><button class="btn accent" data-status="published">Опубликовать</button></div>'+
      '<div class="form-group" style="margin-top:18px"><label>Комментарий редактора</label><textarea id="editorComment" placeholder="Например: привести список литературы к требованиям журнала"></textarea><button id="addComment" class="btn ghost" style="margin-top:8px">Добавить в историю</button></div>'+
      '<h4>История</h4><div class="history">'+x.history.slice().reverse().map(h=>'<div><time>'+h.date+'</time><p>'+h.text+'</p></div>').join('')+'</div>';
      panel.querySelectorAll('[data-status]').forEach(b=>b.addEventListener('click',()=>{const map={review:'Передана на рецензирование',revision:'Направлена автору на доработку',accepted:'Принята к публикации',rejected:'Отклонена редакцией',published:'Опубликована на сайте'};setStatus(x,b.dataset.status,map[b.dataset.status])}));
      document.getElementById('addComment').addEventListener('click',()=>{const t=document.getElementById('editorComment').value.trim();if(!t)return;x.history.push({date:today(),text:'Комментарий редакции: '+t});saveSubs(items);renderPanel();showToast('Комментарий сохранён')});
    };
    document.querySelectorAll('.side-link').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.side-link').forEach(x=>x.classList.remove('active'));b.classList.add('active');filter=b.dataset.filter;selectedId=null;renderRows();renderPanel()}));
    document.getElementById('resetDemo').addEventListener('click',()=>{localStorage.removeItem(STORAGE_KEY);items=loadSubs();selectedId=null;renderKpis();renderRows();renderPanel();showToast('Демо-данные восстановлены')});
    document.getElementById('exportDemo').addEventListener('click',()=>{const blob=new Blob([JSON.stringify(items,null,2)],{type:'application/json'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='chgpu-submissions-demo.json';a.click();URL.revokeObjectURL(a.href)});
    renderKpis();renderRows();renderPanel();
  }

  // legacy archive buttons on home
  document.querySelectorAll('.year').forEach(button=>button.addEventListener('click',()=>{const year=button.dataset.year;document.querySelectorAll('.year').forEach(b=>b.classList.toggle('active',b===button));document.querySelectorAll('[data-archive-year]').forEach(item=>item.style.display=item.dataset.archiveYear===year?'':'none')}));
});