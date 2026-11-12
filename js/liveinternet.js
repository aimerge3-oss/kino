/** Official LiveInternet counter (bound to registered domain) */
(function () {
  'use strict';

  var box = document.getElementById('liveinternet-counter');
  if (!box) return;

  // Official code from liveinternet.ru cabinet
  box.innerHTML =
    '<a href="https://www.liveinternet.ru/click" target="_blank" rel="noopener">' +
    '<img id="licnt044E" width="88" height="31" style="border:0;vertical-align:middle" ' +
    'title="LiveInternet: показано число просмотров и посетителей за 24 часа" ' +
    'src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7" ' +
    'alt="LiveInternet"/></a>';

  (function (d, s) {
    var img = d.getElementById('licnt044E');
    if (!img) return;
    img.src =
      'https://counter.yadro.ru/hit?t52.6;r' +
      escape(d.referrer) +
      (typeof s === 'undefined'
        ? ''
        : ';s' +
          s.width +
          '*' +
          s.height +
          '*' +
          (s.colorDepth ? s.colorDepth : s.pixelDepth)) +
      ';u' +
      escape(d.URL) +
      ';h' +
      escape(d.title.substring(0, 150)) +
      ';' +
      Math.random();
  })(document, screen);
})();
