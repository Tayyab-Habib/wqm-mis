<!DOCTYPE html>
<html lang="en">
<style>
    body {
        font-size: 12px; /* Set the default font size to 8px */
    }

    .table-cell {
        border: 1px solid #ccc;
        padding: 5px;
        text-align: left;
    }

    .abbreviations-table {
        border-collapse: collapse;
        width: 100%;
    }

    .abbreviations-table td {
        padding: 5px;
        text-align: left;
    }

    .abbreviations-table .name-cell {
        margin-right: 10px;
        color: #999;
    }

    .parameter-table-bordered {
        width: 100%;
        border-collapse: collapse;
    }

    .parameter-table-bordered th,
    .parameter-table-bordered td {
        border: 1px solid #ccc;
        padding: 5px;
        text-align: left;
    }
</style>
<body>
<div style="display: flex; align-items: center;">
    <p style="text-align: right; flex: 1;">Water Sample # {{ $waterSample->slug }}</p>
</div>

<hr style="border: 1px solid #ccc;">
<!-- <img src="https://wqmis-backend.phedkp.gov.pk/storage//logos/default-logo.png" alt="Logo" style="margin-left: 2px; width: 70px; height: auto;"> -->
<div style="display: flex; align-items: center; justify-content: space-between;">
    <div>
        <x-kpk-logo/>
    </div>
    <div>
        <p style="text-align: center; margin: 0;">GOVERNMENT OF KHYBER PAKHTUNKHWA</p>
        <!-- <p style="text-align: center; margin: 0;">{{$waterSample->province->name}}</p> -->
        <p style="text-align: center; margin: 0;">Public Health Engineering Department</p>
        <p style="text-align: center; margin: 0;">{{$waterSample->laboratory->name}}</p>
        <p style="text-align: center; margin: 0;">{{$waterSample->laboratory->address}}</p>
        <p
            style="text-align: center; margin: 0;">
            Ph: {{$waterSample->laboratory->phone}},
            Fax: {{$waterSample->laboratory->fax}},
            Email: {{$waterSample->laboratory->email}}
        </p>
    </div>
    <div>
        <x-lab-logo/>
    </div>
</div>
<hr style="border: 1px solid #ccc;">

<p style="text-align: center; font-weight: bold;">WATER QUALITY ANALYSIS REPORT</p>
<div style="margin-bottom: 20px;">

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
        <tr>
            <td class="table-cell;">Source Name/ID</td>
            <td class="table-cell">{{ $waterSample->waterScheme?->name ?? $waterSample->water_sample_address ??  $waterSample->slug }}</td>
            <td class="table-cell">Sampling Date & Time</td>
            <td class="table-cell">{{ $waterSample->sampled_at }}</td>
        </tr>
        <tr>
            <td class="table-cell">Sample Detail</td>
            <td class="table-cell">{{ $waterSample->sample_name}}</td>
            <td class="table-cell">Sampling receipt Date & Time</td>
            <td class="table-cell">{{ $waterSample->created_at}}</td>
        </tr>
        <tr>
            <td class="table-cell">Source Type</td>
            <td class="table-cell">{{ $waterSample->source_sub_type}} / {{$waterSample->sampling_point}}</td>
            <td class="table-cell">Temperature (during test)</td>
            <td class="table-cell">{{ $waterSample->temperature_in_celsius}}</td>
        </tr>
        <tr>
            <td class="table-cell">Address / District</td>
            <td class="table-cell">{{ $waterSample?->water_sample_address . ' / ' .  $waterSample->district->name }}</td>
            <td class="table-cell">Reporting Date</td>
            <td class="table-cell">{{ $waterSample->reported_at}}</td>
        </tr>
        <tr>
            <td class="table-cell">Collected by/Received From</td>
            <td class="table-cell">{{ $waterSample->collected_by}}</td>
            <td class="table-cell">Date of Analysis</td>
            <td class="table-cell">{{ $waterSample->analyzed_at}}</td>
        </tr>
        @php
            $collectable = $waterSample->collectable_type === 'Private';
        @endphp

        <tr>
            <td class="table-cell">Client Name</td>
            <td class="table-cell">{{ $collectable ? $waterSample->collectable->name : '-'}}</td>
            <td class="table-cell">Client Email</td>
            <td class="table-cell">{{ $collectable ? $waterSample->collectable->email : '-'}}</td>
        </tr>
        <tr>
            <td class="table-cell">Desired Tests</td>
            <td class="table-cell">{{implode(', ', array_filter($waterSample->desired_test, fn ($test) => $test !== 'On Demand'))}}, {{$desiredTests}}</td>
            <td class="table-cell">Ref /Contact #</td>
            <td class="table-cell">{{ $collectable ? $waterSample->collectable->phone : '-'}}</td>
        </tr>
        <tr>
            <td class="table-cell">Reason for Testing</td>
            <td class="table-cell">{{ $waterSample->complaint}}</td>
            <td class="table-cell">Result</td>
            <td class="table-cell">{{ $waterSample->result ?? 'Not tested'}}</td>
        </tr>
        <tr>
            <td class="table-cell">Created By</td>
            <td class="table-cell">{{ $waterSample->createdByUser->name ?? '-'}}</td>
            <td class="table-cell">Updated By</td>
            <td class="table-cell">{{ $waterSample->modifiedByUser->name ?? '-'}}</td>
        </tr>
    </table>
