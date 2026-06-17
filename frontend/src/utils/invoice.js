/**
 * invoice.js — generate a self-contained printable invoice HTML
 * Opened in a new tab so @media print works perfectly without
 * interfering with the main app's Vue scoped styles.
 */

const IDR = v =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR',
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  }).format(Number(v) || 0)

const fmtDT = d => {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const METODE = {
  tunai: 'Tunai', qris: 'QRIS',
  transfer: 'Transfer Bank', kartu: 'Kartu Debit/Kredit',
}

export function printInvoice(tx) {
  const items = (tx.items ?? [])
    .map(item => `
      <tr>
        <td class="name">${item.drug_name}</td>
        <td class="center">${item.quantity}</td>
        <td class="right">${IDR(item.harga_jual)}</td>
        <td class="right bold">${IDR(item.subtotal)}</td>
      </tr>`)
    .join('')

  const diskonRow = Number(tx.diskon) > 0
    ? `<tr><td colspan="2">Diskon</td><td colspan="2" class="right red">- ${IDR(tx.diskon)}</td></tr>`
    : ''


  const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <title>Invoice ${tx.nomor_transaksi}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      color: #111;
      background: #fff;
      width: 80mm;
      margin: 0 auto;
      padding: 8px 6px;
      line-height: 1.55;
    }
    .center  { text-align: center; }
    .right   { text-align: right; }
    .bold    { font-weight: 700; }
    .red     { color: #dc2626; }
    .green   { color: #16a34a; }
    .name    { text-align: left; word-break: break-word; max-width: 120px; }

    /* Header */
    .hdr       { text-align: center; margin-bottom: 8px; }
    .hdr-name  { font-size: 16px; font-weight: 700; letter-spacing: .5px; }
    .hdr-sub   { font-size: 10px; color: #555; margin-top: 2px; }

    /* Dividers */
    .dash   { border-top: 1px dashed #888; margin: 6px 0; }
    .solid  { border-top: 1px solid #333;  margin: 4px 0; }

    /* Meta table */
    .meta { width: 100%; font-size: 11px; border-collapse: collapse; }
    .meta td { padding: 1px 0; vertical-align: top; }
    .meta td:last-child { text-align: right; font-weight: 600; word-break: break-word; }

    /* Items table */
    .items { width: 100%; border-collapse: collapse; font-size: 11px; }
    .items thead th {
      font-weight: 700; padding: 2px 1px;
      border-bottom: 1px solid #333;
    }
    .items thead th.right { text-align: right; }
    .items thead th.center { text-align: center; }
    .items tbody td {
      padding: 2px 1px;
      vertical-align: top;
      border-bottom: 1px dotted #ccc;
    }

    /* Totals table */
    .totals { width: 100%; border-collapse: collapse; font-size: 12px; }
    .totals td { padding: 2px 0; vertical-align: top; }
    .totals td:last-child { text-align: right; }
    .total-bold td { font-size: 14px; font-weight: 700; padding: 4px 0; }

    /* Footer */
    .ftr {
      text-align: center; font-size: 10px;
      color: #555; margin-top: 8px; line-height: 1.6;
    }
    .ftr .note { font-style: italic; color: #888; margin-top: 4px; }

    @media print {
      body { width: 80mm; margin: 0; padding: 4mm; }
      @page { size: 80mm auto; margin: 0; }
    }
  </style>
</head>
<body>

  <div class="hdr">
    <div class="hdr-name">Apotek Algenz</div>
    <div class="hdr-sub">Jl. Kesehatan No. 1 &middot; Telp. (0xxx) xxx-xxxx</div>
  </div>

  <div class="dash"></div>

  <table class="meta">
    <tr><td>No. Transaksi</td><td>${tx.nomor_transaksi}</td></tr>
    <tr><td>Tanggal</td><td>${fmtDT(tx.created_at)}</td></tr>
    <tr><td>Kasir</td><td>${tx.user?.name ?? '-'}</td></tr>
  </table>

  <div class="dash"></div>

  <table class="items">
    <thead>
      <tr>
        <th class="name">Nama Obat</th>
        <th class="center">Qty</th>
        <th class="right">Harga</th>
        <th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>${items}</tbody>
  </table>

  <div class="dash"></div>

  <table class="totals">
    <tr><td>Subtotal</td><td>${IDR(tx.subtotal)}</td></tr>
    ${diskonRow}
    <tr class="total-bold"><td>TOTAL</td><td>${IDR(tx.total)}</td></tr>
    <tr><td colspan="2"><div class="solid"></div></td></tr>
    <tr><td>Metode Bayar</td><td>${METODE[tx.metode_bayar] ?? tx.metode_bayar}</td></tr>
    <tr><td>Dibayar</td><td>${IDR(tx.bayar)}</td></tr>
    <tr class="bold green"><td>Kembalian</td><td class="right green">${IDR(tx.kembalian)}</td></tr>
  </table>

  <div class="dash"></div>

  <div class="ftr">
    <p>Terima kasih atas kepercayaan Anda.</p>
    <p>Simpan struk ini sebagai bukti pembelian.</p>
    ${tx.catatan ? `<p class="note">${tx.catatan}</p>` : ''}
  </div>

  <script>
    window.onload = function () {
      window.print()
      // close tab after print dialog closes (optional)
      window.onafterprint = function () { window.close() }
    }
  <\/script>
</body>
</html>`

  const win = window.open('', '_blank', 'width=400,height=600')
  win.document.write(html)
  win.document.close()
}
