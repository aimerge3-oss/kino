/**
 * LiveInternet counter
 * 1. Зарегистрируй сайт на https://www.liveinternet.ru/
 * 2. Вставь свой ID счётчика ниже (из кода в личном кабинете)
 * 3. Либо положи полный код из кабинета в #liveinternet-counter в футере
 */
(function () {
  'use strict';

  // ← замени на свой ID из LiveInternet (часто это имя сайта или хеш из URL counter.yadro.ru/hit;ID)
  var LI_ID = localStorage.getItem('li_counter_id') || 'kinopoisk_ai';

  // Невидимый hit
  try {
    var ref = typeof document.referrer !== 'undefined' ? document.referrer : '';
    var screenPart = '';
    if (typeof screen !== 'undefined') {
      screenPart =
        ';s' +
        screen.width +
        '*' +
        screen.height +
        '*' +
        (screen.colorDepth ? screen.colorDepth : screen.pixelDepth);
    }
    var url = typeof document.URL !== 'undefined' ? document.URL : '';
    var title =
      typeof document.title !== 'undefined'
        ? document.title.substring(0, 150)
        : '';

    var src =
      'https://counter.yadro.ru/hit;' +
      encodeURIComponent(LI_ID) +
      '?r' +
      escape(ref) +
      screenPart +
      ';u' +
      escape(url) +
      ';h' +
      escape(title) +
      ';' +
      Math.random();

    new Image().src = src;
  } catch (e) {}

  // Бейдж в футере (если есть контейнер)
  var box = document.getElementById('liveinternet-counter');
  if (box && !box.hasChildNodes()) {
    var a = document.createElement('a');
    a.href = 'https://www.liveinternet.ru/click;'+ encodeURIComponent(LI_ID);
    a.target = '_blank';
    a.rel = 'noopener';
    a.title = 'LiveInternet';

    var img = document.createElement('img');
    img.src =
      'https://counter.yadro.ru/hit;'+ encodeURIComponent(LI_ID) +
      '?t26.1;r' +
      escape(typeof document.referrer !== 'undefined' ? document.referrer : '') +
      ((typeof screen === 'undefined')
        ? ''
        : ';s' +
          screen.width +
          '*' +
          screen.height +
          '*' +
          (screen.colorDepth ? screen.colorDepth : screen.pixelDepth)) +
      ';u' +
      escape(typeof document.URL !== 'undefined' ? document.URL : '') +
      ';' +
      Math.random();
    img.width = 88;
    img.height = 15;
    img.alt = 'LiveInternet';
    img.style.border = '0';
    img.style.verticalAlign = 'middle';

    a.appendChild(img);
    box.appendChild(a);
  }
})();
