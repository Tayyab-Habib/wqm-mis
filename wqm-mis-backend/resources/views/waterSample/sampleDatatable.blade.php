<h3 style="text-align: center;">{{ $title }} Parameters</h3>
<table style="width: 100%; border-collapse: collapse; text-align: left;">
    <thead>
    <tr>
        <th style="border: 1px solid #ccc;">ID</th>
        <th style="border: 1px solid #ccc;">Water Quality Parameter</th>
        <th style="border: 1px solid #ccc;">Unit</th>
        <th style="border: 1px solid #ccc;">Detectable Limit</th>
        <th style="border: 1px solid #ccc;">Reference Method</th>
        <th style="border: 1px solid #ccc;">Guideline Values</th>
        <th style="border: 1px solid #ccc;">Analysis Result</th>
    </tr>
    </thead>
    <tbody>
    @php

        @endphp
    @foreach ($parameters as $key => $detail)
        <tr>
            <td style="border: 1px solid #ccc;">{{ ++$key }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail->test->water_quality_parameter }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail->test->unit }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail->test->detectable_limit }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail->test->reference_method }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail->test->who_guideline_start }} - {{ $detail->test->who_guideline_end }}</td>
            <td style="border: 1px solid #ccc;">{{ $detail?->analysis_result ?? 'NT' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
