<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $language === 'sw' ? 'Mkataba wa Awamu' : 'Installment Agreement' }} - {{ $plan->reference }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { box-sizing: border-box; }

        :root {
            --text: #334155;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --soft-2: #f1f5f9;
            --title: #0f172a;
            --accent: #f97316;
        }

        body {
            margin: 0;
            background: #eef2f7;
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11.5px;
            line-height: 1.6;
        }

        .toolbar {
            width: 210mm;
            max-width: calc(100% - 24px);
            margin: 18px auto 8px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
        }

        .toolbar-group {
            display: flex;
            gap: 7px;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: var(--title);
            border-radius: 7px;
            padding: 9px 12px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar .active {
            border-color: var(--accent);
            color: var(--accent);
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            background: #fff;
            padding: 14mm 14mm 12mm;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            border-bottom: 1px solid var(--line);
            padding-bottom: 14px;
        }

        .logo {
            display: block;
            width: auto;
            height: 42px;
            object-fit: contain;
        }

        .brand-note {
            margin-top: 6px;
            color: var(--muted);
            font-size: 10px;
        }

        .doc-meta {
            min-width: 230px;
            text-align: right;
        }

        .doc-type {
            margin: 0;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
        }

        .reference {
            margin: 2px 0 0;
            color: var(--title);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -.2px;
        }

        .meta-line {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .title-block {
            margin-top: 16px;
            text-align: center;
        }

        .title-block h1 {
            margin: 0;
            color: var(--title);
            font-size: 18px;
            letter-spacing: -.2px;
        }

        .title-block p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 10.5px;
        }

        .section {
            margin-top: 16px;
        }

        .section-label {
            margin: 0 0 7px;
            color: #94a3b8;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 8px;
        }

        .summary-box {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 9px 10px;
        }

        .summary-box span {
            display: block;
            color: #94a3b8;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-box strong {
            display: block;
            margin-top: 3px;
            color: var(--title);
            font-size: 11px;
        }

        .products-wrap {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .products {
            width: 100%;
            border-collapse: collapse;
        }

        .products th,
        .products td {
            padding: 8px 9px;
            text-align: left;
        }

        .products thead {
            background: var(--soft);
        }

        .products th {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid var(--line);
        }

        .products tbody tr + tr td {
            border-top: 1px solid var(--soft-2);
        }

        .products .number {
            text-align: right;
        }

        .products .center {
            text-align: center;
        }

        .sku {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 8.5px;
        }

        .terms {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 11px 12px;
            font-size: 10.5px;
            color: #475569;
        }

        .terms p {
            margin: 0 0 7px;
        }

        .terms p:last-child {
            margin-bottom: 0;
        }

        .terms strong {
            color: var(--title);
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 14px;
            margin-top: 18px;
            align-items: start;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 12px;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .signature {
            min-height: 78px;
        }

        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 40px;
            padding-top: 5px;
            color: var(--title);
            font-size: 9px;
            font-weight: 700;
        }

        .signature-name {
            margin-top: 2px;
            color: var(--muted);
            font-size: 8.5px;
        }

        .signature-date {
            margin-top: 5px;
            color: var(--muted);
            font-size: 8.5px;
        }

        .stamp-wrapper {
            margin-top: 14px;
        }

        .stamp-box {
            margin-top: 6px;
            width: 170px;
            height: 170px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .stamp-circle {
            width: 122px;
            height: 122px;
            border: 1px dashed #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 14px;
            color: var(--muted);
            font-size: 8.5px;
            line-height: 1.35;
        }

        .tracking {
            text-align: center;
        }

        .tracking svg {
            width: 100%;
            max-width: 250px;
            height: 58px;
        }

        .tracking-ref {
            margin-top: 4px;
            color: var(--title);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .tracking-note {
            margin: 5px auto 0;
            max-width: 240px;
            color: var(--muted);
            font-size: 8.5px;
            line-height: 1.4;
        }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            color: #94a3b8;
            font-size: 8px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .toolbar {
                display: none !important;
            }

            .paper {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                box-shadow: none;
            }
        }

        @media (max-width: 800px) {
            .paper {
                width: calc(100% - 20px);
                min-height: auto;
                padding: 22px;
            }

            .toolbar {
                width: calc(100% - 20px);
            }

            .summary-grid,
            .bottom-grid,
            .signatures {
                grid-template-columns: 1fr;
            }

            .stamp-box {
                width: 100%;
                max-width: 190px;
            }
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
    <button type="button" onclick="window.print()">{{ $language === 'sw' ? 'Chapisha / Hifadhi PDF' : 'Print / Save PDF' }}</button>
</div>

<main class="paper">
    <header class="header">
        <div>
            <img src="{{ asset('assets/prinx-logo.png') }}" alt="Prinx" class="logo">
            <div class="brand-note">{{ $language === 'sw' ? 'Kumbukumbu ya manunuzi kwa awamu' : 'Installment purchase record' }}</div>
        </div>

        <div class="doc-meta">
            <p class="doc-type">{{ $language === 'sw' ? 'Mkataba wa Awamu' : 'Installment Agreement' }}</p>
            <div class="reference">{{ $plan->reference }}</div>
            <div class="meta-line"><strong>{{ $language === 'sw' ? 'Tarehe' : 'Date' }}:</strong> {{ $generatedAt->format('d M Y') }}</div>
            <div class="meta-line"><strong>{{ $language === 'sw' ? 'Imeandaliwa na' : 'Prepared by' }}:</strong> {{ $staffName ?: 'Prinx Staff' }}</div>
        </div>
    </header>

    <section class="title-block">
        <h1>{{ $language === 'sw' ? 'Mkataba wa Manunuzi kwa Awamu' : 'Installment Purchase Agreement' }}</h1>
        <p>{{ $language === 'sw' ? 'Makubaliano rahisi kati ya mteja na Prinx' : 'Simple agreement between the customer and Prinx' }}</p>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Taarifa za Mkataba' : 'Agreement Details' }}</p>
        <div class="summary-grid">
            <div class="summary-box">
                <span>{{ $language === 'sw' ? 'Jina la Mteja' : 'Customer Name' }}</span>
                <strong>{{ $plan->customer_name }}</strong>
            </div>
            <div class="summary-box">
                <span>{{ $language === 'sw' ? 'Namba ya Simu' : 'Phone Number' }}</span>
                <strong>{{ $plan->customer_phone }}</strong>
            </div>
            <div class="summary-box">
                <span>{{ $language === 'sw' ? 'Kumbukumbu' : 'Reference' }}</span>
                <strong>{{ $plan->reference }}</strong>
            </div>
            <div class="summary-box">
                <span>{{ $language === 'sw' ? 'Bei Iliyokubaliwa' : 'Agreed Price' }}</span>
                <strong>{{ $money($plan->net_total) }}</strong>
            </div>
        </div>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Bidhaa za Makubaliano' : 'Agreement Products' }}</p>
        <div class="products-wrap">
            <table class="products">
                <thead>
                    <tr>
                        <th>{{ $language === 'sw' ? 'Bidhaa' : 'Product' }}</th>
                        <th class="center" style="width:70px">{{ $language === 'sw' ? 'Idadi' : 'Qty' }}</th>
                        <th class="number" style="width:120px">{{ $language === 'sw' ? 'Bei' : 'Amount' }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if($item->sku)
                                <span class="sku">SKU: {{ $item->sku }}</span>
                            @endif
                        </td>
                        <td class="center">{{ $item->quantity }}</td>
                        <td class="number"><strong>{{ $money($item->line_total) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <p class="section-label">{{ $language === 'sw' ? 'Masharti ya Makubaliano' : 'Agreement Terms' }}</p>
        <div class="terms">
            @if ($language === 'sw')
                <p>Mkataba huu unathibitisha kuwa <strong>{{ $plan->customer_name }}</strong>, mwenye namba ya simu <strong>{{ $plan->customer_phone }}</strong>, ameingia makubaliano na <strong>Prinx</strong> ya kulipia bidhaa zilizoainishwa hapo juu kwa awamu kwa bei iliyokubaliwa ya <strong>{{ $money($plan->net_total) }}</strong>.</p>
                <p>Mteja atalipa kiasi hicho kwa awamu. Baada ya kukamilisha malipo yote ya bei iliyokubaliwa, <strong>Prinx</strong> itamkabidhi mteja bidhaa yake au bidhaa zake.</p>
                <p>Iwapo mteja atabadilisha uamuzi wake kabla ya kuchukua bidhaa, anaweza kuchagua bidhaa nyingine inayopatikana dukani yenye thamani inayokaribiana. Tofauti yoyote ya bei italipwa au kuzingatiwa kabla ya bidhaa kukabidhiwa.</p>
                <p>Kwa kusaini hapa chini, mteja na mfanyakazi wa <strong>Prinx</strong> wanathibitisha kuwa taarifa hizi ni sahihi na wamekubaliana na masharti haya.</p>
            @else
                <p>This agreement confirms that <strong>{{ $plan->customer_name }}</strong>, phone number <strong>{{ $plan->customer_phone }}</strong>, has entered into an agreement with <strong>Prinx</strong> to pay for the product(s) listed above by installment at the agreed price of <strong>{{ $money($plan->net_total) }}</strong>.</p>
                <p>The customer will pay the agreed amount in installments. After the full agreed price has been paid, <strong>Prinx</strong> will hand over the purchased item or items to the customer.</p>
                <p>If the customer changes their mind before collecting the item, they may choose another item available in the shop with a similar value. Any price difference must be settled or accounted for before the replacement item is handed over.</p>
                <p>By signing below, the customer and <strong>Prinx</strong> staff confirm that the information above is correct and that they agree to these terms.</p>
            @endif
        </div>
    </section>

    <section class="bottom-grid">
        <div class="panel">
            <p class="section-label">{{ $language === 'sw' ? 'Sahihi na Muhuri' : 'Signatures and Stamp' }}</p>

            <div class="signatures">
                <div class="signature">
                    <div class="signature-line">{{ $language === 'sw' ? 'Sahihi ya Mteja' : 'Customer Signature' }}</div>
                    <div class="signature-name">{{ $plan->customer_name }}</div>
                    <div class="signature-date">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: ____________________</div>
                </div>

                <div class="signature">
                    <div class="signature-line">{{ $language === 'sw' ? 'Sahihi ya Mfanyakazi' : 'Staff Signature' }}</div>
                    <div class="signature-name">{{ $staffName ?: ($language === 'sw' ? 'Jina la mfanyakazi' : 'Staff name') }}</div>
                    <div class="signature-date">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: ____________________</div>
                </div>
            </div>

            <div class="stamp-wrapper">
                <p class="section-label">{{ $language === 'sw' ? 'Sehemu ya Muhuri wa Ofisi' : 'Office Stamp Area' }}</p>
                <div class="stamp-box">
                    <div class="stamp-circle">
                        {{ $language === 'sw' ? 'Weka muhuri wa mviringo hapa' : 'Apply round office stamp here' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="panel tracking">
            <p class="section-label">{{ $language === 'sw' ? 'Ufuatiliaji wa Mkataba' : 'Contract Tracking' }}</p>
            <svg id="tracking-barcode"></svg>
            <div class="tracking-ref">{{ $plan->reference }}</div>
            <p class="tracking-note">
                {{ $language === 'sw'
                    ? 'Barcode hii inaweza kuskaniwa au kumbukumbu hii inaweza kutumika kufuatilia mkataba huu kwa urahisi.'
                    : 'This barcode can be scanned, or this reference can be used, to quickly track this agreement.' }}
            </p>
        </div>
    </section>

    <div class="footer">
        <span>Prinx · {{ $plan->reference }}</span>
        <span>{{ $language === 'sw' ? 'Nakala ya kumbukumbu ya malipo kwa awamu' : 'Installment agreement record copy' }}</span>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        JsBarcode('#tracking-barcode', @json($plan->reference), {
            format: 'CODE128',
            displayValue: false,
            width: 1.6,
            height: 54,
            margin: 0
        });
    });
</script>
</body>
</html>
