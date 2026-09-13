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
      function updateSidebarButton() {
        var toggle = chemtechSidebar.querySelector('[data-ct-sidebar-toggle]');
        if (!toggle) return;
        var collapsed = root.dataset.ctSidebar === 'collapsed';
        toggle.textContent = collapsed ? '›' : '‹';
        toggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
      }
      updateSidebarButton();
      document.querySelectorAll('[data-ct-sidebar-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          if (window.matchMedia('(max-width: 760px)').matches) {
            chemtechSidebar.classList.toggle('open');
            return;
          }
          root.dataset.ctSidebar = root.dataset.ctSidebar === 'collapsed' ? 'open' : 'collapsed';
          localStorage.setItem('chemtech-sidebar', root.dataset.ctSidebar);
          updateSidebarButton();
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

    var editOrderDialog = document.getElementById('ct-edit-order-dialog');
    if (editOrderDialog) {
      var editOrderId = editOrderDialog.querySelector('[data-ct-edit-order-id]');
      var editOrderNumber = editOrderDialog.querySelector('[data-ct-edit-order-number]');
      var editOrderItems = editOrderDialog.querySelector('[data-ct-edit-order-items]');
      var orderRecords = readJson('ct-order-records');
      document.querySelectorAll('[data-ct-edit-order]').forEach(function (button) {
        button.addEventListener('click', function () {
          var order = orderRecords[button.dataset.orderId] || { items: [] };
          editOrderId.value = button.dataset.orderId || '';
          editOrderNumber.textContent = button.dataset.orderNumber || 'Order';
          editOrderItems.innerHTML = (order.items || []).map(function (item) {
            return '<label>' + escapeMarkup(item.product_name || 'Product') + '<small>' + (Number(item.quantity_milli || 0) / 1000).toLocaleString('en-IN', { maximumFractionDigits: 3 }) + ' ' + escapeMarkup(item.unit || '') + '</small><input type="number" name="item_prices[]" min=".01" step=".01" value="' + (Number(item.unit_price_paise || 0) / 100).toFixed(2) + '" required></label>';
          }).join('');
          editOrderDialog.showModal();
          var firstPrice = editOrderItems.querySelector('input');
          if (firstPrice) { firstPrice.focus(); firstPrice.select(); }
        });
      });
    }

    var orderForm = document.querySelector('[data-ct-order-form]');
    if (orderForm) {
      var orderItems = orderForm.querySelector('[data-ct-order-items]');
      var orderItemTemplate = document.getElementById('ct-order-item-template');
      var orderLineIndex = orderItems.querySelectorAll('[data-ct-order-item]').length;

      function refreshOrderLineControls() {
        var lines = Array.from(orderItems.querySelectorAll('[data-ct-order-item]'));
        lines.forEach(function (line) {
          var remove = line.querySelector('[data-ct-remove-order-item]');
          if (remove) remove.hidden = lines.length === 1;
        });
      }

      function bindOrderLine(line) {
        var product = line.querySelector('[data-ct-order-product]');
        var price = line.querySelector('[data-ct-order-price]');
        var remove = line.querySelector('[data-ct-remove-order-item]');
        if (product) product.addEventListener('change', function () {
          var option = product.options[product.selectedIndex];
          if (price) price.value = option && option.dataset.price ? option.dataset.price : '';
        });
        if (remove) remove.addEventListener('click', function () {
          line.remove();
          refreshOrderLineControls();
        });
      }

      orderItems.querySelectorAll('[data-ct-order-item]').forEach(bindOrderLine);
      var addOrderItem = orderForm.querySelector('[data-ct-add-order-item]');
      if (addOrderItem && orderItemTemplate) addOrderItem.addEventListener('click', function () {
        var holder = document.createElement('div');
        holder.innerHTML = orderItemTemplate.innerHTML.replace(/__INDEX__/g, String(orderLineIndex++));
        var line = holder.firstElementChild;
        orderItems.appendChild(line);
        bindOrderLine(line);
        refreshOrderLineControls();
        line.querySelector('select').focus();
      });

      var customerModes = orderForm.querySelectorAll('[name="customer_mode"]');
      function syncCustomerMode() {
        var selected = orderForm.querySelector('[name="customer_mode"]:checked');
        var mode = selected ? selected.value : 'existing';
        orderForm.querySelectorAll('[data-ct-customer-mode]').forEach(function (section) {
          var active = section.dataset.ctCustomerMode === mode;
          section.hidden = !active;
          section.querySelectorAll('input,select').forEach(function (field) { field.disabled = !active; });
        });
        var existingCustomer = orderForm.querySelector('[name="customer_id"]');
        var oneTimeName = orderForm.querySelector('[name="one_time_name"]');
        if (existingCustomer) existingCustomer.required = mode === 'existing';
        if (oneTimeName) oneTimeName.required = mode === 'one_time';
      }
      customerModes.forEach(function (radio) { radio.addEventListener('change', syncCustomerMode); });
      syncCustomerMode();
      refreshOrderLineControls();
    }

    function readJson(id) {
      var node = document.getElementById(id);
      if (!node) return {};
      try { return JSON.parse(node.textContent || '{}'); } catch (error) { return {}; }
    }

    function displayMoney(paise) {
      return '₹' + (Number(paise || 0) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeMarkup(value) {
      return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character];
      });
    }

    var popupContext = readJson('ct-popup-context');
    var customerRecords = readJson('ct-customer-records');
    var customerViewDialog = document.getElementById('ct-customer-view-dialog');

    function openCustomer(customerId) {
      var customer = customerRecords[customerId];
      if (!customer || !customerViewDialog) return false;
      customerViewDialog.querySelectorAll('[data-ct-customer-field]').forEach(function (field) {
        field.value = customer[field.dataset.ctCustomerField] == null ? '' : customer[field.dataset.ctCustomerField];
      });
      customerViewDialog.querySelector('[data-ct-customer-title]').textContent = customer.name || 'Customer details';
      customerViewDialog.querySelector('[data-ct-customer-name]').textContent = customer.name || 'Customer';
      customerViewDialog.querySelector('[data-ct-customer-avatar]').textContent = (customer.name || 'CT').slice(0, 2).toUpperCase();
      customerViewDialog.querySelector('[data-ct-customer-summary]').textContent = (customer.contact_person || 'No contact person') + ' · ' + (customer.state || 'State not set');
      customerViewDialog.querySelectorAll('[data-ct-customer-stat]').forEach(function (node) {
        node.textContent = customer[node.dataset.ctCustomerStat] || 0;
      });
      customerViewDialog.querySelectorAll('[data-ct-customer-money]').forEach(function (node) {
        node.textContent = displayMoney(customer[node.dataset.ctCustomerMoney]);
      });
      var call = customerViewDialog.querySelector('[data-ct-customer-call]');
      var whatsapp = customerViewDialog.querySelector('[data-ct-customer-whatsapp]');
      var email = customerViewDialog.querySelector('[data-ct-customer-email]');
      var phone = String(customer.phone || '');
      var digits = phone.replace(/\D+/g, '');
      call.hidden = phone === '';
      call.href = phone ? 'tel:' + phone : '#';
      whatsapp.hidden = digits === '';
      whatsapp.href = digits ? 'https://wa.me/' + digits : '#';
      email.hidden = !customer.email;
      email.href = customer.email ? 'mailto:' + customer.email : '#';
      customerViewDialog.showModal();
      return true;
    }

    document.querySelectorAll('[data-ct-customer]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (openCustomer(link.dataset.ctCustomer)) event.preventDefault();
      });
    });

    var invoiceRecords = readJson('ct-invoice-records');
    var invoiceViewDialog = document.getElementById('ct-invoice-view-dialog');
    var invoiceDownloadData = document.getElementById('ct-invoice-data');

    function invoiceLinesMarkup(lines) {
      return (lines || []).map(function (line, index) {
        return '<tr><td>' + (index + 1) + '</td><td><b>' + escapeMarkup(line.description) + '</b><small>' + escapeMarkup(line.order_number) + '</small></td><td>' + escapeMarkup(line.hsn || '—') + '</td><td>' + Number(line.quantity || 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }) + ' ' + escapeMarkup(line.unit) + '</td><td>' + displayMoney(line.rate_paise) + '</td><td>' + displayMoney(line.subtotal_paise) + '</td><td>' + displayMoney(line.tax_paise) + '<small>' + (Number(line.tax_rate_bps || 0) / 100).toFixed(2) + '%</small></td><td><b>' + displayMoney(line.total_paise) + '</b></td></tr>';
      }).join('');
    }

    function openInvoice(invoiceId) {
      var record = invoiceRecords[invoiceId];
      if (!record || !invoiceViewDialog || !invoiceDownloadData) return false;
      var totals = record.totals || {};
      var taxMarkup = Number(totals.cgst_paise || 0) > 0
        ? '<dt>CGST</dt><dd>' + displayMoney(totals.cgst_paise) + '</dd><dt>SGST</dt><dd>' + displayMoney(totals.sgst_paise) + '</dd>'
        : '<dt>IGST</dt><dd>' + displayMoney(totals.igst_paise) + '</dd>';
      var address = function (value, fallback) { return escapeMarkup(value || fallback).replace(/\n/g, '<br>'); };
      invoiceViewDialog.querySelector('[data-ct-invoice-title]').textContent = record.invoice.number || 'Invoice';
      invoiceViewDialog.querySelector('[data-ct-invoice-document]').innerHTML =
        '<header class="ct-invoice-header"><div><span>TAX INVOICE</span><h2>' + escapeMarkup(record.company.name) + '</h2><p>' + address(record.company.address, 'Add the registered address in Settings.') + '</p><small>GSTIN: ' + escapeMarkup(record.company.gstin || 'Not configured') + '</small></div><div><strong>' + escapeMarkup(record.invoice.number) + '</strong><dl><dt>Invoice date</dt><dd>' + escapeMarkup(record.invoice.date) + '</dd><dt>Due date</dt><dd>' + escapeMarkup(record.invoice.due_date) + '</dd><dt>Status</dt><dd>' + escapeMarkup(record.invoice.status) + '</dd></dl></div></header>' +
        '<section class="ct-invoice-parties"><div><span>BILL TO</span><strong>' + escapeMarkup(record.customer.name) + '</strong><p>' + address(record.customer.address, 'Address not provided') + '</p><small>GSTIN: ' + escapeMarkup(record.customer.gstin || 'Unregistered') + '</small></div><div><span>PAYMENT DETAILS</span><p><b>' + escapeMarkup(record.bank.name || 'Bank not configured') + '</b><br>Account: ' + escapeMarkup(record.bank.account || '—') + '<br>IFSC: ' + escapeMarkup(record.bank.ifsc || '—') + '</p></div></section>' +
        '<div class="ct-table-wrap"><table class="ct-invoice-lines"><thead><tr><th>#</th><th>Description</th><th>HSN</th><th>Qty</th><th>Rate</th><th>Taxable</th><th>Tax</th><th>Total</th></tr></thead><tbody>' + invoiceLinesMarkup(record.lines) + '</tbody></table></div>' +
        '<footer class="ct-invoice-totals"><div><span>Payment status</span><strong>' + (Number(totals.balance_paise || 0) > 0 ? displayMoney(totals.balance_paise) + ' due' : 'Paid in full') + '</strong></div><dl><dt>Taxable amount</dt><dd>' + displayMoney(totals.subtotal_paise) + '</dd>' + taxMarkup + '<dt class="total">Invoice total</dt><dd class="total">' + displayMoney(totals.total_paise) + '</dd><dt>Amount received</dt><dd>' + displayMoney(totals.paid_paise) + '</dd><dt>Balance due</dt><dd>' + displayMoney(totals.balance_paise) + '</dd></dl></footer>' +
        '<p class="ct-invoice-note">Computer-generated tax invoice. Verify company GST, address and bank details in Settings before using a real invoice.</p>';
      invoiceDownloadData.textContent = JSON.stringify(record);
      invoiceViewDialog.showModal();
      return true;
    }

    document.querySelectorAll('[data-ct-invoice]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (openInvoice(link.dataset.ctInvoice)) event.preventDefault();
      });
    });

    if (popupContext.customer) openCustomer(popupContext.customer);
    if (popupContext.invoice) openInvoice(popupContext.invoice);
    if ((popupContext.customer || popupContext.invoice) && window.history && window.history.replaceState) {
      var cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete('open_customer');
      cleanUrl.searchParams.delete('open_invoice');
      window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
    }

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
