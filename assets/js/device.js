/* Device detection for the auth pages (login, signup, forgot, reset).
   Runs in <head> so the page is tagged before first paint.

   It tags <html> with:
     data-device = phone | tablet | desktop
     data-orient = portrait | landscape
     data-touch  = true | false
     data-os     = ios | android | other
   and sets --vh (1% of the real visible height) so the layout fits the screen
   even where 100vh is wrong (mobile browser toolbars). --vh is refreshed only
   when the width changes (rotation / resize), so opening the on-screen
   keyboard does not make the page jump.

   Detection is feature based (touch + screen size), with the user agent used
   only as a hint, so it keeps working on new devices and resized windows. */
(function () {
  var html = document.documentElement;
  var ua = navigator.userAgent || '';
  var lastW = -1;

  function mq(q) {
    return !!(window.matchMedia && window.matchMedia(q).matches);
  }

  function detect() {
    var w = window.innerWidth;
    var h = window.innerHeight;
    var touch = mq('(pointer: coarse)') || mq('(hover: none)') || (navigator.maxTouchPoints || 0) > 0;
    var uaPhone = /iPhone|iPod|Android.*Mobile|Windows Phone|Mobile/i.test(ua);
    var shortSide = Math.min(w, h);

    var device = 'desktop';
    if (w < 600 || (shortSide < 600 && (touch || uaPhone) && w < 960)) {
      device = 'phone';          // portrait phones, narrow windows, and phones turned sideways
    } else if (w < 960) {
      device = 'tablet';
    }

    var ios = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && (navigator.maxTouchPoints || 0) > 1);
    var android = /Android/i.test(ua);

    html.setAttribute('data-device', device);
    html.setAttribute('data-orient', w > h ? 'landscape' : 'portrait');
    html.setAttribute('data-touch', touch ? 'true' : 'false');
    html.setAttribute('data-os', ios ? 'ios' : android ? 'android' : 'other');

    if (w !== lastW) {
      lastW = w;
      html.style.setProperty('--vh', (h / 100) + 'px');
    }
  }

  detect();
  window.addEventListener('resize', detect);
  window.addEventListener('orientationchange', function () { setTimeout(detect, 250); });
})();
