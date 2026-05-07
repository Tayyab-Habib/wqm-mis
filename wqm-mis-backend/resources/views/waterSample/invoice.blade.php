<!DOCTYPE html>
<html lang="en">
<style>
    body {
        font-size: 12px; /* Set the default font size to 8px */
    }

    .table-cell {
        border: 1px solid #ccc;
        padding: 5px;
        padding: 5px;
        text-align: left;
    }

    .word-value-table {
        display: flex;
        justify-content: space-between;
        margin-left: 20px; /* Adjust the distance from the text */
    }

    .word-value-table table {
        border-collapse: collapse;
        width: 300px; /* Adjust the width as needed */
        /* color: grey; */
        color: #000;
    }

    .word-value-table td {
        border-bottom: 1px solid #c0c0c0;
        padding: 5px;
        text-align: left;
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
    <p style="text-align: right; flex: 1;">Water Sample # {{ $waterSampleInvoice->waterSample->slug }}</p>
</div>

<hr style="border: 1px solid #ccc;">
<div style="display: flex; align-items: center; justify-content: space-between;">
    <div>
        <x-kpk-logo/>
    </div>
    <div>
        <p style="text-align: center; margin: 0;">GOVERNMENT OF KHYBER PAKHTUNKHWA</p>
        <p style="text-align: center; margin: 0;">Public Health Engineering Department</p>
        <p style="text-align: center; margin: 0;">{{$waterSampleInvoice->waterSample->laboratory->name}}</p>
        <p style="text-align: center; margin: 0;">{{$waterSampleInvoice->waterSample->laboratory->address}}</p>
        <p
            style="text-align: center; margin: 0;">
            Ph: {{$waterSampleInvoice->waterSample->laboratory->phone}},
            Fax: {{$waterSampleInvoice->waterSample->laboratory->fax}},
            Email: {{$waterSampleInvoice->waterSample->laboratory->email}}
        </p>
    </div>
    <div>
        <x-lab-logo/>
    </div>
</div>
<hr style="border: 1px solid #ccc;">

<p style="text-align: center; font-weight: bold;">INVOICE</p>
<p style="text-align: center; font-weight: bold;">WATER QUALITY TESTING SERVICES</p>
<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
    <tr>
        <td class="table-cell" style="border-bottom: 1px solid #ccc; font-weight:bold">Source Name/ID</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">
            {{$waterSampleInvoice?->waterSample->waterScheme?->name ?? $waterSampleInvoice?->waterSample?->collectable?->organization_name ?? $waterSampleInvoice?->waterSample->slug}}
        </td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">Sampling Date & Time</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">{{$waterSampleInvoice?->waterSample?->sampled_at ?? '-'}}</td>
    </tr>
    <tr>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;">Samples Detail</td>
        <td class="table-cell"
            style="border-bottom: 1px solid #ccc;">{{ $waterSampleInvoice?->waterSample?->sample_name}}</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;">Sample receipt Date & Time</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;">{{ $waterSampleInvoice?->waterSample?->created_at ?? '-' }}</td>
    </tr>
    <tr>
        <td class="table-cell">Source/Sampling point</td>
        <td class="table-cell"> {{ $waterSampleInvoice->waterSample->source_sub_type }} / {{ $waterSampleInvoice->waterSample->sampling_point}}</td>
        <td class="table-cell">Temperature (at receipt)</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->temperature_in_celsius}}</td>
    </tr>
    <tr>
        <td class="table-cell">Sample Status</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->status }}</td>
        <td class="table-cell">Date of Analysis</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->analyzed_at ?? '-'}}</td>
    </tr>
    <tr>
        <td class="table-cell">Address / District</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->water_sample_address ?? $waterSampleInvoice->water_sample->collectable?->address }}
            / {{ $waterSampleInvoice->waterSample->district->name }}</td>
        <td class="table-cell">Reporting Date</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->reported_at}}</td>
    </tr>
    @php
        $collectedIn = $waterSampleInvoice->waterSample->collected_in;
        $complaint = $waterSampleInvoice->waterSample->complaint;
    @endphp
    <tr>
        <td class="table-cell">Collected by/Received From</td>
        <td class="table-cell">{{ $waterSampleInvoice->waterSample->collected_by}}</td>
        <td class="table-cell">Sample Collected in</td>
        <td class="table-cell"> {{ $collectedIn->value === 'Other' ? $waterSampleInvoice->waterSample->collected_in_other : $waterSampleInvoice->waterSample->collected_in }}</td>
    </tr>
    <tr>
        <td class="table-cell">Reason For Testing</td>
        <td class="table-cell">{{ $complaint->value === 'Other' ? $waterSampleInvoice->waterSample->complaint_by_other : $waterSampleInvoice->waterSample->complaint}}</td>
        <td class="table-cell">Sender Reference/Contact</td>
        <td class="table-cell">{{ $waterSampleInvoice?->waterSample?->collectable_type === \App\Models\Client::class ? $waterSampleInvoice->waterSample->collectable->phone : 'NIL'}}</td>
    </tr>
    <tr>
        <td class="table-cell">Client Name</td>
        <td class="table-cell">{{ $waterSampleInvoice?->waterSample->collectable_type === \App\Models\Client::class ?$waterSampleInvoice?->waterSample->collectable->name : '-'}}</td>
        <td class="table-cell">Client Email</td>
        <td class="table-cell">{{ $waterSampleInvoice?->waterSample->collectable_type === \App\Models\Client::class ? $waterSampleInvoice?->waterSample->collectable->email : '-'}}</td>
    </tr>
    <tr>
        <td class="table-cell">Desired Tests</td>
        <td class="table-cell">{{implode(', ',$waterSampleInvoice->waterSample->desired_test)}} {{$desiredTests}}</td>
        <td class="table-cell">Total Price</td>
        <td class="table-cell">{{ $waterSampleInvoice->price}}</td>
    </tr>
</table>
</div>

<div style="display: flex; align-items: center; justify-content: space-between;">
    <div>
        <table>
            <tr>
                <td>
                    {!! $waterSampleInvoice->waterSample->qr_code !!}
                </td>
            </tr>
        </table>
    </div>
    <div class="word-value-table">
        <table>
            <tr>
                <td>
                    Price
                    : {{$waterSampleInvoice->discount_percentage > 0 ? ($waterSampleInvoice->price * 100) / $waterSampleInvoice->discount_percentage : $waterSampleInvoice->price}}
                </td>
            </tr>
            <tr>
                <td>
                    Discounted Price :
                    {{ $waterSampleInvoice->price }}
                </td>
            </tr>
            <tr>
                <td>
                    Discount(%) : {{ $waterSampleInvoice->discount_percentage }}
                </td>
            </tr>
            <tr>
                <td>
                    Last Paid : {{ $waterSampleInvoice->paid }}
                </td>
            </tr>
            <tr>
                <td>
                    Balance : {{ $waterSampleInvoice->balance}}
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
