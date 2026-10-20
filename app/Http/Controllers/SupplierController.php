<?php

namespace App\Http\Controllers;

use App\Models\MSupplier;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = MSupplier::latest()->get();

        return view('supplier.index', compact('suppliers'));
    }

    public function create()
    {
        return view('supplier.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_supplier' => 'required|string|max:100',
            'alamat' => 'required|string|max:200',
            'telepon' => 'required|string|max:20',
            'email' => 'required|email|max:100',
        ]);

        MSupplier::create($request->only([
            'nama_supplier',
            'alamat',
            'telepon',
            'email',
        ]));

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Data supplier berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $supplier = MSupplier::findOrFail($id);

        return view('supplier.edit', compact('supplier'));
    }

    public function update(Request $request, $id)
    {
        $supplier = MSupplier::findOrFail($id);

        $request->validate([
            'nama_supplier' => 'required|string|max:100',
            'alamat' => 'required|string|max:200',
            'telepon' => 'required|string|max:20',
            'email' => 'required|email|max:100',
        ]);

        $supplier->update($request->only([
            'nama_supplier',
            'alamat',
            'telepon',
            'email',
        ]));

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Data supplier berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $supplier = MSupplier::findOrFail($id);

        try {
            DB::transaction(function () use ($supplier) {
                $locked = MSupplier::lockForUpdate()->findOrFail($supplier->getKey());
                if ($locked->purchaseOrders()->exists()) {
                    throw ValidationException::withMessages(['supplier' => 'Supplier tidak dapat dihapus karena sudah digunakan pada Purchase Order.']);
                }
                $locked->delete();
            });
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1451
                && ! str_contains((string) ($exception->errorInfo[2] ?? ''), 'FOREIGN KEY constraint failed')) {
                throw $exception;
            }
            return redirect()->route('supplier.index')
                ->withErrors(['supplier' => 'Supplier tidak dapat dihapus karena sudah digunakan pada Purchase Order.']);
        } catch (ValidationException $exception) {
            return redirect()->route('supplier.index')->withErrors($exception->errors());
        }

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Data supplier berhasil dihapus.');
    }
}