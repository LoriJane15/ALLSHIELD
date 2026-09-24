<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ $document->document_type->label() }} — Draft</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .screen-controls { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 18px; background: #fff; border-bottom: 1px solid #cbd5e1; }
        .screen-controls .warning { margin-right: auto; color: #92400e; font-weight: 700; }
        .screen-controls a, .screen-controls button { border: 1px solid #475569; border-radius: 4px; background: #fff; color: #0f172a; padding: 7px 12px; text-decoration: none; cursor: pointer; }
        .document-stack { padding: 16px; }
        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto 16px; padding: 15mm 14mm 14mm; background: #fff; box-shadow: 0 1px 8px rgba(15, 23, 42, .2); overflow: visible; }
        .sheet::before { content: "DRAFT — NOT FINAL"; display: block; margin: 0 0 8mm; border: 2px solid #991b1b; padding: 4px 8px; color: #991b1b; font-size: 14px; font-weight: 800; letter-spacing: 1.5px; text-align: center; }
        .print-draft-mark { display: none; }
        .official-title { margin: 0 0 7mm; font-size: 16px; font-weight: 800; text-align: center; text-transform: uppercase; }
        .form-number { text-align: right; font-weight: 700; }
        .line-grid { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 4px 8px; margin: 3px 0; }
        .line-value { min-height: 18px; border-bottom: 1px solid #111; white-space: pre-wrap; overflow-wrap: anywhere; }
        .inline-line { display: inline-block; min-width: 120px; min-height: 16px; border-bottom: 1px solid #111; vertical-align: bottom; white-space: pre-wrap; overflow-wrap: anywhere; }
        .fixed-copy { line-height: 1.45; white-space: normal; }
        .value { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        .official-table { width: 100%; margin: 5mm 0; border-collapse: collapse; table-layout: fixed; }
        .official-table th, .official-table td { min-height: 22px; border: 1px solid #111; padding: 4px; vertical-align: top; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        .official-table th { font-weight: 800; text-align: center; }
        .official-table thead { display: table-header-group; }
        .official-table tr { break-inside: avoid; page-break-inside: avoid; }
        .section-label { margin: 5mm 0 2mm; font-weight: 800; }
        .choice-row { display: flex; flex-wrap: wrap; gap: 16mm; margin: 4mm 0; }
        .checkbox { display: inline-grid; width: 13px; height: 13px; place-items: center; border: 1px solid #111; font-size: 11px; line-height: 1; }
        .signature-block { break-inside: avoid; margin: 10mm auto 0; text-align: center; }
        .signature-line { width: 62mm; height: 9mm; margin: 0 auto; border-bottom: 1px solid #111; }
        .printed-name { min-height: 16px; font-weight: 700; white-space: pre-wrap; overflow-wrap: anywhere; }
        .signature-caption { font-style: italic; }
        .signature-title { font-weight: 800; }
        .question { break-inside: avoid; margin: 4mm 0; }
        .question-number { font-weight: 800; }
        .answer-box { min-height: 18mm; margin-top: 2mm; border: 1px solid #111; padding: 5px; white-space: pre-wrap; overflow-wrap: anywhere; }
        .photo-box { break-inside: avoid; min-height: 72mm; margin: 3mm 0 7mm; border: 2px dashed #555; display: grid; place-items: center; padding: 8px; color: #555; text-align: center; }
        .photo-box img { display: block; max-height: 68mm; max-width: 100%; object-fit: contain; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; }
        .certification-box { border: 1px solid #111; padding: 5px; line-height: 1.4; }
        .continuation { break-before: page; page-break-before: always; }
        .legend-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #111; }
        .legend-grid > div { padding: 6px; border-right: 1px solid #111; }
        .legend-grid > div:last-child { border-right: 0; }
        .empty-state { width: min(680px, calc(100% - 32px)); margin: 40px auto; padding: 30px; background: #fff; border: 1px solid #cbd5e1; text-align: center; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }
            .document-stack { padding: 0; }
            .sheet { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
            .sheet:last-child { break-after: auto; page-break-after: auto; }
            .sheet::before { visibility: hidden; }
            .print-draft-mark { position: fixed; z-index: 20; top: 4mm; left: 14mm; right: 14mm; display: block; border: 2px solid #991b1b; padding: 4px 8px; background: #fff; color: #991b1b; font-size: 14px; font-weight: 800; letter-spacing: 1.5px; text-align: center; }
        }
        @media screen and (max-width: 850px) {
            .document-stack { overflow-x: auto; }
            .sheet { margin-left: 0; transform-origin: top left; }
        }
    </style>
</head>
<body>
<nav class="screen-controls no-print" aria-label="Draft preview controls">
    <span class="warning">Private 39th IB working draft — not a final submission</span>
    <a href="{{ route('ib39.fea.documents.draft.edit', [$fea, $document]) }}">Back to Editor</a>
    <a href="{{ route('ib39.fea.documents.draft.preview', [$fea, $document]) }}">Preview</a>
    @if($draft !== null)<button type="button" onclick="window.print()">Print Draft</button>@endif
</nav>

@if($draft === null)
    <main class="empty-state"><strong style="display:block;margin-bottom:18px;color:#991b1b;letter-spacing:1.5px">DRAFT — NOT FINAL</strong><h1>Save a draft first</h1><p>No saved draft exists for this document. Return to the editor and save before previewing or printing.</p></main>
@else
    <main class="document-stack">
        <div class="print-draft-mark" aria-hidden="true">DRAFT — NOT FINAL</div>
        @include('ib39.fea.documents.'.strtolower($document->document_type->value), ['draft' => $draft])
    </main>
    @if($printMode)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
@endif
</body>
</html>
