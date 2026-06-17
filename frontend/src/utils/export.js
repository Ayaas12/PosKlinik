/**
 * Export utility — plain Excel (exceljs) + PDF (jsPDF + autoTable)
 * Apotek Algenz POS
 *
 * Excel: clean, bold headers, column widths, number format
 * PDF:   branded header, summary box, autoTable
 */
import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import ExcelJS from 'exceljs'

// ─── Shared helpers ───────────────────────────────────────────────────────────

const IDR = v =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR',
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  }).format(Number(v) || 0)

// Safe numeric coercion — handles strings, null, undefined
const n = v => Number(v) || 0

const fmtDate = d => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

const fmtDateLong = d => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
  })
}

const today = () => fmtDateLong(new Date())

const periodLabel = (from, to) => {
  if (from && to) return `Periode: ${fmtDateLong(from)} — ${fmtDateLong(to)}`
  if (from) return `Mulai: ${fmtDateLong(from)}`
  return ''
}

// ─── Excel helpers ────────────────────────────────────────────────────────────

async function saveExcel(workbook, filename) {
  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename + '.xlsx'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function addTitleRows(worksheet, title, period) {
  worksheet.addRow([title])
  if (period) worksheet.addRow([period])
  worksheet.addRow([])
}

function boldRow(worksheet, rowNumber) {
  worksheet.getRow(rowNumber).font = { bold: true }
}

// ─── PDF base ─────────────────────────────────────────────────────────────────

function basePDF(title, subtitle) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })

  doc.setFillColor(29, 78, 216)
  doc.rect(0, 0, 210, 30, 'F')

  doc.setTextColor(255, 255, 255)
  doc.setFontSize(16)
  doc.setFont('helvetica', 'bold')
  doc.text('Apotek Algenz', 14, 12)

  doc.setFontSize(10)
  doc.setFont('helvetica', 'normal')
  doc.text(title, 14, 21)

  if (subtitle) {
    doc.setFontSize(8)
    doc.text(subtitle, 14, 27)
  }

  doc.setFontSize(8)
  doc.setTextColor(199, 210, 254)
  doc.text(`Dicetak: ${today()}`, 196, 10, { align: 'right' })

  doc.setTextColor(30, 41, 59)
  return doc
}

const PDF_HEAD  = { fillColor: [29, 78, 216], textColor: 255, fontStyle: 'bold', fontSize: 9 }
const PDF_ALT   = { fillColor: [248, 250, 252] }
const PDF_FOOT  = { fillColor: [241, 245, 249], textColor: [30, 41, 59], fontStyle: 'bold' }
const PDF_STYLE = { fontSize: 8.5, cellPadding: 2.5 }

// ═══════════════════════════════════════════════════════════════════════════════
// SALES REPORT
// ═══════════════════════════════════════════════════════════════════════════════

export function exportSalesPDF({ summary, topItems, transactions, dateFrom, dateTo }) {
  const doc = basePDF('Laporan Penjualan', periodLabel(dateFrom, dateTo))
  const s = summary ?? {}
  let y = 36

  // Summary band
  doc.setFillColor(219, 234, 254)
  doc.roundedRect(14, y, 182, 22, 2, 2, 'F')
  const cols = [
    ['Total Transaksi',       n(s.total_transaksi)],
    ['Total Pendapatan',      IDR(s.total_pendapatan)],
    ['Total Diskon',          IDR(s.total_diskon)],
    ['Rata-rata/Transaksi',   IDR(s.rata_rata)],
  ]
  cols.forEach(([label, val], i) => {
    const x = 18 + i * 46
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7.5)
    doc.setTextColor(71, 85, 105)
    doc.text(label, x, y + 9)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9)
    doc.setTextColor(30, 41, 59)
    doc.text(String(val), x, y + 17)
  })
  y += 28

  // Top items table
  if (topItems?.length) {
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(30, 41, 59)
    doc.text('Produk Terlaris', 14, y)
    y += 2
    const totalQty = topItems.reduce((a, i) => a + n(i.total_qty), 0)
    const totalRev = topItems.reduce((a, i) => a + n(i.total_revenue), 0)
    autoTable(doc, {
      startY: y,
      head: [['#', 'Nama Obat', 'Qty Terjual', 'Total Pendapatan']],
      body: topItems.map((item, i) => [
        i + 1, item.drug_name, n(item.total_qty), IDR(item.total_revenue),
      ]),
      foot: [['', 'JUMLAH TOTAL', totalQty, IDR(totalRev)]],
      headStyles: PDF_HEAD, alternateRowStyles: PDF_ALT, footStyles: PDF_FOOT, styles: PDF_STYLE,
      columnStyles: { 0: { cellWidth: 10, halign: 'center' }, 2: { halign: 'right' }, 3: { halign: 'right' } },
      footStyles: { ...PDF_FOOT, halign: 'right' },
    })
    y = doc.lastAutoTable.finalY + 8
  }

  // Transactions table
  if (transactions?.length) {
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.text('Daftar Transaksi', 14, y)
    y += 2
    const totalTx = transactions.reduce((a, t) => a + n(t.total), 0)
    autoTable(doc, {
      startY: y,
      head: [['No. Transaksi', 'Tanggal', 'Kasir', 'Total', 'Metode', 'Status']],
      body: transactions.map(tx => [
        tx.nomor_transaksi, fmtDate(tx.created_at),
        tx.user?.name ?? '-', IDR(tx.total),
        tx.metode_bayar, tx.status,
      ]),
      foot: [['', '', 'JUMLAH TOTAL', IDR(totalTx), '', '']],
      headStyles: PDF_HEAD, alternateRowStyles: PDF_ALT, footStyles: PDF_FOOT, styles: PDF_STYLE,
      columnStyles: { 3: { halign: 'right' } },
    })
  }

  doc.save(`laporan-penjualan-${dateFrom ?? 'all'}.pdf`)
}

