<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $language === 'sw' ? 'Mkataba wa Awamu' : 'Installment Agreement' }} - {{ $plan->reference }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        :root { --orange: #f97316; --orange-dark: #ea580c; --orange-soft: #fff7ed; --slate: #0f172a; --text: #334155; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc; }
        body { margin: 0; background: #eef2f7; color: var(--text); font-family: Arial, Helvetica, sans-serif; font-size: 11.5px; line-height: 1.55; }
        .toolbar { width: 210mm; max-width: calc(100% - 24px); margin: 18px auto 8px; display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .toolbar-group { display: flex; gap: 7px; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; background: #fff; color: var(--slate); border-radius: 7px; padding: 9px 12px; font-size: 11px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar a:hover, .toolbar button:hover { border-color: #fdba74; color: var(--orange-dark); }
        .toolbar .active { border-color: var(--orange); background: var(--orange-soft); color: var(--orange-dark); }
        .toolbar .print { border-color: var(--orange); background: var(--orange); color: #fff; }
        .paper { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto 24px; overflow: hidden; background: #fff; padding: 12mm 14mm 10mm; box-shadow: 0 12px 40px rgba(15, 23, 42, .10); }
        .paper::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 5px; background: linear-gradient(90deg, var(--orange), #fb923c 45%, var(--slate) 45%, var(--slate)); }
        .header { display: flex; justify-content: space-between; gap: 28px; align-items: flex-start; border-bottom: 1px solid var(--line); padding: 5px 0 14px; }
        .logo { display: block; width: auto; height: 43px; object-fit: contain; }
        .brand-note { margin-top: 6px; color: var(--muted); font-size: 10px; }
        .doc-meta { min-width: 235px; text-align: right; }
        .eyebrow { margin: 0; color: var(--orange-dark); font-size: 9px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .reference { margin: 2px 0 0; color: var(--slate); font-size: 18px; font-weight: 700; letter-spacing: -.25px; }
        .meta-line { margin-top: 4px; color: var(--muted); font-size: 10px; }
        .hero { display: grid; grid-template-columns: 1.25fr .75fr; gap: 12px; margin-top: 13px; }
        .hero-title { border-radius: 8px; background: var(--slate); padding: 13px 15px; color: #fff; }
        .hero-title h1 { margin: 0; font-size: 18px; line-height: 1.25; letter-spacing: -.25px; }
        .hero-title p { margin: 4px 0 0; color: #cbd5e1; font-size: 10px; }
        .total-card { border: 1px solid #fed7aa; border-radius: 8px; background: linear-gradient(135deg, #fff7ed, #ffedd5); padding: 12px 14px; text-align: right; }
        .total-card span { display: block; color: #9a3412; font-size: 9px; font-weight: 800; letter-spacing: .10em; text-transform: uppercase; }
        .total-card strong { display: block; margin-top: 2px; color: var(--orange-dark); font-size: 18px; }
        .section { margin-top: 13px; }
        .section-label { margin: 0 0 6px; color: #94a3b8; font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .customer-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 7px; }
        .info-box { border: 1px solid var(--line); border-radius: 7px; background: #fff; padding: 8px 10px; }
        .info-box span { display: block; color: #94a3b8; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
        .info-box strong { display: block; margin-top: 2px; color: var(--slate); font-size: 11px; }
        .products-wrap { overflow: hidden; border: 1px solid var(--line); border-radius: 7px; }
        .products { width: 100%; border-collapse: collapse; }
        .products th, .products td { padding: 7px 9px; text-align: left; }
        .products thead { background: var(--slate); color: #fff; }
        .products th { font-size: 9px; font-weight: 700; }
        .products tbody tr + tr td { border-top: 1px solid #f1f5f9; }
        .products tbody tr:nth-child(even) { background: var(--soft); }
        .products .number { text-align: right; }
        .sku { display: block; margin-top: 1px; color: #94a3b8; font-size: 8.5px; }
        .agreement { border-left: 4px solid var(--orange); border-radius: 0 8px 8px 0; background: var(--orange-soft); padding: 10px 13px 9px; color: #475569; font-size: 10.5px; line-height: 1.55; }
        .agreement p { margin: 0 0 6px; }
        .agreement p:last-child { margin-bottom: 0; }
        .agreement strong { color: var(--slate); }
        .bottom-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 13px; margin-top: 13px; align-items: stretch; }
        .signature-panel { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .signature { min-height: 73px; }
        .signature-line { border-top: 1px solid #64748b; margin-top: 35px; padding-top: 4px; color: var(--slate); font-size: 9px; font-weight: 700; }
        .signature-name { margin-top: 2px; color: var(--muted); font-size: 8.5px; }
        .signature-date { margin-top: 5px; color: var(--muted); font-size: 8.5px; }
        .stamp { margin-top: 8px; height: 55px; border: 1px dashed #fb923c; border-radius: 7px; display: flex; align-items: center; justify-content: center; background: #fffaf5; color: #c2410c; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .tracking { display: flex; height: 100%; min-height: 145px; flex-direction: column; align-items: center; justify-content: center; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); padding: 9px 10px; text-align: center; }
        .tracking .section-label { margin-bottom: 2px; color: var(--orange-dark); }
        .tracking svg { width: 100%; max-width: 250px; height: 62px; }
        .tracking-ref { margin-top: 1px; color: var(--slate); font-size: 10px; font-weight: 800; letter-spacing: .08em; }
        .tracking-note { max-width: 240px; margin: 3px 0 0; color: var(--muted); font-size: 8px; line-height: 1.35; }
        .footer { margin-top: 11px; padding-top: 7px; border-top: 1px solid var(--line); color: #94a3b8; font-size: 8px; display: flex; justify-content: space-between; gap: 15px; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none !important; }
            .paper { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; }
        }
        @media (max-width: 800px) {
            .paper { width: calc(100% - 20px); min-height: auto; padding: 22px; }
            .toolbar { width: calc(100% - 20px); }
            .hero, .bottom-grid { grid-template-columns: 1fr; }
            .customer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    $money = static fn ($value) => 'TZS '.number_format((float) $value, 0);
@endphp

<div class="toolbar">
    <div class="toolbar-group">
        <a href="{{ route('admin.installments.contract', ['reference' => $plan->reference, 'language' => 'en']) }}" class="{{ $language === 'en' ? 'active' : '' }}">English</a>
        <a href="{{ route('admin.installments.contract', ['reference' => $plan->reference, 'language' => 'sw']) }}" class="{{ $language === 'sw' ? 'active' : '' }}">Swahili</a>
    </div>
    <button class="print" type="button" onclick="window.print()">{{ $language === 'sw' ? 'Chapisha / Hifadhi PDF' : 'Print / Save PDF' }}</button>
</div>

<main class="paper">
    <header class="header">
        <div>
            <img src="{{ asset('assets/prinx-logo.png') }}" alt="Prinx" class="logo">
            <div class="brand-note">{{ $language === 'sw' ? 'Mkataba wa malipo kwa awamu' : 'Installment purchase record' }}</div>
        </div>
        <div class="doc-meta">
            <p class="eyebrow">{{ $language === 'sw' ? 'Mkataba wa Awamu' : 'Installment Agreement' }}</p>
            <div class="reference">{{ $plan->reference }}</div>
            <div class="meta-line"><strong>{{ $language === 'sw' ? 'Tarehe' : 'Date' }}:</strong> {{ $generatedAt->format('d M Y') }}</div>
            <div class="meta-line"><strong>{{ $language === 'sw' ? 'Imeandaliwa na' : 'Prepared by' }}:</strong> {{ $staffName ?: 'Prinx Staff' }}</div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-title">
            <h1>{{ $language === 'sw' ? 'Mkataba wa Manunuzi kwa Awamu' : 'Installment Purchase Agreement' }}</h1>
            <p>{{ $language === 'sw' ? 'Makubaliano rahisi kati ya mteja na Prinx' : 'Simple agreement between the customer and Prinx' }}</p>
        </div>
        <div class="total-card">
            <span>{{ $language === 'sw' ? 'Bei Iliyokubaliwa' : 'Agreed Price' }}</span>
            <strong>{{ $money($plan->net_total) }}</strong>
        </div>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Taarifa za Mkataba' : 'Agreement Details' }}</p>
        <div class="customer-grid">
            <div class="info-box"><span>{{ $language === 'sw' ? 'Jina la Mteja' : 'Customer Name' }}</span><strong>{{ $plan->customer_name }}</strong></div>
            <div class="info-box"><span>{{ $language === 'sw' ? 'Namba ya Simu' : 'Phone Number' }}</span><strong>{{ $plan->customer_phone }}</strong></div>
            <div class="info-box"><span>{{ $language === 'sw' ? 'Kumbukumbu' : 'Reference' }}</span><strong>{{ $plan->reference }}</strong></div>
        </div>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Bidhaa za Makubaliano' : 'Agreement Products' }}</p>
        <div class="products-wrap">
            <table class="products">
                <thead>
                    <tr>
                        <th>{{ $language === 'sw' ? 'Bidhaa' : 'Product' }}</th>
                        <th style="width:60px; text-align:center">{{ $language === 'sw' ? 'Idadi' : 'Qty' }}</th>
                        <th class="number" style="width:115px">{{ $language === 'sw' ? 'Bei' : 'Amount' }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong>@if($item->sku)<span class="sku">SKU: {{ $item->sku }}</span>@endif</td>
                        <td style="text-align:center">{{ $item->quantity }}</td>
                        <td class="number"><strong>{{ $money($item->line_total) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Masharti ya Makubaliano' : 'Agreement Terms' }}</p>
        <div class="agreement">
            @if ($language === 'sw')
                <p>Mkataba huu unathibitisha kuwa <strong>{{ $plan->customer_name }}</strong>, mwenye namba ya simu <strong>{{ $plan->customer_phone }}</strong>, ameingia makubaliano na <strong>Prinx</strong> ya kulipia bidhaa zilizoainishwa hapo juu kwa awamu kwa bei iliyokubaliwa ya <strong>{{ $money($plan->net_total) }}</strong>.</p>
                <p>Mteja atalipa kiasi hicho kwa awamu. Baada ya kukamilisha malipo yote ya bei iliyokubaliwa, <strong>Prinx</strong> itamkabidhi mteja bidhaa yake/bidhaa zake.</p>
                <p>Iwapo mteja atabadilisha uamuzi wake kabla ya kuchukua bidhaa, anaweza kuchagua bidhaa nyingine inayopatikana dukani yenye thamani inayokaribiana. Tofauti yoyote ya bei italipwa au kuzingatiwa kabla ya bidhaa kukabidhiwa.</p>
                <p>Kwa kusaini hapa chini, mteja na mfanyakazi wa <strong>Prinx</strong> wanathibitisha kuwa taarifa hizi ni sahihi na wamekubaliana na masharti haya.</p>
            @else
                <p>This agreement confirms that <strong>{{ $plan->customer_name }}</strong>, phone number <strong>{{ $plan->customer_phone }}</strong>, has entered into an agreement with <strong>Prinx</strong> to pay for the product(s) listed above by installment at the agreed price of <strong>{{ $money($plan->net_total) }}</strong>.</p>
                <p>The customer will pay the agreed amount in installments. After the full agreed price has been paid, <strong>Prinx</strong> will hand over the purchased item(s) to the customer.</p>
                <p>If the customer changes their mind before collecting the item, they may choose another item available in the shop with a similar value. Any price difference must be settled or accounted for before the replacement item is handed over.</p>
                <p>By signing below, the customer and <strong>Prinx</strong> staff confirm that the information above is correct and that they agree to these terms.</p>
            @endif
        </div>
    </section>

    <section class="bottom-grid">
        <div class="signature-panel">
            <p class="section-label">{{ $language === 'sw' ? 'Uthibitisho' : 'Authorization' }}</p>
            <div class="signatures">
                <div class="signature">
                    <div class="signature-line">{{ $language === 'sw' ? 'Sahihi ya Mteja' : 'Customer Signature' }}</div>
                    <div class="signature-name">{{ $plan->customer_name }}</div>
                    <div class="signature-date">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: __________________</div>
                </div>
                <div class="signature">
                    <div class="signature-line">{{ $language === 'sw' ? 'Sahihi ya Mfanyakazi' : 'Staff Signature' }}</div>
                    <div class="signature-name">{{ $staffName ?: ($language === 'sw' ? 'Mfanyakazi wa Prinx' : 'Prinx Staff') }}</div>
                    <div class="signature-date">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: __________________</div>
                </div>
            </div>
            <div class="stamp">{{ $language === 'sw' ? 'Muhuri Rasmi wa Prinx' : 'Official Prinx Stamp' }}</div>
        </div>

        <div class="tracking">
            <p class="section-label">{{ $language === 'sw' ? 'Barcode ya Ufuatiliaji' : 'Tracking Barcode' }}</p>
            <svg id="installment-barcode" data-reference="{{ $plan->reference }}"></svg>
            <div class="tracking-ref">{{ $plan->reference }}</div>
            <p class="tracking-note">{{ $language === 'sw' ? 'Skani barcode hii au tumia kumbukumbu hii kutafuta mkataba kwenye mfumo wa Prinx.' : 'Scan this barcode or use this reference to find the installment agreement in the Prinx system.' }}</p>
        </div>
    </section>

    <footer class="footer">
        <span>Prinx · {{ $plan->reference }}</span>
        <span>{{ $language === 'sw' ? 'Nakala rasmi ya kumbukumbu ya malipo kwa awamu' : 'Official installment agreement record copy' }}</span>
    </footer>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const barcode = document.getElementById('installment-barcode');
        if (!barcode || typeof JsBarcode === 'undefined') return;

        JsBarcode(barcode, barcode.dataset.reference, {
            format: 'CODE128',
            lineColor: '#0f172a',
            background: '#f8fafc',
            width: 1.55,
            height: 44,
            margin: 2,
            displayValue: false,
        });
    });
</script>
</body>
</html>
