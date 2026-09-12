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

    var invoiceForm = document.querySelector('[data-ct-invoice-form]');
    if (invoiceForm) {
      var invoiceChoices = Array.from(invoiceForm.querySelectorAll('.ct-select-list label'));
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
    }

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
