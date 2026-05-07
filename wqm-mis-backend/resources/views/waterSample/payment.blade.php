<!DOCTYPE html>
<html lang="en">
<style>
    body {
        font-size: 8px; /* Set the default font size to 8px */
    }

    .table-cell {
        border: 1px solid #ccc;
        padding: 5px;
        padding: 5px;
        text-align: left;
    }

    .word-value-table {
        float: right;
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
    <p style="text-align: right; flex: 1;">Water Sample # {{ $payment->slug }}</p>
</div>

<hr style="border: 1px solid #ccc;">
<div style="display: flex; align-items: center; justify-content: space-between;">
    <div>
        <x-kpk-logo/>
    </div>
    <div>
        <p style="text-align: center; margin: 0;">GOVERNMENT OF KHYBER PAKHTUNKHWA</p>
        <p style="text-align: center; margin: 0;">Public Health Engineering Department</p>
        <p style="text-align: center; margin: 0;">{{$payment->laboratory->name}}</p>
        <p style="text-align: center; margin: 0;">{{$payment->laboratory->address}}</p>
        <p
            style="text-align: center; margin: 0;">
            Ph: {{$payment->laboratory->phone}},
            Fax: {{$payment->laboratory->fax}},
            Email: {{$payment->laboratory->email}}
        </p>
    </div>
    <div>
        <x-kpk-logo/>
    </div>
</div>
<hr style="border: 1px solid #ccc;">

<p style="text-align: center; font-weight: bold;">PAYMENT</p>
<p style="text-align: center; font-weight: bold;">WATER QUALITY TESTING SERVICES</p>
<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
    <tr>
        <td class="table-cell" style="border-bottom: 1px solid #ccc; font-weight:bold">Payment ID</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">{{$payment?->slug ?? '-'}}</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">Payment Amount</td>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;font-weight:bold">{{$payment?->total ?? '-'}}</td>
    </tr>
    <tr>
        <td class="table-cell" style="border-bottom: 1px solid #ccc;">Description</td>
        <td class="table-cell" colspan="3" style="border-bottom: 1px solid #ccc;">{{ $payment->description ?? '-' }}</td>
    </tr>
    <tr>
        <td class="table-cell">Created By</td>
        <td class="table-cell">{{ $payment->created_by_user?->name}}</td>
        <td class="table-cell">Created At</td>
        <td class="table-cell">{{$payment->created_at}}</td>
    </tr>
    <tr>
        <td class="table-cell">Updated By</td>
        <td class="table-cell">{{ $payment->updated_by_user?->name}}</td>
        <td class="table-cell">Updated At</td>
        <td class="table-cell">{{$payment->updated_at}}</td>
    </tr>
</table>
<h3 style="text-align: center;">Details</h3>
<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
    <tr>
        <th style="border: 1px solid #ccc;">Water Sample ID</th>
        <th style="border: 1px solid #ccc;">Client Name</th>
        <th style="border: 1px solid #ccc;">Invoice Date</th>
        <th style="border: 1px solid #ccc;">Amount</th>
    </tr>
    @foreach($payment->paymentDetails as $payment)
        <tr>
            <td style="border: 1px solid #ccc; text-align: center;">{{$payment->paymentable?->waterSampleInvoice?->waterSample?->slug}}</td>
            <td style="border: 1px solid #ccc;text-align: center;">{{$payment->paymentable?->waterSampleInvoice?->invoiceable?->name}}</td>
            <td style="border: 1px solid #ccc;text-align: center;">{{$payment->paymentable?->created_at}}</td>
            <td style="border: 1px solid #ccc;text-align: center;">Rs. {{number_format($payment->amount)}}</td>
        </tr>
    @endforeach
</table>
</div>

<div class="word-value-table">
</div>
</body>
</html>
