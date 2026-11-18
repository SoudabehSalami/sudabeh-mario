/**
 * داشبورد بانک‌ها — Google Apps Script web app
 * فایل‌ها: Code.gs (همین فایل) و Index.html
 * هر بار که صفحه باز یا به‌روز می‌شود، تب‌های حساب از دو فایل گوگل‌شیت خوانده می‌شوند.
 */
const RIAL_SS = '1V2OTZRzvdZuNcqNYmeR1WQB8FBrJNbQ1Rq8nfkqCnhw'; // Bugloos ACs 1405
const FX_SS = '1ndbrVJyqDGkB9J0qYEjqBZDI_0SgCpWotuiBOLB_y3I';   // Revolut

// [dataset id, tab name, spreadsheet, first data row]
const ACCOUNTS = [
  ['rial_AR', "A(R)", 'RIAL', 5],
  ['rial_BR', "B(R)", 'RIAL', 5],
  ['rial_CR', "C(R)", 'RIAL', 5],
  ['rial_AT', "A(T)", 'RIAL', 5],
  ['rial_BTG', "B(T.G)", 'RIAL', 5],
  ['rial_CTG', "C(T.G)", 'RIAL', 5],
  ['rial_armin', "Armin", 'RIAL', 5],
  ['rial_armita', "Armita", 'RIAL', 5],
  ['rial_nariman', "Nariman", 'RIAL', 5],
  ['rial_sadegh', "Sadegh", 'RIAL', 5],
  ['rial_heeman', "Heeman", 'RIAL', 5],
  ['rial_alireza', "Alireza", 'RIAL', 5],
  ['rial_samaneh', "Samaneh", 'RIAL', 5],
  ['rial_mhmd', "Mhmd", 'RIAL', 5],
  ['rial_tsob', "Tankhah Sobhaneh", 'RIAL', 5],
  ['rial_tbirad', "Tankhah Birad", 'RIAL', 5],
  ['rial_thr', "Tankhah HR", 'RIAL', 5],
  ['rial_melli', "Soudabeh Melli", 'RIAL', 5],
  ['rial_foroosh', "Foroosh", 'RIAL', 5],
  ['rial_sandogh', "Sandogh", 'RIAL', 5],
  ['eur_revolut', "Revolut1", 'FX', 2],
  ['eur_abn', "ABN", 'FX', 2],
  ['eur_abnh', "ABN Bugloos Holding ", 'FX', 2],
  ['eur_ics', "ICS", 'FX', 2],
  ['eur_zeraat', "Zeraat Euro", 'FX', 2],
  ['try_zeraat', "Zeraat lir", 'FX', 2]
];

function doGet() {
  return HtmlService.createHtmlOutputFromFile('Index')
    .setTitle('داشبورد بانک‌ها')
    .addMetaTag('viewport', 'width=device-width, initial-scale=1');
}

function getData() {
  const books = { RIAL: SpreadsheetApp.openById(RIAL_SS), FX: SpreadsheetApp.openById(FX_SS) };
  const data = {};
  const missing = [];
  ACCOUNTS.forEach(function (a) {
    const id = a[0], tab = a[1], book = books[a[2]], first = a[3];
    const sh = book.getSheetByName(tab);
    if (!sh) { missing.push(tab.trim()); return; }
    const last = sh.getLastRow();
    const values = last >= first ? sh.getRange(first, 1, last - first + 1, 8).getDisplayValues() : [];
    data[id] = { range: "'" + tab + "'!A" + first + ':H', values: values };
  });
  return JSON.stringify({ data: data, missing: missing });
}
