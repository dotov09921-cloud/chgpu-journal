document.addEventListener('DOMContentLoaded',async()=>{
  const catalog=document.getElementById('archiveCatalog');
  const filter=document.getElementById('archiveYearFilter');
  const search=document.getElementById('archiveSearch');
  if(!catalog||!filter||!search||!window.CHGPU_API||!window.CHGPU_API.enabled)return;

  try{
    const data=await publicArchive();
    const items=data.items||[];
    const years=[...new Set(items.map(x=>String(x.year)))];
    filter.innerHTML='<option value="">Все годы</option>'+years.map(y=>'<option value="'+chgpuEscape(y)+'">'+chgpuEscape(y)+'</option>').join('');

    const render=()=>{
      const q=search.value.trim().toLowerCase();
      const y=filter.value;
      const visible=items.filter(x=>(!y||String(x.year)===y)&&(!q||(String(x.year)+' '+x.series+' '+x.number).toLowerCase().includes(q)));
      const grouped={};
      visible.forEach(x=>{
        const year=String(x.year);
        grouped[year]=grouped[year]||{};
        grouped[year][x.series]=grouped[year][x.series]||[];
        grouped[year][x.series].push(x);
      });

      const html=Object.keys(grouped).sort((a,b)=>Number(b)-Number(a)).map(year=>{
        const seriesGroups=grouped[year];
        return '<section class="archive-year-block"><div class="archive-year-title"><strong>'+chgpuEscape(year)+'</strong><span>'+Object.keys(seriesGroups).length+' сер.</span></div>'+
          Object.entries(seriesGroups).map(([series,list])=>
            '<div class="archive-series"><h3>'+chgpuEscape(series)+'</h3><div class="issue-chips">'+
            list.map(i=>{
              const label=chgpuEscape(i.number);
              if(Number(i.has_pdf))return '<a class="issue-chip" target="_blank" rel="noopener" href="'+publicArchivePdfUrl(i.id)+'">'+label+' · PDF</a>';
              const suffix=i.migration_status==='duplicate_conflict'?' · уточняется':' · файл восстанавливается';
              return '<span class="issue-chip issue-chip-muted">'+label+suffix+'</span>';
            }).join('')+'</div></div>'
          ).join('')+'</section>';
      }).join('');
      catalog.innerHTML=html||'<div class="empty-state">Ничего не найдено.</div>';
    };

    search.addEventListener('input',render);
    filter.addEventListener('change',render);
    render();
  }catch(e){
    catalog.textContent='Не удалось загрузить архив.';
  }
});