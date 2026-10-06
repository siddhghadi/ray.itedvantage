(() => {
  const body = document.body;
  // Theme only: never store client records, credentials or quotations in browser storage.
  try { if(localStorage.getItem('insurance-theme') === 'dark') body.classList.add('dark'); } catch {}
  document.querySelector('#theme')?.addEventListener('click', () => { body.classList.toggle('dark'); try { localStorage.setItem('insurance-theme', body.classList.contains('dark') ? 'dark' : 'light'); } catch {} });
  document.querySelector('#menu')?.addEventListener('click', e => { body.classList.toggle('menu-open'); e.currentTarget.setAttribute('aria-expanded', String(body.classList.contains('menu-open'))); });
  const close=document.createElement('button');close.className='secondary mobile-close';close.textContent='Close menu';close.addEventListener('click',()=>{body.classList.remove('menu-open');document.querySelector('#menu')?.setAttribute('aria-expanded','false');document.querySelector('#menu')?.focus();});document.querySelector('#sidebar')?.prepend(close);
  document.addEventListener('keydown', e => { if(e.key === 'Escape') { body.classList.remove('menu-open'); document.querySelector('#menu')?.setAttribute('aria-expanded','false'); } });
  const fullscreen=document.querySelector('#fullscreen'); if(fullscreen) { fullscreen.hidden=!document.fullscreenEnabled; fullscreen.addEventListener('click',async()=>{try { if(document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen(); } catch { fullscreen.textContent='Fullscreen unavailable'; }}); }
  document.querySelector('#print')?.addEventListener('click',()=>window.print());
})();
