<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = PaymentResource::collection(
            Payment::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
                ->orderBy('id', 'desc')->get()
        );

        return $this->success(['payments' => $data]);
    }

    public function get(string $id)
    {
        $payment = Payment::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
            ->where('id', $id)->first();

        if (empty($payment)) {
            return $this->error([], 'Payment not found.');
        }

        return $this->success(['payment' => $payment]);
    }

    /**
     * Display the payments belonging to a single customer.
     */
    public function customer(string $customerId)
    {
        $owned = Customer::where('id', $customerId)->where('userId', Auth::id())->exists();

        if (! $owned) {
            return $this->error([], 'Customer not found.');
        }

        $data = PaymentResource::collection(
            Payment::where('customerId', $customerId)->orderBy('id', 'desc')->get()
        );

        return $this->success(['payments' => $data]);
    }

    public function save(Request $request)
    {
        // handle requests from timed out logins
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $validator = PaymentRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $data = $validator->safe()->toArray();
        $paymentId = $data['id'];

        $ownsCustomer = Customer::where('id', $data['customerId'])->where('userId', Auth::id())->exists();

        if (! $ownsCustomer) {
            return $this->error([
                'errors' => ['customerId' => ['Customer not found.']]
            ], 'One or more errors were encountered.');
        }

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        if (empty($paymentId)) {
            $payment = Payment::create($data);
        } else {
            $payment = Payment::whereHas('customer', fn ($q) => $q->where('userId', Auth::id()))
                ->where('id', $paymentId)->first();

            if (empty($payment)) {
                return $this->error([], 'Payment not found.');
            }

            $payment->update($data);
        }

        return $this->success(
            ['payment' => $payment->toArray()],
            'Payment saved successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Payment $payment)
    {
        abort_unless($payment->customer?->userId === Auth::id(), 403);

        return $payment->delete();
    }
}
