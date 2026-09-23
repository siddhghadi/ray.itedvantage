(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var workspace = document.querySelector('.ct-workspace');
    if (!workspace) return;
    var isTechDecodes = workspace.classList.contains('td-professional');
    var business = isTechDecodes ? 'techdecodes' : 'chemtech';
    var page = new URLSearchParams(location.search).get('page') || 'dashboard';
    var sidebar = workspace.querySelector('.ct-sidebar[data-ct-sidebar]');
    var backdrop = document.createElement('button');
    backdrop.className = 'ct-mobile-shade';
    backdrop.setAttribute('aria-label', 'Close navigation');
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', function () { sidebar.classList.remove('open'); });
    var refresh = document.createElement('button');
    refresh.type = 'button'; refresh.className = 'ct-mobile-refresh';
    refresh.textContent = '↻'; refresh.setAttribute('aria-label', 'Refresh page');
    refresh.addEventListener('click', function () { location.reload(); });
    document.querySelector('.ct-top-actions').prepend(refresh);
    var nav = document.createElement('nav');
    nav.className = 'ct-bottom-nav'; nav.setAttribute('aria-label', 'Mobile navigation');
    (isTechDecodes ? [['dashboard','Home'],['leads','Leads'],['add','+'],['email','Email'],['more','More']] : [['dashboard','Home'],['customers','Customers'],['add','+'],['orders','Orders'],['more','More']]).forEach(function (item) {
      var control = document.createElement(item[0] === 'add' || item[0] === 'more' ? 'button' : 'a');
      control.textContent = item[1];
      if (control.tagName === 'A') control.href = '?business=' + business + '&page=' + item[0];
      else control.type = 'button';
      if (page === item[0]) { control.className = 'active'; control.setAttribute('aria-current', 'page'); }
      if (item[0] === 'add') { control.className = 'ct-quick-plus'; control.setAttribute('aria-label','Quick actions'); control.addEventListener('click',function () { sheet.showModal(); }); }
      if (item[0] === 'more') control.addEventListener('click', function () { sidebar.classList.toggle('open'); });
      nav.appendChild(control);
    });
    document.body.appendChild(nav);
    var sheet = document.createElement('dialog'); sheet.className = 'ct-quick-sheet';
    var heading = document.createElement('h2'); heading.textContent = 'Quick actions'; sheet.appendChild(heading);
    var close = document.createElement('button'); close.type='button'; close.className='ct-quick-close'; close.textContent='×'; close.setAttribute('aria-label','Close quick actions'); close.onclick=function(){sheet.close();}; sheet.appendChild(close);
    var actions = document.createElement('div'); actions.className='ct-quick-grid';
    (isTechDecodes ? [['Import leads','leads'],['Compose email','email'],['Record payment','payments'],['Add follow-up','activity'],['Plan content','calendar']] : [['Add customer','ct-customer-dialog'],['Create order','ct-order-dialog'],['Add product','ct-product-dialog'],['Generate invoice','ct-invoice-dialog'],['Record payment','ct-payment-dialog']]).forEach(function(item){
      var button=document.createElement('button'); button.type='button'; button.textContent=item[0];
      var target=document.getElementById(item[1]);
      button.disabled=!isTechDecodes && (!target || !!target.querySelector('button[type="submit"]:disabled, button[name]:disabled'));
      button.onclick=function(){ sheet.close(); if(isTechDecodes) location.href='?business=techdecodes&page='+item[1]; else target.showModal(); }; actions.appendChild(button);
    });
    sheet.appendChild(actions); document.body.appendChild(sheet);
    sheet.addEventListener('click',function(event){if(event.target===sheet)sheet.close();});
    document.querySelectorAll('.ct-main .ct-table').forEach(function(table){
      var headers=Array.from(table.querySelectorAll('thead th')).map(function(th){return th.textContent.trim();});
      table.querySelectorAll('tbody tr').forEach(function(row){Array.from(row.cells).forEach(function(cell,index){cell.dataset.label=headers[index] || '';});});
    });
  });
})();
