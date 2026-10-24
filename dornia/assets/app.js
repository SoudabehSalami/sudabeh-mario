// رفتارهای سمت کاربر: گالری، انتخاب سایز، تب‌ها، ماشین‌حساب
(function () {
  var faDigits = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
  var toEn = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
  var num = function (s) { var n = parseFloat(toEn(s).replace(/[,\s]/g, '').replace('٫', '.')); return isNaN(n) ? 0 : n; };
  var toman = function (n) { return faDigits(Math.round(n).toLocaleString('en-US')) + ' تومان'; };
  var weightFa = function (w) { return faDigits(String(Math.round(w * 100) / 100)) + ' گرم'; };

  // گالری
  document.querySelectorAll('.thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('mainImg').src = b.dataset.src;
      document.querySelectorAll('.thumbs button').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
    });
  });

  // انتخاب سایز و به‌روزرسانی قیمت
  var opts = window.DORNIA_OPTIONS;
  var bd = document.getElementById('breakdown');
  if (opts && bd) {
    document.querySelectorAll('input[name=variant]').forEach(function (r) {
      r.addEventListener('change', function () {
        var o = opts.find(function (x) { return String(x.idx) === r.value; });
        if (!o) return;
        bd.querySelectorAll('[data-k]').forEach(function (td) {
          var k = td.dataset.k;
          td.textContent = k === 'weight' ? weightFa(o.weight) : toman(o.price[k]);
        });
        document.getElementById('finalPrice').textContent = toman(o.price.total);
      });
    });
  }

  // تعداد
  document.querySelectorAll('.qty button').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = b.parentNode.querySelector('input');
      var v = (parseInt(input.value, 10) || 1) + parseInt(b.dataset.q, 10);
      var max = parseInt(input.max, 10) || 99;
      input.value = Math.min(max, Math.max(1, v));
    });
  });

  // تب‌ها
  document.querySelectorAll('.tab-btns button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.tab-btns button, .tab').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
      document.getElementById(b.dataset.tab).classList.add('active');
    });
  });

  // نمایش اطلاعات کارت فقط وقتی کارت به کارت انتخاب شده
  var cardPay = document.getElementById('cardPay');
  if (cardPay) {
    var sync = function () {
      var c = document.querySelector('input[name=payment_method]:checked');
      cardPay.style.display = c && c.value === 'card' ? '' : 'none';
    };
    document.querySelectorAll('input[name=payment_method]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }

  // ماشین‌حساب
  var calc = document.getElementById('calc');
  if (calc) {
    var ids = ['c-gold', 'c-weight', 'c-ojrat', 'c-karat', 'c-profit', 'c-tax'];
    var run = function () {
      var g = num(document.getElementById('c-gold').value);
      var w = num(document.getElementById('c-weight').value);
      var oj = num(document.getElementById('c-ojrat').value);
      var k = num(document.getElementById('c-karat').value);
      var pr = num(document.getElementById('c-profit').value);
      var tx = num(document.getElementById('c-tax').value);
      var gold = w * g * (k / 18);
      var ojrat = gold * oj / 100;
      var profit = (gold + ojrat) * pr / 100;
      var tax = (ojrat + profit) * tx / 100;
      document.getElementById('r-gold').textContent = toman(gold);
      document.getElementById('r-ojrat').textContent = toman(ojrat);
      document.getElementById('r-profit').textContent = toman(profit);
      document.getElementById('r-tax').textContent = toman(tax);
      document.getElementById('r-total').textContent = toman(gold + ojrat + profit + tax);
    };
    ids.forEach(function (id) { document.getElementById(id).addEventListener('input', run); });
    run();
  }
})();
