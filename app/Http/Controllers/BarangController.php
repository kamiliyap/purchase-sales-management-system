<?php

namespace App\Http\Controllers;

use App\Models\MBarang;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangController extends Controller
{
    public function index()
    {
        $barangs = MBarang::latest()->get();

        return view('barang.index', compact('barangs'));
    }

    public function create()
    {
        return view('barang.create');
    }

    public function store(Request $request)
    {
        MBarang::create($request->validate($this->rules()));

        return redirect()->route('barang.index')
            ->with('success', 'Data barang berhasil ditambahkan.');
    }

    public function show(MBarang $barang)
    {
        return view('barang.show', compact('barang'));
    }

    public function edit(MBarang $barang)
    {
        return view('barang.edit', compact('barang'));
    }

    public function update(Request $request, MBarang $barang)
    {
        $barang->update($request->validate($this->rules($barang)));

        return redirect()->route('barang.index')
            ->with('success', 'Data barang berhasil diperbarui.');
    }

    public function destroy(MBarang $barang)
    {
        if ($barang->detailPurchaseOrders()->exists() || $barang->detailSalesOrders()->exists()) {
            return redirect()->route('barang.index')
                ->withErrors(['barang' => 'Barang tidak dapat dihapus karena sudah digunakan dalam transaksi pembelian atau penjualan.']);
        }

        try {
            $barang->delete();
        } catch (QueryException $exception) {
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $driverMessage = (string) ($exception->errorInfo[2] ?? '');
            if ($driverCode !== 1451 && !str_contains($driverMessage, 'FOREIGN KEY constraint failed')) {
                throw $exception;
            }
            return redirect()->route('barang.index')
                ->withErrors(['barang' => 'Barang tidak dapat dihapus karena masih digunakan dalam transaksi.']);
        }

        return redirect()->route('barang.index')
            ->with('success', 'Data barang berhasil dihapus.');
    }

    private function rules(?MBarang $barang = null): array
    {
        $skuRule = Rule::unique('m_barang', 'sku');
        if ($barang !== null) {
            $skuRule->ignore($barang->getKey(), 'id_barang');
        }

        return [
            'sku' => ['required', 'string', 'max:255', $skuRule],
            'nama_barang' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:255'],
            'harga_beli' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stok' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