export async function exportSalesXLSX({ summary, topItems, transactions, dateFrom, dateTo }) {
  const wb = new ExcelJS.Workbook()
  const s = summary ?? {}
  const period = periodLabel(dateFrom, dateTo)

  // ── Sheet 1: Ringkasan ──
  const wsSummary = wb.addWorksheet('Ringkasan')
  addTitleRows(wsSummary, 'LAPORAN PENJUALAN — APOTEK ALGENZ', period)
  wsSummary.addRow(['Keterangan', 'Nilai'])
  boldRow(wsSummary, wsSummary.rowCount)
  wsSummary.addRow(['Total Transaksi', n(s.total_transaksi)])
  wsSummary.addRow(['Total Pendapatan (Rp)', n(s.total_pendapatan)])
  wsSummary.addRow(['Total Diskon (Rp)', n(s.total_diskon)])
  wsSummary.addRow(['Rata-rata/Transaksi (Rp)', n(s.rata_rata)])
  wsSummary.columns = [{ width: 30 }, { width: 20 }]

  // ── Sheet 2: Produk Terlaris ──
  if (topItems?.length) {
    const totalQty = topItems.reduce((a, i) => a + n(i.total_qty), 0)
    const totalRev = topItems.reduce((a, i) => a + n(i.total_revenue), 0)
    const ws = wb.addWorksheet('Produk Terlaris')
    addTitleRows(ws, 'PRODUK TERLARIS — APOTEK ALGENZ', period)
    ws.addRow(['No', 'Nama Obat', 'Qty Terjual', 'Total Pendapatan (Rp)'])
    boldRow(ws, ws.rowCount)
    topItems.forEach((item, i) => {
      ws.addRow([i + 1, item.drug_name, n(item.total_qty), n(item.total_revenue)])
    })
    ws.addRow(['JUMLAH TOTAL', '', totalQty, totalRev])
    boldRow(ws, ws.rowCount)
    ws.columns = [{ width: 6 }, { width: 38 }, { width: 14 }, { width: 22 }]
  }

  // ── Sheet 3: Transaksi ──
  if (transactions?.length) {
    const totalTx = transactions.reduce((a, t) => a + n(t.total), 0)
    const ws = wb.addWorksheet('Transaksi')
    addTitleRows(ws, 'DAFTAR TRANSAKSI — APOTEK ALGENZ', period)
    ws.addRow(['No. Transaksi', 'Tanggal', 'Kasir', 'Total (Rp)', 'Metode', 'Status'])
    boldRow(ws, ws.rowCount)
    transactions.forEach(tx => {
      ws.addRow([
        tx.nomor_transaksi,
        fmtDate(tx.created_at),
        tx.user?.name ?? '-',
        n(tx.total),
        tx.metode_bayar,
        tx.status,
      ])
    })
    ws.addRow(['JUMLAH TOTAL', '', '', totalTx, '', ''])
    boldRow(ws, ws.rowCount)
    ws.columns = [
      { width: 22 }, { width: 14 }, { width: 20 },
      { width: 18 }, { width: 14 }, { width: 14 },
    ]
  }

  await saveExcel(wb, `laporan-penjualan-${dateFrom ?? 'all'}`)
}