</div>

<div>

    @php
        $parameters = $waterSample->waterSampleDetails->groupBy('test.type');
        $physicalParameters = $parameters['Physical'] ?? [];
        $chemicalParameters = $parameters['Chemical'] ?? [];
        $microKParameter = $parameters['Microbiological(Kit)'] ?? [];
        $microMFParameter = $parameters['Microbiological(MF)'] ?? [];
        $otherWaterSampleParameters = [];

        foreach ($parameters as $key => $value) {
            if (!in_array($key, ['Chemical', 'Physical', 'Microbiological(Kit)', 'Microbiological(MF)'])) {
                $otherWaterSampleParameters[$key] = $value;
            }
        }
    @endphp
    @includeWhen( count($physicalParameters) > 0,'waterSample.sampleDatatable', ['title' => 'Physical', 'parameters' => $physicalParameters])
    @includeWhen(count($chemicalParameters) > 0,'waterSample.sampleDatatable', ['title' => 'Chemical', 'parameters' => $chemicalParameters])
    @includeWhen(count($microKParameter) > 0,'waterSample.sampleDatatable', ['title' => 'Microbiological (KIT)', 'parameters' => $microKParameter])
    @includeWhen(count($microMFParameter) > 0,'waterSample.sampleDatatable', ['title' => 'Microbiological (MF)', 'parameters' => $microMFParameter])
    @foreach ($otherWaterSampleParameters as $type => $details)
        @include('waterSample.sampleDatatable', ['title' => $type, 'parameters' => $details])
    @endforeach

</div>
<h3>Abbreviations</h3>
<table class="abbreviations-table">
    @php
        $abbreviationChunks = collect($abbreviations)->chunk(4);
        $columnsPerRow = 4;
    @endphp
    @foreach($abbreviationChunks as $chunk)
        <tr>
            @foreach($chunk as $abbreviation)
                <td><b>{{$abbreviation->name}}: </b>{{$abbreviation->detail}}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<div style="display: flex; justify-content: space-between;">
    <div>
        <h3>Term and Conditions</h3>
        <ul>
            @foreach($termAndConditions as $term)
                <li>{{ $term['term_condition'] }}</li>
            @endforeach
        </ul>
    </div>
    <div>
        {!! $waterSample->qr_code !!}
    </div>
</div>
<div>
    <h3>Remarks:</h3>
    {{$waterSample->remarks}}
</div>
</body>
</html>
