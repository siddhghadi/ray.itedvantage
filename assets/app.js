(function () {
  var root = document.documentElement;
  var saved = localStorage.getItem('ray-theme');
  var preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  var theme = saved || preferred;
  root.dataset.theme = theme;
  root.dataset.sidebar = localStorage.getItem('ray-sidebar') || 'open';

  function updateButton() {
    var button = document.getElementById('theme-toggle');
    if (!button) return;
    var icon = button.querySelector('span');
    if (icon) icon.textContent = root.dataset.theme === 'light' ? '☾' : '☼';
    button.setAttribute('aria-label', root.dataset.theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateButton();
    var button = document.getElementById('theme-toggle');
    if (!button) return;
    button.addEventListener('click', function () {
      root.dataset.theme = root.dataset.theme === 'light' ? 'dark' : 'light';
      localStorage.setItem('ray-theme', root.dataset.theme);
      updateButton();
    });

    var sidebarButton = document.getElementById('sidebar-toggle');
    function updateSidebarButton() {
      if (!sidebarButton) return;
      var collapsed = root.dataset.sidebar === 'collapsed';
      sidebarButton.textContent = collapsed ? '☰' : '‹';
      sidebarButton.setAttribute('aria-label', collapsed ? 'Open sidebar' : 'Collapse sidebar');
      sidebarButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    updateSidebarButton();
    if (sidebarButton) sidebarButton.addEventListener('click', function () {
      root.dataset.sidebar = root.dataset.sidebar === 'collapsed' ? 'open' : 'collapsed';
      localStorage.setItem('ray-sidebar', root.dataset.sidebar);
      updateSidebarButton();
    });
  });
})();
