<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
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
        $data = PaymentResource::collection(Payment::orderBy('id', 'desc')->get());

        return $this->success(['payments' => $data]);
    }

    public function get(string $id)
    {
        $payment = Payment::find($id);

        return $this->success(['payment' => $payment]);
    }

    /**
     * Display the payments belonging to a single customer.
     */
    public function customer(string $customerId)
    {
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

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        if (empty($paymentId)) {
            $payment = Payment::create($data);
        } else {
            $payment = Payment::find($paymentId);
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
        return $payment->delete();
    }
}
