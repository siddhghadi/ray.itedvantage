(() => {
  const body = document.body;
  const refresh=document.querySelector('#refresh');
  refresh?.addEventListener('click',()=>{const url=new URL(refresh.href);url.searchParams.set('_refresh',String(Date.now()));refresh.href=url.href;});
  // Native actions are trusted-origin links, not a JavaScript/native bridge.
  if(navigator.userAgent.includes('RAYInsurance/')) {
    const tools=document.createElement('a');tools.className='button secondary';tools.textContent='App tools';
    const url=new URL(location.href);url.searchParams.set('__ray_action','tools');tools.href=url.href;
    document.querySelector('#sidebar nav')?.after(tools);
  }
  document.querySelector('#edit-client-toggle')?.addEventListener('click', e => { const panel=document.querySelector('#client-edit');panel.hidden=!panel.hidden;e.currentTarget.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)panel.querySelector('input:not([type=hidden])')?.focus(); });
  // Theme only: never store client records, credentials or quotations in browser storage.
  try { if(localStorage.getItem('insurance-theme') === 'dark') body.classList.add('dark'); } catch {}
  document.querySelector('#theme')?.addEventListener('click', () => { body.classList.toggle('dark'); try { localStorage.setItem('insurance-theme', body.classList.contains('dark') ? 'dark' : 'light'); } catch {} });
  document.querySelector('#menu')?.addEventListener('click', e => { body.classList.toggle('menu-open'); e.currentTarget.setAttribute('aria-expanded', String(body.classList.contains('menu-open'))); });
  const close=document.createElement('button');close.className='secondary mobile-close';close.textContent='Close menu';close.addEventListener('click',()=>{body.classList.remove('menu-open');document.querySelector('#menu')?.setAttribute('aria-expanded','false');document.querySelector('#menu')?.focus();});document.querySelector('#sidebar')?.prepend(close);
  document.addEventListener('keydown', e => { if(e.key === 'Escape') { body.classList.remove('menu-open'); document.querySelector('#menu')?.setAttribute('aria-expanded','false'); } });
  const fullscreen=document.querySelector('#fullscreen'); if(fullscreen) { fullscreen.hidden=!document.fullscreenEnabled; fullscreen.addEventListener('click',async()=>{try { if(document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen(); } catch { fullscreen.textContent='Fullscreen unavailable'; }}); }
  document.querySelector('#print')?.addEventListener('click',()=>window.print());
})();
