(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var button = document.querySelector('[data-download-invoice]');
    var dataNode = document.getElementById('ct-invoice-data');
    if (!button || !dataNode) return;

    function clean(value) {
      return String(value == null ? '' : value)
        .normalize('NFKD')
        .replace(/[^\x20-\x7E\n]/g, ' ')
        .replace(/[ \t]+/g, ' ')
        .trim();
    }

    function money(paise) {
      return 'Rs. ' + (Number(paise || 0) / 100).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function wrap(text, font, size, width) {
      var paragraphs = clean(text).split('\n');
      var lines = [];
      paragraphs.forEach(function (paragraph) {
        var words = paragraph.split(' ').filter(Boolean);
        if (!words.length) { lines.push(''); return; }
        var line = '';
        words.forEach(function (word) {
          var candidate = line ? line + ' ' + word : word;
          if (line && font.widthOfTextAtSize(candidate, size) > width) {
            lines.push(line);
            line = word;
          } else {
            line = candidate;
          }
        });
        if (line) lines.push(line);
      });
      return lines;
    }

    function fit(text, font, size, width) {
      var value = clean(text) || '-';
      if (font.widthOfTextAtSize(value, size) <= width) return value;
      while (value.length > 1 && font.widthOfTextAtSize(value + '...', size) > width) value = value.slice(0, -1);
      return value + '...';
    }

    button.addEventListener('click', async function () {
      var originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = 'Preparing PDF…';
      try {
        if (!window.PDFLib) throw new Error('PDF library did not load.');
        var data = JSON.parse(dataNode.textContent);
        var PDFDocument = window.PDFLib.PDFDocument;
        var StandardFonts = window.PDFLib.StandardFonts;
        var rgb = window.PDFLib.rgb;
        var documentPdf = await PDFDocument.create();
        var regular = await documentPdf.embedFont(StandardFonts.Helvetica);
        var bold = await documentPdf.embedFont(StandardFonts.HelveticaBold);
        var pageSize = [595.28, 841.89];
        var margin = 42;
        var ink = rgb(0.12, 0.14, 0.15);
        var muted = rgb(0.39, 0.42, 0.44);
        var lineColor = rgb(0.84, 0.85, 0.86);
        var soft = rgb(0.97, 0.97, 0.97);
        var yellow = rgb(0.98, 0.65, 0.11);
        var page;
        var y;

        function addPage() {
          page = documentPdf.addPage(pageSize);
          y = pageSize[1] - margin;
          page.drawRectangle({ x: 0, y: pageSize[1] - 9, width: pageSize[0], height: 9, color: yellow });
          return page;
        }

        function drawText(text, x, baseline, options) {
          var settings = options || {};
          var font = settings.font || regular;
          var size = settings.size || 9;
          var value = settings.maxWidth ? fit(text, font, size, settings.maxWidth) : (clean(text) || '-');
          var drawX = x;
          if (settings.right) drawX = x - font.widthOfTextAtSize(value, size);
          page.drawText(value, { x: drawX, y: baseline, size: size, font: font, color: settings.color || ink });
        }

        function drawWrapped(text, x, baseline, width, options) {
          var settings = options || {};
          var font = settings.font || regular;
          var size = settings.size || 9;
          var lineHeight = settings.lineHeight || size + 3;
          var lines = wrap(text, font, size, width);
          lines.forEach(function (line, index) { drawText(line || ' ', x, baseline - index * lineHeight, settings); });
          return baseline - Math.max(1, lines.length) * lineHeight;
        }

        function drawTableHeader() {
          page.drawRectangle({ x: margin, y: y - 23, width: pageSize[0] - margin * 2, height: 23, color: soft });
          var columns = [margin + 5, margin + 25, margin + 170, margin + 218, margin + 278, margin + 350, margin + 414, pageSize[0] - margin - 5];
          ['#', 'Description', 'HSN', 'Qty', 'Rate', 'Taxable', 'Tax', 'Total'].forEach(function (label, index) {
            drawText(label, columns[index], y - 15, { font: bold, size: 7.5, color: muted, right: index === 7 });
          });
          y -= 23;
        }

        addPage();
        drawText('TAX INVOICE', margin, y - 17, { font: bold, size: 8, color: rgb(0.55, 0.36, 0) });
        drawText(data.company.name || 'ChemTech Trading Company', margin, y - 40, { font: bold, size: 18, maxWidth: 265 });
        drawWrapped(data.company.address || 'Registered address not configured', margin, y - 57, 285, { size: 8, color: muted, lineHeight: 10 });
        drawText('GSTIN: ' + (data.company.gstin || 'Not configured'), margin, y - 91, { size: 8 });

        var boxX = 365;
        page.drawRectangle({ x: boxX, y: y - 108, width: 188, height: 92, color: soft });
        drawText(data.invoice.number, boxX + 13, y - 38, { font: bold, size: 13, maxWidth: 162 });
        drawText('Invoice date', boxX + 13, y - 58, { size: 7.5, color: muted });
        drawText(data.invoice.date, boxX + 175, y - 58, { font: bold, size: 7.5, right: true });
        drawText('Due date', boxX + 13, y - 75, { size: 7.5, color: muted });
        drawText(data.invoice.due_date, boxX + 175, y - 75, { font: bold, size: 7.5, right: true });
        drawText('Status', boxX + 13, y - 92, { size: 7.5, color: muted });
        drawText(data.invoice.status, boxX + 175, y - 92, { font: bold, size: 7.5, right: true });
        y -= 126;
        page.drawLine({ start: { x: margin, y: y }, end: { x: pageSize[0] - margin, y: y }, thickness: 1.5, color: ink });

        y -= 24;
        drawText('BILL TO', margin, y, { font: bold, size: 7.5, color: rgb(0.55, 0.36, 0) });
        drawText('PAYMENT DETAILS', 330, y, { font: bold, size: 7.5, color: rgb(0.55, 0.36, 0) });
        drawText(data.customer.name, margin, y - 20, { font: bold, size: 11, maxWidth: 235 });
        drawWrapped(data.customer.address || 'Address not provided', margin, y - 35, 235, { size: 8, color: muted, lineHeight: 10 });
        drawText('GSTIN: ' + (data.customer.gstin || 'Unregistered'), margin, y - 70, { size: 8 });
        drawText(data.bank.name || 'Bank not configured', 330, y - 20, { font: bold, size: 9, maxWidth: 220 });
        drawText('Account: ' + (data.bank.account || '-'), 330, y - 38, { size: 8, color: muted });
        drawText('IFSC: ' + (data.bank.ifsc || '-'), 330, y - 54, { size: 8, color: muted });
        y -= 96;

        drawTableHeader();
        data.lines.forEach(function (item, index) {
          if (y < 175) { addPage(); y -= 18; drawTableHeader(); }
          var rowHeight = 37;
          var baseline = y - 15;
          drawText(index + 1, margin + 5, baseline, { size: 7.5 });
          drawText(item.description, margin + 25, baseline, { font: bold, size: 7.5, maxWidth: 135 });
          drawText(item.order_number, margin + 25, baseline - 12, { size: 6.5, color: muted });
          drawText(item.hsn || '-', margin + 170, baseline, { size: 7.5 });
          drawText(Number(item.quantity).toLocaleString('en-IN', { maximumFractionDigits: 3 }) + ' ' + item.unit, margin + 218, baseline, { size: 7.5 });
          drawText(money(item.rate_paise), margin + 278, baseline, { size: 7.5 });
          drawText(money(item.subtotal_paise), margin + 350, baseline, { size: 7.5 });
          drawText(money(item.tax_paise), margin + 414, baseline, { size: 7.5 });
          drawText((Number(item.tax_rate_bps || 0) / 100).toFixed(2) + '%', margin + 414, baseline - 12, { size: 6.5, color: muted });
          drawText(money(item.total_paise), pageSize[0] - margin - 5, baseline, { font: bold, size: 7.5, right: true });
          page.drawLine({ start: { x: margin, y: y - rowHeight }, end: { x: pageSize[0] - margin, y: y - rowHeight }, thickness: 0.6, color: lineColor });
          y -= rowHeight;
        });

        if (y < 220) addPage();
        y -= 26;
        page.drawRectangle({ x: margin, y: y - 48, width: 250, height: 48, color: rgb(1, 0.97, 0.88) });
        drawText('PAYMENT STATUS', margin + 12, y - 17, { font: bold, size: 7, color: rgb(0.46, 0.34, 0.14) });
        drawText(Number(data.totals.balance_paise) > 0 ? money(data.totals.balance_paise) + ' due' : 'Paid in full', margin + 12, y - 35, { font: bold, size: 11 });

        var totalX = 360;
        var totalRight = pageSize[0] - margin;
        var totalY = y - 5;
        function totalLine(label, amount, strong) {
          drawText(label, totalX, totalY, { font: strong ? bold : regular, size: strong ? 9 : 8, color: strong ? ink : muted });
          drawText(money(amount), totalRight, totalY, { font: strong ? bold : regular, size: strong ? 9 : 8, right: true });
          totalY -= strong ? 19 : 16;
        }
        totalLine('Taxable amount', data.totals.subtotal_paise, false);
        if (Number(data.totals.cgst_paise) > 0) {
          totalLine('CGST', data.totals.cgst_paise, false);
          totalLine('SGST', data.totals.sgst_paise, false);
        } else {
          totalLine('IGST', data.totals.igst_paise, false);
        }
        page.drawLine({ start: { x: totalX, y: totalY + 7 }, end: { x: totalRight, y: totalY + 7 }, thickness: 0.8, color: lineColor });
        totalLine('Invoice total', data.totals.total_paise, true);
        totalLine('Amount received', data.totals.paid_paise, false);
        totalLine('Balance due', data.totals.balance_paise, true);

        documentPdf.getPages().forEach(function (pdfPage, index) {
          pdfPage.drawText('Computer-generated tax invoice', { x: margin, y: 24, size: 6.5, font: regular, color: muted });
          var pageLabel = 'Page ' + (index + 1) + ' of ' + documentPdf.getPageCount();
          pdfPage.drawText(pageLabel, { x: pageSize[0] - margin - regular.widthOfTextAtSize(pageLabel, 6.5), y: 24, size: 6.5, font: regular, color: muted });
        });

        var bytes = await documentPdf.save();
        var blob = new Blob([bytes], { type: 'application/pdf' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = clean(data.filename || 'invoice.pdf').replace(/\s+/g, '-');
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        button.textContent = 'Downloaded';
      } catch (error) {
        button.textContent = 'Download failed';
      } finally {
        setTimeout(function () { button.textContent = originalLabel; button.disabled = false; }, 1400);
      }
    });
  });
})();
