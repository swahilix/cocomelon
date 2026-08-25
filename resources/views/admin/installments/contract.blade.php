<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $language === 'sw' ? 'Mkataba wa Awamu' : 'Installment Agreement' }} - {{ $plan->reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.55; }
        .toolbar { width: 210mm; max-width: calc(100% - 24px); margin: 18px auto 8px; display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .toolbar a, .toolbar button { border: 1px solid #d1d5db; background: #fff; color: #111827; border-radius: 6px; padding: 9px 12px; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar .active { border-color: #f97316; color: #ea580c; }
        .paper { width: 210mm; min-height: 297mm; margin: 0 auto 24px; background: #fff; padding: 16mm 17mm; box-shadow: 0 8px 30px rgba(15, 23, 42, .08); }
        .brand { font-size: 25px; font-weight: 800; letter-spacing: -.6px; }
        .muted { color: #6b7280; }
        .header { display: flex; justify-content: space-between; gap: 30px; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; }
        h1 { margin: 18px 0 4px; font-size: 18px; letter-spacing: .2px; text-align: center; text-transform: uppercase; }
        .subtitle { margin: 0 0 18px; text-align: center; color: #6b7280; }
        .info { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        .info td { border: 1px solid #d1d5db; padding: 8px 9px; vertical-align: top; }
        .info .label { width: 28%; background: #f9fafb; color: #4b5563; font-weight: 700; }
        .products { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
        .products th, .products td { border: 1px solid #d1d5db; padding: 7px 8px; text-align: left; }
        .products th { background: #f9fafb; font-size: 10px; text-transform: uppercase; color: #4b5563; }
        .products .number { text-align: right; }
        .agreement { margin-top: 15px; font-size: 12.5px; line-height: 1.7; }
        .agreement p { margin: 0 0 11px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 42px; }
        .signature { min-height: 88px; }
        .line { border-top: 1px solid #111827; margin-top: 45px; padding-top: 6px; font-weight: 700; }
        .stamp { margin-top: 32px; width: 190px; height: 90px; border: 1px dashed #9ca3af; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 9px; display: flex; justify-content: space-between; gap: 15px; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .paper { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
@php
    $money = static fn ($value) => 'TZS '.number_format((float) $value, 0);
@endphp

<div class="toolbar">
    <div>
        <a href="{{ route('admin.installments.contract', ['reference' => $plan->reference, 'language' => 'en']) }}" class="{{ $language === 'en' ? 'active' : '' }}">English</a>
        <a href="{{ route('admin.installments.contract', ['reference' => $plan->reference, 'language' => 'sw']) }}" class="{{ $language === 'sw' ? 'active' : '' }}">Swahili</a>
    </div>
    <button type="button" onclick="window.print()">{{ $language === 'sw' ? 'Chapisha / Hifadhi PDF' : 'Print / Save PDF' }}</button>
</div>

<main class="paper">
    <header class="header">
        <div>
            <div class="brand">Ptix</div>
            <div class="muted">{{ $language === 'sw' ? 'Mkataba wa malipo kwa awamu' : 'Installment purchase record' }}</div>
        </div>
        <div style="text-align:right">
            <strong>{{ $language === 'sw' ? 'Kumbukumbu' : 'Reference' }}:</strong> {{ $plan->reference }}<br>
            <span class="muted">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: {{ $generatedAt->format('d M Y') }}</span>
        </div>
    </header>

    <h1>{{ $language === 'sw' ? 'Mkataba wa Manunuzi kwa Awamu' : 'Installment Purchase Agreement' }}</h1>
    <p class="subtitle">{{ $language === 'sw' ? 'Makubaliano rahisi kati ya mteja na Ptix' : 'Simple agreement between the customer and Ptix' }}</p>

    <table class="info">
        <tr><td class="label">{{ $language === 'sw' ? 'Jina la mteja' : 'Customer name' }}</td><td>{{ $plan->customer_name }}</td></tr>
        <tr><td class="label">{{ $language === 'sw' ? 'Namba ya simu' : 'Phone number' }}</td><td>{{ $plan->customer_phone }}</td></tr>
        <tr><td class="label">{{ $language === 'sw' ? 'Bei iliyokubaliwa' : 'Agreed price' }}</td><td><strong>{{ $money($plan->net_total) }}</strong></td></tr>
    </table>

    <table class="products">
        <thead>
            <tr>
                <th>{{ $language === 'sw' ? 'Bidhaa' : 'Product' }}</th>
                <th style="width:60px">{{ $language === 'sw' ? 'Idadi' : 'Qty' }}</th>
                <th class="number" style="width:115px">{{ $language === 'sw' ? 'Bei' : 'Price' }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->product_name }} @if($item->sku)<span class="muted">({{ $item->sku }})</span>@endif</td>
                <td>{{ $item->quantity }}</td>
                <td class="number">{{ $money($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <section class="agreement">
        @if ($language === 'sw')
            <p>Mkataba huu unathibitisha kuwa <strong>{{ $plan->customer_name }}</strong>, mwenye namba ya simu <strong>{{ $plan->customer_phone }}</strong>, ameingia makubaliano na <strong>Ptix</strong> ya kulipia bidhaa zilizoainishwa hapo juu kwa awamu kwa bei iliyokubaliwa ya <strong>{{ $money($plan->net_total) }}</strong>.</p>
            <p>Mteja atalipa kiasi hicho kwa awamu. Baada ya kukamilisha malipo yote ya bei iliyokubaliwa, Ptix itamkabidhi mteja bidhaa yake/bidhaa zake.</p>
            <p>Iwapo mteja atabadilisha uamuzi wake kabla ya kuchukua bidhaa, anaweza kuchagua bidhaa nyingine inayopatikana dukani yenye thamani inayokaribiana. Tofauti yoyote ya bei italipwa au kuzingatiwa kabla ya bidhaa kukabidhiwa.</p>
            <p>Kwa kusaini hapa chini, mteja na mfanyakazi wa Ptix wanathibitisha kuwa taarifa hizi ni sahihi na wamekubaliana na masharti haya.</p>
        @else
            <p>This agreement confirms that <strong>{{ $plan->customer_name }}</strong>, phone number <strong>{{ $plan->customer_phone }}</strong>, has entered into an agreement with <strong>Ptix</strong> to pay for the product(s) listed above by installment at the agreed price of <strong>{{ $money($plan->net_total) }}</strong>.</p>
            <p>The customer will pay the agreed amount in installments. After the full agreed price has been paid, Ptix will hand over the purchased item(s) to the customer.</p>
            <p>If the customer changes their mind before collecting the item, they may choose another item available in the shop with a similar value. Any price difference must be settled or accounted for before the replacement item is handed over.</p>
            <p>By signing below, the customer and Ptix staff confirm that the information above is correct and that they agree to these terms.</p>
        @endif
    </section>

    <div class="signatures">
        <div class="signature">
            <div class="line">{{ $language === 'sw' ? 'Sahihi ya Mteja' : 'Customer Signature' }}</div>
            <div class="muted">{{ $plan->customer_name }}</div>
            <div style="margin-top:8px">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: ____________________</div>
        </div>
        <div class="signature">
            <div class="line">{{ $language === 'sw' ? 'Sahihi ya Mfanyakazi' : 'Staff Signature' }}</div>
            <div class="muted">{{ $staffName ?: ($language === 'sw' ? 'Jina la mfanyakazi' : 'Staff name') }}</div>
            <div style="margin-top:8px">{{ $language === 'sw' ? 'Tarehe' : 'Date' }}: ____________________</div>
        </div>
    </div>

    <div class="stamp">{{ $language === 'sw' ? 'Muhuri wa Ofisi' : 'Office Stamp' }}</div>

    <div class="footer">
        <span>Ptix · {{ $plan->reference }}</span>
        <span>{{ $language === 'sw' ? 'Nakala ya kumbukumbu ya malipo kwa awamu' : 'Installment agreement record copy' }}</span>
    </div>
</main>
</body>
</html>
