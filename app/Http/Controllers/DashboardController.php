<?php

namespace App\Http\Controllers;

use App\Models\MCustomer;
use App\Models\MSupplier;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;

class DashboardController extends Controller
{
    public function index()
    {
        if (request()->user()->role !== 'Admin') {
            return view('workspace');
        }

        $today = now('Asia/Jakarta');
        $start = $today->copy()->startOfMonth()->subMonths(5);
        $end = $today->copy()->endOfMonth();
        $monthlyPurchase = PurchaseOrder::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get(['tanggal', 'total_harga'])
            ->groupBy(fn ($order) => substr((string) $order->tanggal, 0, 7));
        $monthlySales = SalesOrder::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get(['tanggal', 'total_harga'])
            ->groupBy(fn ($order) => substr((string) $order->tanggal, 0, 7));

        $chart = ['labels' => [], 'purchase' => [], 'sales' => []];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $chart['labels'][] = $month->locale('id')->translatedFormat('M Y');
            $chart['purchase'][] = (float) $monthlyPurchase->get($key, collect())->sum('total_harga');
            $chart['sales'][] = (float) $monthlySales->get($key, collect())->sum('total_harga');
        }

        $stats = [
            'purchase_orders' => PurchaseOrder::count(),
            'sales_orders' => SalesOrder::count(),
            'suppliers' => MSupplier::count(),
            'customers' => MCustomer::count(),
        ];
        $greeting = match (true) {
            $today->hour < 11 => 'Selamat pagi',
            $today->hour < 15 => 'Selamat siang',
            $today->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        return view('dashboard', compact('stats', 'chart', 'today', 'greeting'));
    }
}
