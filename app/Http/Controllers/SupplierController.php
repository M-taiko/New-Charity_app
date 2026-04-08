<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $this->authorizeManager();
        $suppliers = Supplier::withCount('purchaseRequests')->latest()->get();
        return view('procurement.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $this->authorizeManager();
        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string|max:1000',
        ]);
        Supplier::create($request->only(['name', 'phone', 'email', 'address', 'notes']));
        return back()->with('success', 'تم إضافة المورد');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeManager();
        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string|max:1000',
        ]);
        $supplier->update($request->only(['name', 'phone', 'email', 'address', 'notes']));
        return back()->with('success', 'تم تحديث بيانات المورد');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeManager();
        $supplier->update(['is_active' => false]);
        return back()->with('success', 'تم تعطيل المورد');
    }

    private function authorizeManager(): void
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            abort(403);
        }
    }
}
