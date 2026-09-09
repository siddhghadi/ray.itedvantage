'use strict';

// Render text with browser fonts so product names can contain any supported script.
// Embed each rendered A4 page in a real PDF using the bundled, offline pdf-lib.
async function createCataloguePDF(products, title, layout, whatsapp, options = {}) {
  if (!window.PDFLib) throw new Error('PDF library could not load. Keep the vendor folder beside index.html.');
  const digits = String(whatsapp || '').replace(/\D/g, '');
  if (!/^[1-9]\d{7,14}$/.test(digits)) throw new Error('Enter a valid WhatsApp number including its country code.');
  const pdf = await PDFLib.PDFDocument.create();
  pdf.setTitle(title);
  pdf.setCreator('Local Product Catalogue Maker');
  const perPage = {one:1,two:2,four:4,six:6}[layout] || 4;
  const groups = new Map();
  for (const p of products) {
    const category = p.category?.trim() || 'Uncategorised';
    const key = category.toLocaleLowerCase();
    if (!groups.has(key)) groups.set(key, {category, products:[]});
    groups.get(key).products.push(p);
  }
  const pages = [];
  for (const group of groups.values()) {
    for (let i=0;i<group.products.length;i+=perPage) pages.push({category:group.category,products:group.products.slice(i,i+perPage)});
  }
  const pageCount = pages.length;
  await document.fonts.ready;
  let decoration;
  if(options.decoration){decoration=new Image();decoration.src=options.decoration;await decoration.decode();}
  for (let pageIndex = 0; pageIndex < pageCount; pageIndex++) {
    const canvas = document.createElement('canvas');
    canvas.width = 1654;
    canvas.height = 2339;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f8f4ec';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if(decoration){ctx.save();ctx.globalAlpha=.16;ctx.drawImage(decoration,130,1180,1390,1145);ctx.restore();}
    ctx.textBaseline = 'top';
    function wrapped(value, x, y, width, size, weight = 400) {
      ctx.font = `${weight} ${size}px "Segoe UI", Arial, sans-serif`;
      let line = '';
      for (const char of String(value)) {
        if (char === '\n' || (line && ctx.measureText(line + char).width > width)) {
          ctx.fillText(line, x, y);
          y += size * 1.35;
          line = char === '\n' ? '' : char;
        } else line += char;
      }
      if (line) { ctx.fillText(line, x, y); y += size * 1.35; }
      return y;
    }
    function fitted(value, x, y, width, size, maxLines, weight=400) {
      // Reserve a fixed number of lines so long names cannot run into the next card.
      while(size>10) {
        ctx.font=`${weight} ${size}px "Segoe UI", Arial, sans-serif`;
        let lines=1,line='';
        for(const char of String(value)) {
          if(char==='\n'||(line&&ctx.measureText(line+char).width>width)){lines++;line=char==='\n'?'':char}else line+=char;
        }
        if(lines<=maxLines) break;
        size--;
      }
      return wrapped(value,x,y,width,size,weight);
    }
    ctx.fillStyle = '#856032';
    fitted(title.toUpperCase(), 100, 70, 1454, 24, 1, 600);
    ctx.fillStyle = '#392b21';
    // Shrink unusually long imported titles to keep the header within its area.
    let titleSize = 44;
    while (titleSize > 16) {
      ctx.font = `650 ${titleSize}px "Segoe UI", Arial, sans-serif`;
      if (ctx.measureText(pages[pageIndex].category).width <= 1454 * 2) break;
      titleSize -= 2;
    }
    wrapped(pages[pageIndex].category, 100, 125, 1454, titleSize, 650);
    ctx.fillStyle='#bc965d';ctx.fillRect(100,260,1454,2);
    const top = 310, bottom = 2170, gap = 60;
    const columns=perPage===1?1:2, rows=Math.ceil(perPage/columns);
    const cardHeight = (bottom - top - gap * (rows - 1)) / rows;
    const cardWidth=(1454-gap*(columns-1))/columns;
    for (let slot = 0; slot < perPage; slot++) {
      const product = pages[pageIndex].products[slot];
      if (!product) break;
      const y = top + Math.floor(slot/columns) * (cardHeight + gap);
      const x = 100+(slot%columns)*(cardWidth+gap);
      const image = new Image();
      image.src = product.image;
      try { await image.decode(); } catch { throw new Error(`Cannot read the image for ${product.name}. Edit the product and choose its image again.`); }
      const single = perPage === 1;
      const imageBox = {x:x+12,y,w:cardWidth-24,h:cardHeight-(perPage===6?240:320)};
      const scale = Math.min(imageBox.w / image.naturalWidth, imageBox.h / image.naturalHeight);
      const w = image.naturalWidth * scale, h = image.naturalHeight * scale;
      ctx.save();ctx.beginPath();ctx.roundRect(imageBox.x+(imageBox.w-w)/2,imageBox.y+(imageBox.h-h)/2,w,h,30);ctx.clip();
      ctx.drawImage(image, imageBox.x + (imageBox.w-w)/2, imageBox.y + (imageBox.h-h)/2, w, h);ctx.restore();
      const tx = x+12, tw = cardWidth-24;
      let ty = y+imageBox.h+22;
      ctx.fillStyle = '#302922';
      ty = fitted(product.name.toUpperCase(), tx, ty, tw, perPage===6?25:32, 3, 700) + 8;
      ctx.fillStyle = '#765735';
      ctx.fillStyle = '#302922';
      const measurements = [];
      if (product.height !== null && product.height !== undefined && product.height !== '') measurements.push(`HEIGHT: ${product.height} ${product.unit}`);
      if (product.width !== null && product.width !== undefined && product.width !== '') measurements.push(`WIDTH: ${product.width} ${product.unit}`);
      if (measurements.length) ty = wrapped(measurements.join('   '), tx, ty, tw, perPage===6?20:24) + 8;
      if(product.price!==undefined&&product.price!=='') wrapped(`PRICE: ₹${product.price}`, tx, ty, tw, perPage===6?23:27,700);
    }
    ctx.fillStyle = '#654924';ctx.fillRect(0,2220,1654,85);
    ctx.fillStyle = '#fff4dd';
    wrapped(`CONNECT ON WHATSAPP  |  ${whatsapp}`,100,2248,1280,26,600);
    wrapped(`${pageIndex + 1} / ${pageCount}`, 1440, 2250, 140, 22);
    const jpg = await pdf.embedJpg(canvas.toDataURL('image/jpeg', 0.94));
    const page = pdf.addPage([595.28, 841.89]);
    page.drawImage(jpg, {x:0,y:0,width:595.28,height:841.89});
    const {PDFName,PDFString}=PDFLib;
    const annotation=pdf.context.obj({Type:'Annot',Subtype:'Link',Rect:[0,841.89*(2339-2305)/2339,595.28,841.89*(2339-2220)/2339],Border:[0,0,0],A:{Type:'Action',S:'URI',URI:PDFString.of(`https://wa.me/${digits}`)}});
    page.node.set(PDFName.of('Annots'),pdf.context.obj([pdf.context.register(annotation)]));
    if(options.onPage)await options.onPage(canvas,pageIndex,pageCount);
    canvas.width = canvas.height = 1;
  }
  return new Blob([await pdf.save()], {type:'application/pdf'});
}

let catalogueDownloadURL;
function downloadCataloguePDF(blob, title) {
  if (catalogueDownloadURL) URL.revokeObjectURL(catalogueDownloadURL);
  catalogueDownloadURL = URL.createObjectURL(blob);
  let link = document.getElementById('pdfDownload');
  if (!link) {
    link = document.createElement('a');
    link.id = 'pdfDownload';
    link.style.cssText = 'display:block;margin:0 0 18px;color:#196b54;font-weight:600';
    document.querySelector('.toolbar').after(link);
  }
  link.href = catalogueDownloadURL;
  link.download = (title.replace(/[<>:"/\\|?*\x00-\x1f]/g, '-').slice(0, 100).replace(/[. ]+$/g, '') || 'product-catalogue') + '.pdf';
  link.textContent = 'Download PDF';
  link.click();
}
