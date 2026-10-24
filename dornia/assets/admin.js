// پنل مدیریت: سایزها و پیش‌نمایش قیمت
(function () {
  var toEn = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
  var num = function (s) { var n = parseFloat(toEn(s).replace(/[,\s]/g, '').replace('٫', '.').replace('/', '.')); return isNaN(n) ? 0 : n; };
  var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };

  var box = document.getElementById('variants');
  var add = document.getElementById('addVariant');
  if (box && add) {
    add.addEventListener('click', function () {
      var row = box.querySelector('.variant-row').cloneNode(true);
      row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      box.appendChild(row);
    });
    box.addEventListener('click', function (e) {
      if (!e.target.matches('[data-remove-row]')) return;
      var rows = box.querySelectorAll('.variant-row');
      if (rows.length > 1) e.target.closest('.variant-row').remove();
      else rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
    });
  }

  var lp = document.getElementById('livePrice');
  if (lp) {
    var calc = function () {
      var w = num(document.getElementById('f-weight').value);
      var oj = num(document.getElementById('f-ojrat').value);
      var k = num(document.getElementById('f-karat').value);
      var gold = w * num(lp.dataset.gold) * k / 18;
      var ojrat = gold * oj / 100;
      var profit = (gold + ojrat) * num(lp.dataset.profit) / 100;
      var tax = (ojrat + profit) * num(lp.dataset.tax) / 100;
      var r = num(lp.dataset.round) || 1;
      var total = Math.ceil((gold + ojrat + profit + tax) / r) * r;
      lp.querySelector('b').textContent = w > 0 ? fa(total.toLocaleString('en-US')) + ' تومان' : '—';
    };
    ['f-weight', 'f-ojrat', 'f-karat'].forEach(function (id) { document.getElementById(id).addEventListener('input', calc); });
    calc();
  }
})();
