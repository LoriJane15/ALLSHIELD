<article class="sheet" data-form="tir">
    <h1 class="official-title">TECHNICAL INSPECTION REPORT</h1>
    <div class="two-column"><div class="line-grid"><strong>Date</strong><span class="line-value">{{ $draft['date'] ?? '' }}</span></div><div class="line-grid"><strong>Time</strong><span class="line-value">{{ $draft['time'] ?? '' }}</span></div></div>
    <div class="line-grid"><strong>NAME OF FR/FVE</strong><span class="line-value">{{ $draft['fr_name'] ?? '' }}</span></div>
    <div style="margin-top: 5mm">
        @foreach(['kind' => 'KIND', 'caliber' => 'CALIBER', 'make' => 'MAKE', 'serial_number' => 'SERIAL NO.'] as $key => $label)
            <div class="line-grid"><strong>{{ $label }}</strong><span class="line-value">{{ $draft[$key] ?? '' }}</span></div>
        @endforeach
    </div>
    <table class="official-table"><thead><tr><th>PARTS</th><th>SERVICEABLE</th><th>UNSERVICEABLE</th></tr></thead><tbody>
        @forelse(($draft['parts'] ?? []) as $row)<tr><td>{{ $row['part'] ?? '' }}</td><td>{{ $row['serviceable'] ?? '' }}</td><td>{{ $row['unserviceable'] ?? '' }}</td></tr>@empty @for($i = 0; $i < 5; $i++)<tr><td>&nbsp;</td><td></td><td></td></tr>@endfor @endforelse
    </tbody></table>
    <div class="section-label">Condition:</div>
    <div class="choice-row">@foreach(['GOOD', 'FAIR', 'SCRAP'] as $choice)<span><span class="checkbox">{{ ($draft['condition'] ?? null) === $choice ? '✓' : '' }}</span> {{ $choice }}</span>@endforeach</div>
    <div class="section-label">Remarks:</div><div class="line-value value" style="min-height: 22mm">{{ $draft['remarks'] ?? '' }}</div>
    <div class="signature-block"><strong>INSPECTED BY:</strong><div class="signature-line"></div><div class="signature-caption">(Signature over printed name)</div><div class="signature-title">AFP/PNP Officer</div></div>
    <div class="signature-block"><strong>NOTED BY:</strong><div class="signature-line"></div><div class="signature-caption">(Signature over printed name)</div><div class="signature-title">AFP/ PNP COMMANDING OFFICER</div></div>
</article>
