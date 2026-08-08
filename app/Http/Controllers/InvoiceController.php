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
            Invoice::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
                ->orderBy('id', 'desc')->get()
        );

        return $this->success(['invoices' => $data]);
    }

    public function get(string $id)
    {
        $invoice = Invoice::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->where('id', $id)->first();

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
        $owned = Customer::where('id', $customerId)->where('userId', Auth::id())->exists();

        if (! $owned) {
            return $this->error([], 'Customer not found.');
        }

        $data = InvoiceResource::collection(
            Invoice::where('customerId', $customerId)->orderBy('id', 'desc')->get()
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

        $ownsCustomer = Customer::where('id', $data['customerId'])->where('userId', Auth::id())->exists();

        if (! $ownsCustomer) {
            return $this->error([
                'errors' => ['customerId' => ['Customer not found.']]
            ], 'One or more errors were encountered.');
        }

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        if (empty($invoiceId)) {
            $invoice = Invoice::create($data);
        } else {
            $invoice = Invoice::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
                ->where('id', $invoiceId)->first();

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

        $invoice = Invoice::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->where('id', $id)->first();

        if (empty($invoice)) {
            return $this->error([], 'Invoice not found.');
        }

        $invoice->emailed = ($invoice->emailed === 'Y') ? 'N' : 'Y';
        $invoice->save();

        return $this->success(['invoice' => $invoice]);
    }

    public function pdf($id)
    {
        // this action is not currently routed, but keep it locked down the
        // same as every other resource endpoint so wiring up a route later
        // doesn't silently reintroduce unauthenticated/cross-tenant access
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $invoice = Invoice::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->where('id', $id)->first();

        if (empty($invoice)) {
            return $this->error([], 'Invoice not found.');
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
        abort_unless($invoice->customer?->userId === Auth::id(), 403);

        return $invoice->delete();
    }
}
