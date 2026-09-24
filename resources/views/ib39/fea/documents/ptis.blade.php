<article class="sheet" data-form="ptis-main">
    <h1 class="official-title">PROPERTY TURN-IN SLIP</h1>
    <table class="official-table" style="margin-top:0"><tbody>
        <tr><th style="width:8%">TO</th><td colspan="3"><strong>SUPPLY CLASSIFICATION OFFICER</strong><br>{{ $draft['supply_classification_officer'] ?? '' }}</td><th style="width:18%">PAGE ___ OF<br>Voucher Number</th><td>{{ $draft['page_of_voucher_number'] ?? '' }}</td></tr>
        <tr><th>FROM</th><td colspan="3"><strong>Organization/ Unit:</strong><br>{{ $draft['organization_unit'] ?? '' }}</td><th>Turn-In Slip Number:</th><td>{{ $draft['turn_in_slip_number'] ?? '' }}</td></tr>
    </tbody></table>
    <table class="official-table"><thead><tr><th style="width:7%">ITEM</th><th style="width:10%">STOCK</th><th style="width:26%">NOMENCLATURE</th><th style="width:9%">UNIT</th><th style="width:8%">QTY</th><th style="width:22%">REMARKS</th><th style="width:18%">SYMBOL<br>ACTION</th></tr></thead><tbody>
        @forelse(($draft['items'] ?? []) as $row)<tr><td>{{ $row['item'] ?? '' }}</td><td>{{ $row['stock'] ?? '' }}</td><td>{{ $row['nomenclature'] ?? '' }}</td><td>{{ $row['unit'] ?? '' }}</td><td>{{ $row['quantity'] ?? '' }}</td><td>{{ $row['remarks'] ?? '' }}</td><td>{{ $row['symbol_action'] ?? '' }}</td></tr>@empty @for($i = 0; $i < 9; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor @endforelse
    </tbody></table>
    <div class="line-grid"><strong>BASIS:</strong><span class="line-value"></span></div>
    <div class="signature-block" style="text-align:left;margin-left:0"><strong>TURNED IN BY:</strong><div class="signature-line" style="margin-left:35mm"></div><div class="printed-name" style="margin-left:35mm;width:62mm;text-align:center">{{ $draft['turned_in_by'] ?? '' }}</div><div class="signature-caption" style="margin-left:35mm;width:62mm;text-align:center">(Name, please specify if FR or FVE)</div></div>
    @foreach(['RECEIVED BY', 'INSPECTED BY'] as $label)<div class="signature-block" style="text-align:left;margin-left:0"><strong>{{ $label }}:</strong><div class="signature-line" style="margin-left:35mm"></div><div class="signature-caption" style="margin-left:35mm;width:62mm;text-align:center">(signature above printed name)</div></div>@endforeach
    <div class="line-grid"><strong>NOTE:</strong><span class="line-value value">{{ $draft['note'] ?? '' }}</span></div>
</article>

<article class="sheet continuation" data-form="ptis-continuation">
    <h2 class="official-title" style="font-size:14px">PROPERTY TURN-IN SLIP — CONTINUATION</h2>
    <div class="legend-grid">
        <div><strong>LEGEND FOR REMARKS</strong><br><br>FWT&nbsp;&nbsp; Unserviceable due to wear and tear<br>SER&nbsp;&nbsp; Serviceable<br>R/C&nbsp;&nbsp; Unserviceable Statement<br>R/S&nbsp;&nbsp; Unserviceable Report on survey<br>EXC&nbsp;&nbsp; In Excess of Authorized Allowance</div>
        <div>
            <div class="certification-box">I HEREBY CERTIFY that the article/s listed herein are turned-in under the circumstances indicated therein:<br><br><strong>FOR THE COMMANDING OFFICER:</strong><div class="signature-line"></div><div style="text-align:center">(signature above printed name)<br><strong>AFP/ PNP Representative</strong></div><br><strong>CONFIRMED BY:</strong><div class="signature-line"></div><div style="text-align:center">(signature above printed name)<br><strong>(DILG Representative)</strong></div></div>
            <div class="certification-box"><strong>DATE: (ORGANIZATION SUPPLY OFFICER)</strong><div class="line-value">{{ $draft['organization_supply_officer_date'] ?? '' }}</div></div>
            <div class="certification-box"><strong>QUANTITIES SHOWN ABOVE IN ACTION HAVE BEEN RECEIVED:</strong><div class="line-value">{{ $draft['station_supply_classification_officer_date'] ?? '' }}</div><div>(DATE :) For Station Supply or Classification Officer</div></div>
        </div>
    </div>
</article>
