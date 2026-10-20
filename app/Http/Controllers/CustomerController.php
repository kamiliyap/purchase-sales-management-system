<?php

namespace App\Http\Controllers;

use App\Models\MCustomer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = MCustomer::latest()->get();

        return view('customer.index', compact('customers'));
    }

    public function create()
    {
        return view('customer.create');
    }

    public function store(Request $request)
    {
        MCustomer::create($request->validate($this->rules()));

        return redirect()->route('customer.index')
            ->with('success', 'Data customer berhasil ditambahkan.');
    }

    public function show(MCustomer $customer)
    {
        return view('customer.show', compact('customer'));
    }

    public function edit(MCustomer $customer)
    {
        return view('customer.edit', compact('customer'));
    }

    public function update(Request $request, MCustomer $customer)
    {
        $customer->update($request->validate($this->rules()));

        return redirect()->route('customer.index')
            ->with('success', 'Data customer berhasil diperbarui.');
    }

    public function destroy(MCustomer $customer)
    {
        if ($customer->salesOrders()->exists()) {
            return redirect()->route('customer.index')
                ->withErrors(['customer' => 'Customer tidak dapat dihapus karena sudah digunakan pada Sales Order.']);
        }

        try {
            $customer->delete();
        } catch (QueryException $exception) {
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $driverMessage = (string) ($exception->errorInfo[2] ?? '');
            if ($driverCode !== 1451 && !str_contains($driverMessage, 'FOREIGN KEY constraint failed')) {
                throw $exception;
            }
            return redirect()->route('customer.index')
                ->withErrors(['customer' => 'Customer tidak dapat dihapus karena masih digunakan dalam transaksi.']);
        }

        return redirect()->route('customer.index')
            ->with('success', 'Data customer berhasil dihapus.');
    }

    private function rules(): array
    {
        return [
            'nama_customer' => ['required', 'string', 'max:100'],
            'alamat' => ['required', 'string', 'max:200'],
            'telepon' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:100'],
        ];
    }
}
