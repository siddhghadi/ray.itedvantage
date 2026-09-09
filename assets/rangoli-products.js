'use strict';
(() => {
  const root=document.getElementById('wr-products');if(!root)return;
  const get=id=>document.getElementById(id);
  const form=get('wr-product-form');
  if(form){
    let processing=false;
    get('wr-photo').addEventListener('change',async()=>{
      const file=get('wr-photo').files[0];if(!file)return;
      processing=true;get('wr-save').disabled=true;get('wr-form-status').textContent='Preparing photo…';
      try{
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>15*1024*1024)throw Error('Choose a JPG, PNG or WebP photo smaller than 15 MB.');
        const bitmap=await createImageBitmap(file),scale=Math.min(1,1400/Math.max(bitmap.width,bitmap.height));
        const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));
        const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();
        const data=canvas.toDataURL('image/jpeg',.86);if(data.length>2800000)throw Error('This photo is too large. Please choose a smaller photo.');
        get('wr-image-data').value=data;get('wr-photo-preview').src=data;get('wr-photo-preview').hidden=false;get('wr-form-status').textContent='Photo ready.';
      }catch(e){get('wr-photo').value='';get('wr-form-status').textContent=e.message;}
      finally{processing=false;get('wr-save').disabled=false;}
    });
    form.addEventListener('submit',e=>{if(processing){e.preventDefault();return}get('wr-save').disabled=true;get('wr-save').textContent='Saving…'});
    return;
  }
  if(!get('wr-product-rows'))return;
  const rows=[...root.querySelectorAll('tr[data-product]')];
  function update(){
    const term=get('wr-search').value.trim().toLocaleLowerCase(),category=get('wr-filter').value;
    for(const row of rows){const p=JSON.parse(row.dataset.product);row.hidden=Boolean(category&&row.dataset.category!==category)||!(p.name+' '+(p.sku||'')).toLocaleLowerCase().includes(term);row.classList.toggle('wr-selected',row.querySelector('input').checked);}
    const visible=rows.filter(r=>!r.hidden),selected=rows.filter(r=>r.querySelector('input').checked);
    get('wr-all').checked=visible.length>0&&visible.every(r=>r.querySelector('input').checked);
    get('wr-all').indeterminate=visible.some(r=>r.querySelector('input').checked)&&!get('wr-all').checked;
    get('wr-empty').hidden=visible.length>0;
    const hidden=selected.filter(r=>r.hidden).length;
    get('wr-selection-count').textContent=selected.length?`${selected.length} products selected${hidden?` (${hidden} in other categories/filters)`:''}`:'No products selected';
    get('wr-make-pdf').disabled=!selected.length;get('wr-make-pdf').textContent=selected.length?`Make PDF (${selected.length})`:'Make PDF';get('wr-clear').hidden=!selected.length;
  }
  get('wr-search').addEventListener('input',update);get('wr-filter').addEventListener('change',update);
  rows.forEach(row=>row.querySelector('input').addEventListener('change',update));
  get('wr-all').addEventListener('change',()=>{rows.filter(r=>!r.hidden).forEach(r=>r.querySelector('input').checked=get('wr-all').checked);update()});
  get('wr-clear').addEventListener('click',()=>{rows.forEach(r=>r.querySelector('input').checked=false);update()});
  let downloadURL;
  const dialog=get('wr-pdf-dialog');
  get('wr-close-pdf').addEventListener('click',()=>dialog.close());
  get('wr-make-pdf').addEventListener('click',async()=>{
    const button=get('wr-make-pdf');button.disabled=true;button.textContent='Preparing PDF…';get('wr-export-status').textContent='Preparing your catalogue…';
    try{
      const products=rows.filter(r=>r.querySelector('input').checked).map(row=>{const p=JSON.parse(row.dataset.product);return {...p,category:row.dataset.category,image:row.querySelector('img')?.src,price:p.retail_price}});
      const missing=products.find(p=>!p.sku||p.category==='Uncategorised');
      if(missing)throw Error(`Open “${missing.name}” and add its category and SKU before making a PDF.`);
      get('wr-pdf-pages').replaceChildren();
      const blob=await createCataloguePDF(products,'Woolen Rangoli','four','+91 8419997526',{decoration:'assets/rangoli-decoration.png',onPage:(canvas,index,total)=>{
        const preview=document.createElement('img');preview.src=canvas.toDataURL('image/jpeg',.78);preview.alt=`Catalogue page ${index+1} of ${total}`;get('wr-pdf-pages').append(preview);
      }});
      if(downloadURL)URL.revokeObjectURL(downloadURL);downloadURL=URL.createObjectURL(blob);get('wr-download-pdf').href=downloadURL;
      dialog.showModal();get('wr-export-status').textContent='Your catalogue is ready. Preview it and choose Download PDF.';
    }catch(e){get('wr-export-status').textContent=e.message||'Could not create the PDF. Please try again.';}
    finally{update()}
  });
  update();
})();
