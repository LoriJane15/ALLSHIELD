<article class="sheet" data-form="cvif">
    <h1 class="official-title">COST VALUATION OF INVENTORIED FIREARMS</h1>
    <p class="fixed-copy">THIS IS TO CERTIFY that <span class="inline-line value">{{ $draft['fr_name'] ?? '' }}</span>; with RM No. <span class="inline-line value">{{ $draft['rm_number'] ?? '' }}</span></p>
    <div>Name of FR or FVE</div><div>The owner of the firearm is described below:</div>
    @foreach(['kind' => 'Kind', 'caliber' => 'Caliber', 'make' => 'Make', 'serial_number' => 'Serial No.'] as $key => $label)<div class="line-grid"><span>{{ $label }}</span><span class="line-value">{{ $draft[$key] ?? '' }}</span></div>@endforeach
    <p class="fixed-copy">THAT THE ABOVE-DESCRIBED firearms has undergone inventory and technical on <span class="inline-line value">{{ $draft['date_of_inspection'] ?? '' }}</span> at <span class="inline-line value">{{ $draft['place_of_inspection'] ?? '' }}</span> with the following findings:</p>
    <div class="two-column"><div style="text-align:center">Date of Inspection</div><div style="text-align:center">Place of Inspection</div></div>
    @foreach(['condition' => 'Condition', 'serviceability' => 'Serviceability', 'remarks' => 'Remarks'] as $key => $label)<div class="line-grid"><span>{{ $label }}</span><span class="line-value value">{{ $draft[$key] ?? '' }}</span></div>@endforeach
    <p class="fixed-copy" style="margin-top: 8mm">THAT UPON DUE DELIBERATION, The Valuation Committee finds the amount</p>
    <div class="line-value value" style="text-align:center">{{ $draft['amount_in_words'] ?? '' }}</div><div style="text-align:center">(Amount in words)</div>
    <div class="line-grid"><strong>(Php:</strong><span class="line-value">{{ $draft['cost_valuation'] ?? '' }}</span></div><div style="text-align:center">) as the cost valuation for the said firearm.</div>
    <p>DONE this <span class="inline-line value">{{ $draft['done_date'] ?? '' }}</span> at <span class="inline-line value">{{ $draft['done_place'] ?? '' }}</span></p>
    <div style="text-align:center;font-weight:800;margin-top:7mm">CERTIFIED BY:</div>
    @foreach(['PNP REPRESENTATIVE', 'AFP REPRESENTATIVE', 'DILG REPRESENTATIVE'] as $title)<div class="signature-block"><div class="signature-line"></div><div class="signature-caption">(Signature over Printed Name)</div><div class="signature-title">{{ $title }}</div></div>@endforeach
</article>
