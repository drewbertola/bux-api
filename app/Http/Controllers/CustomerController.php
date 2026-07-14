<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Traits\HttpResponses;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController
{
    use HttpResponses;

    /**
     * Display a listing of the resource (not implemented).
     */
    public function index()
    {
    }

    /**
     * Display the specified resource.
     */
    public function get(string $id)
    {
        $customer = Customer::find($id);

        if (empty($customer)) {
            return $this->error([], 'Customer not found.');
        }

        return $this->success(['customer' => $customer]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function save(Request $request)
    {
        // handle requests from timed out logins
        if (empty(Auth::user())) {
            return redirect('/');
        }

        $validator = CustomerRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $data = $validator->safe()->toArray();
        $customerId = $data['id'];

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        if (empty($customerId)) {
            $customer = Customer::create($data);
        } else {
            $customer = Customer::find($customerId);

            if (empty($customer)) {
                return $this->error([], 'Customer not found.');
            }

            $customer->update($data);
        }

        return $this->success(
            ['customer' => $customer->toArray()],
            'Customer saved successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Customer $customer)
    {
        return $customer->delete();
    }

    public function getBalanceData(string $customerId)
    {
        $customer = Customer::find($customerId);
        $invoices = Invoice::where('customerId', $customerId)->get();
        $payments = Payment::where('customerId', $customerId)->get();

        $entries = [];

        $invoiceTotal = 0;
        $paymentTotal = 0;

        foreach ($invoices as $i) {
            $i->type = 'Invoice';
            $i->amount = -1 * $i->amount;
            $entries[] = $i;
            $invoiceTotal += $i->amount;
        }

        foreach ($payments as $p) {
            $p->type = 'Payment';
            $entries[] = $p;
            $paymentTotal += $p->amount;
        }

        usort($entries, 'self::sortEntries');

        $balance = 0;

        foreach ($entries as $e) {
            $balance += $e->amount;
            $e->balance = $balance;
        }

        return $this->success([
            'customer' => $customer,
            "transactions" => $entries,
            "invTotal" => $invoiceTotal,
            "pmtTotal" => $paymentTotal
        ]);
    }

    public function getTableData()
    {
        $invoice = DB::table('invoice')
            ->select('customerId',
                DB::raw('max(id) as id'),
                DB::raw('sum(amount) as amount'),
                DB::raw('max(date) as date'))
            ->groupBy('customerId');

        $payment = DB::table('payment')
            ->select('customerId',
                DB::raw('max(id) as id'),
                DB::raw('sum(amount) as amount'),
                DB::raw('max(date) as date'))
            ->groupBy('customerId');


        $results = DB::table('customer')
            ->select(
                'customer.id as id',
                'customer.archive as archive',
                'customer.name as name',
                'customer.primaryContact as primaryContact',
                'customer.primaryEmail as primaryEmail',
                'customer.primaryPhone as primaryPhone',
                DB::raw('ifnull(i.id, 0) as lastInvId'),
                DB::raw('ifnull(i.date, "") as lastInvDate'),
                DB::raw('ifnull(p.amount, 0) - ifnull(i.amount, 0) as balance')
            )
            ->leftJoinSub($invoice, 'i', function (JoinClause $join) {
                $join->on('customer.id', '=', 'i.customerId');
            })
            ->leftJoinSub($payment, 'p', function (JoinClause $join) {
                $join->on('customer.id', '=', 'p.customerId');
            })->get()->toArray();

        return $this->success(['customers' => $results]);
    }

    private static function sortEntries($a, $b)
    {
        if ($a->date === $b->date) {
            return 0;
        }

        return ($a->date < $b->date) ? -1 : 1;
    }

}
