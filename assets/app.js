(function () {
  var root = document.documentElement;
  var saved = localStorage.getItem('ray-theme');
  var preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  var theme = saved || preferred;
  root.dataset.theme = theme;
  root.dataset.sidebar = localStorage.getItem('ray-sidebar') || 'open';

  function updateButton() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      var icon = button.querySelector('span');
      if (icon) icon.textContent = root.dataset.theme === 'light' ? '☾' : '☼';
      button.setAttribute('aria-label', root.dataset.theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateButton();
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    buttons.forEach(function (button) { button.addEventListener('click', function () {
        root.dataset.theme = root.dataset.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('ray-theme', root.dataset.theme);
        updateButton();
      });
    });

    var chemtechSidebar = document.querySelector('[data-ct-sidebar]');
    if (chemtechSidebar) {
      root.dataset.ctSidebar = localStorage.getItem('chemtech-sidebar') || 'open';
      document.querySelectorAll('[data-ct-sidebar-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          if (window.matchMedia('(max-width: 760px)').matches) {
            chemtechSidebar.classList.toggle('open');
            return;
          }
          root.dataset.ctSidebar = root.dataset.ctSidebar === 'collapsed' ? 'open' : 'collapsed';
          localStorage.setItem('chemtech-sidebar', root.dataset.ctSidebar);
        });
      });
      chemtechSidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () { chemtechSidebar.classList.remove('open'); });
      });
    }

    document.querySelectorAll('[data-dialog-open]').forEach(function (button) {
      button.addEventListener('click', function () {
        var dialog = document.getElementById(button.getAttribute('data-dialog-open'));
        if (dialog && !button.disabled && typeof dialog.showModal === 'function') dialog.showModal();
      });
    });
    document.querySelectorAll('[data-dialog-close]').forEach(function (button) {
      button.addEventListener('click', function () {
        var dialog = button.closest('dialog');
        if (dialog) dialog.close();
      });
    });
    document.querySelectorAll('.ct-dialog').forEach(function (dialog) {
      dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
    });

    document.querySelectorAll('.ct-index .ct-table[id]').forEach(function (table) {
      var rows = Array.from(table.querySelectorAll('tbody tr'));
      var input = document.querySelector('[data-ct-table-filter="' + table.id + '"]');
      var pageSize = 10;
      var pageIndex = 0;
      var footer = document.createElement('div');
      footer.className = 'ct-pagination';
      footer.innerHTML = '<span></span><div><button type="button" aria-label="Previous page">‹</button><button type="button" aria-label="Next page">›</button></div>';
      table.closest('.ct-table-wrap').insertAdjacentElement('afterend', footer);
      var label = footer.querySelector('span');
      var previous = footer.querySelector('button:first-child');
      var next = footer.querySelector('button:last-child');

      function renderTable() {
        var query = input ? input.value.trim().toLowerCase() : '';
        var matching = rows.filter(function (row) { return query === '' || row.textContent.toLowerCase().includes(query); });
        var pages = Math.max(1, Math.ceil(matching.length / pageSize));
        pageIndex = Math.min(pageIndex, pages - 1);
        rows.forEach(function (row) { row.hidden = true; });
        matching.slice(pageIndex * pageSize, (pageIndex + 1) * pageSize).forEach(function (row) { row.hidden = false; });
        var start = matching.length ? pageIndex * pageSize + 1 : 0;
        var end = Math.min((pageIndex + 1) * pageSize, matching.length);
        label.textContent = start + '–' + end + ' of ' + matching.length;
        previous.disabled = pageIndex === 0;
        next.disabled = pageIndex >= pages - 1;
      }
      previous.addEventListener('click', function () { if (pageIndex > 0) { pageIndex--; renderTable(); } });
      next.addEventListener('click', function () { pageIndex++; renderTable(); });
      if (input) input.addEventListener('input', function () { pageIndex = 0; renderTable(); });
      renderTable();
    });

    var invoiceForm = document.querySelector('[data-ct-invoice-form]');
    if (invoiceForm) {
      var invoiceChoices = Array.from(invoiceForm.querySelectorAll('.ct-select-list label'));
      var invoiceCustomer = invoiceForm.querySelector('[data-ct-invoice-customer]');
      var invoiceOrders = invoiceForm.querySelector('[data-ct-invoice-orders]');
      var invoiceMessage = invoiceOrders ? invoiceOrders.querySelector('p') : null;
      if (invoiceCustomer) invoiceCustomer.addEventListener('change', function () {
        var customerId = invoiceCustomer.value;
        if (invoiceMessage) {
          invoiceMessage.textContent = customerId === '' ? 'Choose a customer to see their orders.' : 'Select one or more orders to include.';
          invoiceMessage.hidden = customerId !== '';
        }
        invoiceChoices.forEach(function (choice) {
          var checkbox = choice.querySelector('input');
          checkbox.checked = false;
          checkbox.disabled = false;
          choice.hidden = customerId === '' || choice.dataset.customer !== customerId;
          choice.classList.remove('disabled');
        });
      });
      invoiceChoices.forEach(function (label) {
        var input = label.querySelector('input');
        input.addEventListener('change', function () {
          var selected = invoiceChoices.find(function (choice) { return choice.querySelector('input').checked; });
          var selectedCustomer = selected ? selected.dataset.customer : '';
          invoiceChoices.forEach(function (choice) {
            var checkbox = choice.querySelector('input');
            var blocked = selectedCustomer !== '' && choice.dataset.customer !== selectedCustomer;
            checkbox.disabled = blocked;
            choice.classList.toggle('disabled', blocked);
          });
        });
      });
      invoiceForm.addEventListener('submit', function (event) {
        if (invoiceChoices.some(function (choice) { return choice.querySelector('input').checked; })) return;
        event.preventDefault();
        if (invoiceMessage) {
          invoiceMessage.textContent = 'Select an unbilled order before creating the invoice.';
          invoiceMessage.hidden = false;
        }
      });
    }

    document.querySelectorAll('[data-print-invoice]').forEach(function (button) {
      button.addEventListener('click', function () { window.print(); });
    });

    var blogTitle = document.getElementById('blog-title');
    if (blogTitle) {
      var blogContent = document.getElementById('blog-content');
      var blogCategory = document.getElementById('blog-category');
      var previewTitle = document.getElementById('preview-title');
      var previewContent = document.getElementById('preview-content');
      var previewCategory = document.getElementById('preview-category');
      var featuredImage = document.getElementById('featured-image');
      var additionalImages = document.getElementById('additional-images');
      var previewCover = document.getElementById('preview-cover');
      var previewGallery = document.getElementById('preview-gallery');

      function syncText() {
        previewTitle.textContent = blogTitle.value.trim() || 'Your blog title will appear here';
        previewContent.textContent = blogContent.value.trim() || 'Start writing in the editor. Your content will appear here using the selected blog template.';
        previewCategory.textContent = (blogCategory.value.trim() || 'Category').toUpperCase();
      }
      [blogTitle, blogContent, blogCategory].forEach(function (field) { field.addEventListener('input', syncText); });

      featuredImage.addEventListener('change', function () {
        var file = featuredImage.files && featuredImage.files[0];
        if (!file) { previewCover.style.backgroundImage = ''; previewCover.querySelector('span').hidden = false; return; }
        var reader = new FileReader();
        reader.onload = function () { previewCover.style.backgroundImage = 'url("' + reader.result + '")'; previewCover.querySelector('span').hidden = true; };
        reader.readAsDataURL(file);
      });

      additionalImages.addEventListener('change', function () {
        previewGallery.textContent = '';
        Array.from(additionalImages.files || []).slice(0, 6).forEach(function (file) {
          var reader = new FileReader();
          reader.onload = function () { var image = document.createElement('img'); image.src = reader.result; image.alt = ''; previewGallery.appendChild(image); };
          reader.readAsDataURL(file);
        });
      });
    }
  });
})();
