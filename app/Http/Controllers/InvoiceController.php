<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\LineItem;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InvoiceController
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = InvoiceResource::collection(
            Invoice::where('user_id', Auth::id())->orderBy('id', 'desc')->get()
        );

        return $this->success(['invoices' => $data]);
    }

    public function get(string $id)
    {
        $invoice = Invoice::where('user_id', Auth::id())->find($id);

        if (empty($invoice)) {
            return $this->error([], 'Invoice not found.');
        }

        return $this->success(['invoice' => $invoice]);
    }

    /**
     * Display the invoices belonging to a single customer.
     */
    public function customer(string $customerId)
    {
        $data = InvoiceResource::collection(
            Invoice::where('customerId', $customerId)->where('user_id', Auth::id())->orderBy('id', 'desc')->get()
        );

        return $this->success(['invoices' => $data]);
    }

    public function save(Request $request)
    {
        // handle requests from timed out logins
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $validator = InvoiceRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $data = $validator->safe()->toArray();
        $invoiceId = $data['id'];

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $ownsCustomer = Customer::where('id', $data['customerId'])
            ->where('user_id', Auth::id())
            ->exists();

        if (!$ownsCustomer) {
            return $this->error([], 'Customer not found.');
        }

        if (empty($invoiceId)) {
            $invoice = new Invoice($data);
            $invoice->user_id = Auth::id();
            $invoice->save();
        } else {
            $invoice = Invoice::where('user_id', Auth::id())->find($invoiceId);

            if (empty($invoice)) {
                return $this->error([], 'Invoice not found.');
            }

            $invoice->update($data);
        }

        return $this->success(
            ['invoice' => $invoice->toArray()],
            'Invoice saved successfully.'
        );
    }

    public function toggleSent(string $id)
    {
        // handle requests from timed out logins
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $invoice = Invoice::where('user_id', Auth::id())->find($id);

        if (empty($invoice)) {
            return $this->error([], 'Invoice not found.');
        }

        $invoice->emailed = ($invoice->emailed === 'Y') ? 'N' : 'Y';
        $invoice->save();

        return $this->success(['invoice' => $invoice]);
    }

    public function pdf($id)
    {
        $invoice = Invoice::where('id', $id)->where('user_id', Auth::id())->first();

        if (empty($invoice)) {
            abort(404);
        }

        $lineItems = LineItem::where('invoiceId', $invoice->id)->get();
        $customer = Customer::where('id', $invoice->customerId)->first();

        $result = \App\Pdf\Invoice::render($invoice, $lineItems, $customer);

        return response(
            $result->pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $result->file . '"',
            ]
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id()) {
            return $this->error([], 'Invoice not found.');
        }

        return $invoice->delete();
    }
}