// ═══════════════════════════════════════════════════════════════════════════════
// STOCK REPORT
// ═══════════════════════════════════════════════════════════════════════════════

export function exportStockPDF({ summary, drugs }) {
  const doc = basePDF('Laporan Stok Obat', `Per tanggal: ${today()}`)
  const s = summary ?? {}
  let y = 36

  doc.setFillColor(219, 234, 254)
  doc.roundedRect(14, y, 182, 22, 2, 2, 'F')
  const sItems = [
    ['Total Jenis Obat',    n(s.total_obat)],
    ['Nilai Stok',          IDR(s.total_nilai_stok)],
    ['Stok Menipis',        n(s.low_stock)],
    ['Hampir Kadaluarsa',   n(s.near_expiry)],
  ]
  sItems.forEach(([label, val], i) => {
    const x = 18 + i * 46
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7.5)
    doc.setTextColor(71, 85, 105)
    doc.text(label, x, y + 9)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9)
    doc.setTextColor(30, 41, 59)
    doc.text(String(val), x, y + 17)
  })
  y += 28

  const totalStok  = (drugs ?? []).reduce((a, d) => a + n(d.stok), 0)
  const totalNilai = (drugs ?? []).reduce((a, d) => a + n(d.harga_beli) * n(d.stok), 0)

  autoTable(doc, {
    startY: y,
    head: [['Kode', 'Nama Obat', 'Kategori', 'Harga Beli', 'Harga Jual', 'Stok', 'Min', 'Nilai Stok', 'Kadaluarsa', 'Status']],
    body: (drugs ?? []).map(d => {
      let st = 'Normal'
      if (d.stok === 0) st = 'Habis'
      else if (d.stok <= d.stok_minimum) st = 'Menipis'
      return [d.kode_obat, d.name, d.category?.name ?? '-',
        IDR(d.harga_beli), IDR(d.harga_jual), n(d.stok), n(d.stok_minimum),
        IDR(n(d.harga_beli) * n(d.stok)), fmtDate(d.tanggal_kadaluarsa), st]
    }),
    foot: [['', 'JUMLAH TOTAL', '', '', '', totalStok, '', IDR(totalNilai), '', '']],
    headStyles: PDF_HEAD, alternateRowStyles: PDF_ALT, footStyles: PDF_FOOT,
    styles: { fontSize: 7.5, cellPadding: 2 },
    columnStyles: { 3:{halign:'right'}, 4:{halign:'right'}, 5:{halign:'right'}, 7:{halign:'right'} },
    didParseCell(data) {
      if (data.section === 'body' && data.column.index === 9) {
        if (data.cell.raw === 'Habis')   data.cell.styles.textColor = [220, 38, 38]
        if (data.cell.raw === 'Menipis') data.cell.styles.textColor = [180, 83, 9]
      }
    },
  })

  doc.save('laporan-stok.pdf')
}

