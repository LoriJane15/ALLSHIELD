<article class="sheet" data-form="justification">
    <div class="form-number">Form 25</div>
    <h1 class="official-title">JUSTIFICATION ON THE TIR AND CVC/CVIF</h1>
    <div class="line-grid"><strong>Name of FR/FVE:</strong><span class="line-value">{{ $draft['fr_name'] ?? '' }}</span></div>
    <div class="section-label">Overview of Firearms, Explosives, and Ammunition (FEAs)<br>(FEA&nbsp;&nbsp;&nbsp;&nbsp;)</div>
    @foreach(['kind' => 'Kind', 'caliber' => 'Caliber', 'make' => 'Make', 'serial_number' => 'Serial No.', 'declared_cost_valuation' => 'Declared Cost Valuation'] as $key => $label)<div class="line-grid"><span>{{ $label }}</span><span class="line-value">{{ $draft[$key] ?? '' }}</span></div>@endforeach
    <h2 class="section-label">Basis of Cost Valuation</h2>
    @foreach([
        'considerations' => ['1', 'What are the considerations for the cost valuation of the FEA?'],
        'classified_condition' => ['1.1', 'What condition was the firearms classified under?'],
        'condition_factors' => ['1.2', 'What specific factors affect its condition?'],
        'visible_damages' => ['1.3', 'Are there any visible damages? If yes, which part are they?'],
        'missing_parts' => ['1.4', 'If there are parts missing, which parts are they?'],
        'other_considerations' => ['2', 'What other the considerations,criteria or basis aside from the DILG-DND JMC No.1,s.2021.are used for the cost valuation?'],
        'market_analysis' => ['2.1', 'If based on market analysis, what references where used?Also,when was the market analysis conducted,and by whom?'],
        'other_source' => ['2.2', 'If others,briefly state the source and justification.Also, how was the credibility of this sourced assessed?'],
    ] as $key => [$number, $question])
        <section class="question"><div><span class="question-number">{{ $number }}</span>&nbsp;&nbsp;{{ $question }}</div><div class="answer-box">{{ $draft[$key] ?? '' }}</div></section>
    @endforeach
    <section class="question"><div><span class="question-number">3</span>&nbsp;&nbsp;Photo documentation of the firearms surrendered</div><div class="photo-box">@if($surrenderedPhoto)<img src="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $surrenderedPhoto]) }}" alt="Current Justification Section 3 photograph">@else EMPTY PHOTO AREA — No Justification Section 3 photograph has been uploaded. @endif</div></section>
    <section class="question"><div><span class="question-number">4</span>&nbsp;&nbsp;Photo of an example of the firearms surrendered that is good condition</div><div class="photo-box">@if($comparisonPhoto)<img src="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $comparisonPhoto]) }}" alt="Current draft comparison photograph">@else EMPTY PHOTO AREA — No current comparison draft photograph is available. @endif</div></section>
    <div class="certification-box">THIS IS TO CERTIFY that the justification provided in the Cost Valuation Certificate(CVC) / Cost Valuation of Inventoried Firearms(CVIF), as prepared by <span class="value">{{ $draft['prepared_by'] ?? '' }}</span>, has been reviewed by the PNP RSAO and found to be correct, accurate and sufficient to support the cost valuation reflected on the CVC/CVIF of the firearms enumerated above.</div>
    <div class="signature-block"><strong>Prepared by:</strong><div class="signature-line"></div><div class="printed-name">{{ $draft['prepared_by'] ?? '' }}</div><div class="signature-title">{{ $draft['prepared_by_position'] ?? '' }}</div></div>
    <div class="signature-block"><strong>Reviewed by:</strong><div class="signature-line"></div><div class="printed-name">{{ $draft['reviewed_by'] ?? '' }}</div><div class="signature-title">{{ $draft['reviewed_by_position'] ?? '' }}</div></div>
</article>
