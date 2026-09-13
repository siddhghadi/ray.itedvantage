(function () {
  'use strict';
  var promptEvent;
  var button = document.getElementById('ray-install');
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).catch(function () {});
  }
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    promptEvent = event;
    if (button && !window.matchMedia('(display-mode: standalone)').matches) button.hidden = false;
  });
  window.addEventListener('appinstalled', function () {
    promptEvent = null;
    if (button) button.hidden = true;
  });
  if (button) button.addEventListener('click', async function () {
    if (!promptEvent) return;
    var pending = promptEvent;
    promptEvent = null;
    button.hidden = true;
    await pending.prompt();
    await pending.userChoice;
  });
})();