export async function exportStockXLSX({ summary, drugs }) {
  const wb = new ExcelJS.Workbook()
  const s = summary ?? {}

  const wsSummary = wb.addWorksheet('Ringkasan')
  addTitleRows(wsSummary, 'LAPORAN STOK OBAT — APOTEK ALGENZ', `Per tanggal: ${today()}`)
  wsSummary.addRow(['Keterangan', 'Nilai'])
  boldRow(wsSummary, wsSummary.rowCount)
  wsSummary.addRow(['Total Jenis Obat', n(s.total_obat)])
  wsSummary.addRow(['Nilai Stok Total (Rp)', n(s.total_nilai_stok)])
  wsSummary.addRow(['Stok Menipis', n(s.low_stock)])
  wsSummary.addRow(['Hampir Kadaluarsa', n(s.near_expiry)])
  wsSummary.columns = [{ width: 28 }, { width: 20 }]

  const totalStok  = (drugs ?? []).reduce((a, d) => a + n(d.stok), 0)
  const totalNilai = (drugs ?? []).reduce((a, d) => a + n(d.harga_beli) * n(d.stok), 0)

  const wsDetail = wb.addWorksheet('Detail Stok')
  addTitleRows(wsDetail, 'DETAIL STOK OBAT — APOTEK ALGENZ', `Per tanggal: ${today()}`)
  wsDetail.addRow([
    'Kode Barang', 'Nama Barang', 'Kategori', 'Supplier',
    'Harga Beli (Rp)', 'Harga Jual (Rp)', 'Jumlah Stok', 'Stok Minimum',
    'Nilai Stok (Rp)', 'Tgl Kadaluarsa', 'Kondisi Stok',
  ])
  boldRow(wsDetail, wsDetail.rowCount)
  ;(drugs ?? []).forEach(d => {
    let status = 'Normal'
    if (d.stok === 0) status = 'Habis'
    else if (d.stok <= d.stok_minimum) status = 'Menipis'
    wsDetail.addRow([
      d.kode_obat, d.name,
      d.category?.name ?? '-', d.supplier?.name ?? '-',
      n(d.harga_beli), n(d.harga_jual),
      n(d.stok), n(d.stok_minimum),
      n(d.harga_beli) * n(d.stok),
      fmtDate(d.tanggal_kadaluarsa),
      status,
    ])
  })
  wsDetail.addRow(['JUMLAH TOTAL', '', '', '', '', '', totalStok, '', totalNilai, '', ''])
  boldRow(wsDetail, wsDetail.rowCount)
  wsDetail.columns = [
    { width: 14 }, { width: 30 }, { width: 18 }, { width: 20 },
    { width: 16 }, { width: 16 }, { width: 12 }, { width: 12 },
    { width: 18 }, { width: 14 }, { width: 14 },
  ]

  await saveExcel(wb, 'laporan-stok')
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROFIT / LOSS
// ═══════════════════════════════════════════════════════════════════════════════

export function exportProfitLossPDF({ summary, items, dateFrom, dateTo }) {
  const doc = basePDF('Laporan Laba Rugi', periodLabel(dateFrom, dateTo))
  const s = summary ?? {}
  let y = 36

  doc.setFillColor(240, 253, 244)
  doc.roundedRect(14, y, 182, 22, 2, 2, 'F')
  const sItems = [
    ['Total Pendapatan', IDR(s.total_pendapatan)],
    ['Total HPP',        IDR(s.total_hpp)],
    ['Laba Kotor',       IDR(s.laba_kotor)],
    ['Margin',           n(s.margin) + '%'],
  ]
  sItems.forEach(([label, val], i) => {
    const x = 18 + i * 46
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7.5)
    doc.setTextColor(71, 85, 105)
    doc.text(label, x, y + 9)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9)
    doc.setTextColor(30, 41, 59)
    doc.text(String(val), x, y + 17)
  })
  y += 28

  const totQty    = (items ?? []).reduce((a, i) => a + n(i.total_qty), 0)
  const totRev    = (items ?? []).reduce((a, i) => a + n(i.total_revenue), 0)
  const totCost   = (items ?? []).reduce((a, i) => a + n(i.total_cost), 0)
  const totProfit = (items ?? []).reduce((a, i) => a + n(i.gross_profit), 0)

  autoTable(doc, {
    startY: y,
    head: [['#', 'Nama Obat', 'Qty', 'Pendapatan', 'HPP', 'Laba Kotor', 'Margin %']],
    body: (items ?? []).map((item, i) => [
      i + 1, item.drug_name, n(item.total_qty),
      IDR(item.total_revenue), IDR(item.total_cost), IDR(item.gross_profit),
      n(item.total_revenue) > 0
        ? ((n(item.gross_profit) / n(item.total_revenue)) * 100).toFixed(1) + '%'
        : '0.0%',
    ]),
    foot: [['', 'JUMLAH TOTAL', totQty, IDR(totRev), IDR(totCost), IDR(totProfit), '']],
    headStyles: PDF_HEAD, alternateRowStyles: PDF_ALT, footStyles: PDF_FOOT, styles: PDF_STYLE,
    columnStyles: { 0:{cellWidth:8,halign:'center'}, 2:{halign:'right'}, 3:{halign:'right'}, 4:{halign:'right'}, 5:{halign:'right'}, 6:{halign:'right'} },
  })

  doc.save(`laporan-laba-rugi-${dateFrom ?? 'all'}.pdf`)
}

