<?php

namespace App\Http\Controllers;

use App\Http\Requests\LineItemRequest;
use App\Models\LineItem;
use App\Models\Invoice;
use App\Http\Resources\LineItemResource;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LineItemController
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(string $invoiceId)
    {
        $ownsInvoice = Invoice::where('id', $invoiceId)
            ->whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->exists();

        if (! $ownsInvoice) {
            return $this->error([], 'Invoice not found.');
        }

        $data = LineItemResource::collection(
            LineItem::where('invoiceId', $invoiceId)->get()
        );

        return $this->success(['lineItems' => $data]);
    }

    public function get(string $id)
    {
        $lineItem = LineItem::whereHas('invoice.customer', fn ($q) => $q->where('userId', Auth::id()))
            ->where('id', $id)->first();

        if (empty($lineItem)) {
            return $this->error([], 'Line item not found.');
        }

        return $this->success(['lineItem' => $lineItem]);
    }

    public function save(Request $request)
    {
        // handle requests from timed out logins
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $validator = LineItemRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $data = $validator->safe()->toArray();
        $lineItemId = $data['id'];

        $ownsInvoice = Invoice::where('id', $data['invoiceId'])
            ->whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->exists();

        if (! $ownsInvoice) {
            return $this->error([
                'errors' => ['invoiceId' => ['Invoice not found.']]
            ], 'One or more errors were encountered.');
        }

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        if (empty($lineItemId)) {
            $lineItem = LineItem::create($data);
        } else {
            $lineItem = LineItem::whereHas('invoice.customer', fn ($q) => $q->where('userId', Auth::id()))
                ->where('id', $lineItemId)->first();

            if (empty($lineItem)) {
                return $this->error([], 'Line item not found.');
            }

            $lineItem->update($data);
        }

        // recalculate invoice total
        $this->updateInvoiceAmount($lineItem->invoiceId);

        return $this->success(
            ['lineItem' => $lineItem->toArray()],
            'Line Item saved successfully.'
        );
    }

    private function updateInvoiceAmount($invoiceId)
    {
        $invoice = Invoice::find($invoiceId);

        if (empty($invoice)) {
            return false;
        }

        $amount = DB::table('line_item')
            ->select(DB::raw('sum(ifnull(quantity, 0) * ifnull(price, 0)) as total'))
            ->where('invoiceId', $invoiceId)
            ->first();

        $invoice->update(['amount' => $amount->total]);
    }
}
