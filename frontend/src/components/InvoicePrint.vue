<script setup>
/**
 * InvoicePrint.vue
 * Renders a thermal-style invoice.
 * Used both inside the modal preview and as the @media print target.
 */
import { formatRupiah } from '@/utils/format'

const props = defineProps({
  transaction: { type: Object, required: true },
})

function fmtDateTime(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const metodeLabel = {
  tunai: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer Bank',
  kartu: 'Kartu Debit/Kredit',
}
</script>

<template>
  <!-- id="invoice-print" is the print target -->
  <div id="invoice-print" class="invoice-wrap">

    <!-- Header -->
    <div class="invoice-header">
      <div class="apotek-name">💊 Apotek Algenz</div>
      <div class="apotek-sub">Jl. Kesehatan No. 1 · Telp. (0xxx) xxx-xxxx</div>
      <div class="divider-dash"></div>
    </div>

    <!-- Transaction meta -->
    <div class="invoice-meta">
      <div class="meta-row">
        <span>No. Transaksi</span>
        <span class="meta-val">{{ transaction.nomor_transaksi }}</span>
      </div>
      <div class="meta-row">
        <span>Tanggal</span>
        <span class="meta-val">{{ fmtDateTime(transaction.created_at) }}</span>
      </div>
      <div class="meta-row">
        <span>Kasir</span>
        <span class="meta-val">{{ transaction.user?.name ?? '-' }}</span>
      </div>
    </div>

    <div class="divider-dash"></div>

    <!-- Items -->
    <div class="invoice-items">
      <div class="items-header">
        <span class="col-name">Nama Obat</span>
        <span class="col-qty">Qty</span>
        <span class="col-price">Harga</span>
        <span class="col-sub">Subtotal</span>
      </div>
      <div class="divider-solid"></div>
      <div
        v-for="item in transaction.items"
        :key="item.id"
        class="item-row"
      >
        <span class="col-name item-name">{{ item.drug_name }}</span>
        <span class="col-qty">{{ item.quantity }}</span>
        <span class="col-price">{{ formatRupiah(item.harga_jual) }}</span>
        <span class="col-sub">{{ formatRupiah(item.subtotal) }}</span>
      </div>
    </div>

    <div class="divider-dash"></div>

    <!-- Totals -->
    <div class="invoice-totals">
      <div class="total-row">
        <span>Subtotal</span>
        <span>{{ formatRupiah(transaction.subtotal) }}</span>
      </div>
      <div v-if="Number(transaction.diskon) > 0" class="total-row diskon-row">
        <span>Diskon</span>
        <span>- {{ formatRupiah(transaction.diskon) }}</span>
      </div>
      <div class="divider-solid"></div>
      <div class="total-row total-bold">
        <span>TOTAL</span>
        <span>{{ formatRupiah(transaction.total) }}</span>
      </div>
      <div class="total-row">
        <span>Metode Bayar</span>
        <span>{{ metodeLabel[transaction.metode_bayar] ?? transaction.metode_bayar }}</span>
      </div>
      <div class="total-row">
        <span>Dibayar</span>
        <span>{{ formatRupiah(transaction.bayar) }}</span>
      </div>
      <div class="total-row kembalian-row">
        <span>Kembalian</span>
        <span>{{ formatRupiah(transaction.kembalian) }}</span>
      </div>
    </div>

    <div class="divider-dash"></div>

    <!-- Footer -->
    <div class="invoice-footer">
      <p>Terima kasih atas kepercayaan Anda.</p>
      <p>Simpan struk ini sebagai bukti pembelian.</p>
      <p class="footer-note">{{ transaction.catatan }}</p>
    </div>

  </div>
</template>

<style scoped>
/* ── Base invoice styles (used in modal preview + print) ── */
.invoice-wrap {
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  color: #111;
  width: 100%;
  max-width: 320px;
  margin: 0 auto;
  padding: 12px 4px;
  line-height: 1.5;
}

.invoice-header { text-align: center; margin-bottom: 6px; }
.apotek-name    { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; }
.apotek-sub     { font-size: 10px; color: #555; margin-top: 2px; }

.divider-dash  { border-top: 1px dashed #888; margin: 6px 0; }
.divider-solid { border-top: 1px solid #333;  margin: 4px 0; }

.invoice-meta  { font-size: 11px; }
.meta-row      { display: flex; justify-content: space-between; gap: 4px; padding: 1px 0; }
.meta-val      { font-weight: 600; text-align: right; max-width: 60%; word-break: break-word; }

/* Items table */
.invoice-items { font-size: 11px; }
.items-header  {
  display: grid;
  grid-template-columns: 1fr 28px 72px 72px;
  font-weight: 700;
  padding: 2px 0;
  gap: 2px;
}
.item-row {
  display: grid;
  grid-template-columns: 1fr 28px 72px 72px;
  padding: 2px 0;
  gap: 2px;
  border-bottom: 1px dotted #ddd;
}
.col-name  { text-align: left; overflow: hidden; }
.item-name { font-weight: 500; white-space: normal; word-break: break-word; }
.col-qty   { text-align: center; }
.col-price { text-align: right; }
.col-sub   { text-align: right; font-weight: 600; }

/* Totals */
.invoice-totals { font-size: 12px; }
.total-row {
  display: flex;
  justify-content: space-between;
  padding: 2px 0;
}
.total-bold    { font-weight: 700; font-size: 14px; padding: 4px 0; }
.diskon-row    { color: #dc2626; }
.kembalian-row { font-weight: 600; color: #16a34a; }

/* Footer */
.invoice-footer {
  text-align: center;
  font-size: 10px;
  color: #555;
  margin-top: 6px;
  line-height: 1.6;
}
.footer-note {
  margin-top: 4px;
  font-style: italic;
  color: #888;
  min-height: 4px;
}
</style>