export async function exportProfitLossXLSX({ summary, items, dateFrom, dateTo }) {
  const wb = new ExcelJS.Workbook()
  const s = summary ?? {}
  const period = periodLabel(dateFrom, dateTo)

  const wsSummary = wb.addWorksheet('Ringkasan')
  addTitleRows(wsSummary, 'LAPORAN LABA RUGI — APOTEK ALGENZ', period)
  wsSummary.addRow(['Keterangan', 'Nilai (Rp)'])
  boldRow(wsSummary, wsSummary.rowCount)
  wsSummary.addRow(['Total Pendapatan', n(s.total_pendapatan)])
  wsSummary.addRow(['Total HPP', n(s.total_hpp)])
  wsSummary.addRow(['Laba Kotor', n(s.laba_kotor)])
  wsSummary.addRow(['Margin (%)', n(s.margin)])
  wsSummary.columns = [{ width: 28 }, { width: 22 }]

  const totQty    = (items ?? []).reduce((a, i) => a + n(i.total_qty), 0)
  const totRev    = (items ?? []).reduce((a, i) => a + n(i.total_revenue), 0)
  const totCost   = (items ?? []).reduce((a, i) => a + n(i.total_cost), 0)
  const totProfit = (items ?? []).reduce((a, i) => a + n(i.gross_profit), 0)

  const wsDetail = wb.addWorksheet('Detail')
  addTitleRows(wsDetail, 'DETAIL LABA RUGI PER PRODUK — APOTEK ALGENZ', period)
  wsDetail.addRow(['No', 'Nama Obat', 'Qty', 'Pendapatan (Rp)', 'HPP (Rp)', 'Laba Kotor (Rp)', 'Margin (%)'])
  boldRow(wsDetail, wsDetail.rowCount)
  ;(items ?? []).forEach((item, i) => {
    const margin = n(item.total_revenue) > 0
      ? +((n(item.gross_profit) / n(item.total_revenue)) * 100).toFixed(2)
      : 0
    wsDetail.addRow([i + 1, item.drug_name, n(item.total_qty),
      n(item.total_revenue), n(item.total_cost), n(item.gross_profit), margin])
  })
  wsDetail.addRow(['JUMLAH TOTAL', '', totQty, totRev, totCost, totProfit, ''])
  boldRow(wsDetail, wsDetail.rowCount)
  wsDetail.columns = [
    { width: 6 }, { width: 36 }, { width: 10 },
    { width: 20 }, { width: 20 }, { width: 20 }, { width: 12 },
  ]

  await saveExcel(wb, `laporan-laba-rugi-${dateFrom ?? 'all'}`)
}

// ═══════════════════════════════════════════════════════════════════════════════
// BEST SELLERS
// ═══════════════════════════════════════════════════════════════════════════════

export function exportBestSellerPDF({ topItems, dateFrom, dateTo }) {
  const doc = basePDF('Produk Terlaris', periodLabel(dateFrom, dateTo))

  const totalQty = (topItems ?? []).reduce((a, i) => a + n(i.total_qty), 0)
  const totalRev = (topItems ?? []).reduce((a, i) => a + n(i.total_revenue), 0)

  autoTable(doc, {
    startY: 36,
    head: [['Peringkat', 'Nama Obat', 'Qty Terjual', 'Total Pendapatan']],
    body: (topItems ?? []).map((item, i) => [
      i + 1, item.drug_name, n(item.total_qty), IDR(item.total_revenue),
    ]),
    foot: [['', 'JUMLAH TOTAL', totalQty, IDR(totalRev)]],
    headStyles: PDF_HEAD, alternateRowStyles: PDF_ALT, footStyles: PDF_FOOT,
    styles: { fontSize: 9, cellPadding: 3 },
    columnStyles: { 0:{cellWidth:20,halign:'center'}, 2:{halign:'right'}, 3:{halign:'right'} },
  })

  doc.save(`produk-terlaris-${dateFrom ?? 'all'}.pdf`)
}

export async function exportBestSellerXLSX({ topItems, dateFrom, dateTo }) {
  const totalQty = (topItems ?? []).reduce((a, i) => a + n(i.total_qty), 0)
  const totalRev = (topItems ?? []).reduce((a, i) => a + n(i.total_revenue), 0)

  const wb = new ExcelJS.Workbook()
  const ws = wb.addWorksheet('Produk Terlaris')
  addTitleRows(ws, 'PRODUK TERLARIS — APOTEK ALGENZ', periodLabel(dateFrom, dateTo))
  ws.addRow(['Peringkat', 'Nama Obat', 'Qty Terjual', 'Total Pendapatan (Rp)'])
  boldRow(ws, ws.rowCount)
  ;(topItems ?? []).forEach((item, i) => {
    ws.addRow([i + 1, item.drug_name, n(item.total_qty), n(item.total_revenue)])
  })
  ws.addRow(['JUMLAH TOTAL', '', totalQty, totalRev])
  boldRow(ws, ws.rowCount)
  ws.columns = [{ width: 12 }, { width: 40 }, { width: 14 }, { width: 24 }]

  await saveExcel(wb, `produk-terlaris-${dateFrom ?? 'all'}`)
}
