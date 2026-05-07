<!DOCTYPE html>
<html lang="en">
<style>
    body {
        font-size: 8px; /* Set the default font size to 8px */
    }
    .table-cell {
        border: 1px solid #ccc;
        padding: 5px; 
        text-align: left;
    }
    .section {
        margin-bottom: 20px;
        border: 1px solid #ccc; /* Add border style to the section */
        padding: 10px; /* Add padding for spacing */
    }
    .status {
    display: inline-block;
    padding: 5px 15px; 
    border-radius: 50%;
    text-align: center;
    font-weight: bold;
    color: white;
}

/* Define background colors for different statuses */
.status.active {
    background-color: green;
}

.status.pending {
    background-color: blue;
}

.status.rejected {
    background-color: red;
}

.status.requested {
    background-color: yellow;
}
</style>
<body>
<div class="section">
<div style="display: flex; align-items: center;">
    <p style="text-align: left; flex: 1; font-weight:bold">KOICA WQM MIS</p>
    <div style="display: flex; align-items: center;">
        <p style="margin-right: 10px;">Purchase Order # {{ $purchaseOrder->id }}</p>
        <div class="status
         @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::APPROVED) active 
         @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PENDING) pending
          @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::REJECTED) inactive 
          @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::REQUESTED) requested @endif">
            @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::APPROVED)
                Active
            @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::REJECTED)
                Inactive
            @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::REQUESTED)
                Requested
            @else
                {{ ucfirst(strtolower($purchaseOrder->status->value)) }}
            @endif
        </div>
    </div>
</div>
    <hr style="border: 1px solid #ccc;">
    <p style="text-align: left;">Purchase Order</p>
    <p style="text-align: left; color:#ccc; padding: 5px; margin-left: 10px;">
        Date of Order: <span style="color: black;margin-left: 250px;">{{ $purchaseOrder->date_of_order }}</span>
    </p>
</div>

<div class="section">
    <h3 style="text-align: left;">Purchase Order Details</h3>
    <div>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <tr>
                <td class="table-cell" style="border-bottom: 1px solid #ccc; font-weight:bold">Purchasable</td>
                <td class="table-cell" style="border-bottom: 1px solid #ccc; font-weight:bold">Quantity</td>
                <td class="table-cell" style="border-bottom: 1px solid #ccc; font-weight:bold">Unit</td>
            </tr>
            <tbody>
                @if ($purchaseOrder->purchaseOrderDetails->isEmpty())
                <tr>
                    <td colspan="4">No details found.</td>
                </tr>
                @else
                @foreach ($purchaseOrder->purchaseOrderDetails as $detail)
                <tr>
                    <td class="table-cell">{{ $detail->purchasable->name }}</td>
                    <td class="table-cell">{{ $detail->quantity }}</td>
                    <td class="table-cell">{{ $detail->unit }}</td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

</body>
</html>