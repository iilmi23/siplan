<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::latest()->get();
        
        return Inertia::render('Master/Customer/Index', [
            'customers' => $customers,
            'flash' => session('flash')
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Customer/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:customers,name'
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:customers,code'
            ],
        ], [
            'name.unique' => 'Customer already exists!',
            'code.unique' => 'Code is already in use!',
        ]);

        Customer::create([
            'name' => $request->name,
            'code' => $request->code,
        ]);

        return redirect()->route('customers.index')
            ->with('flash', ['success' => 'Customer successfully added!']);
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        return Inertia::render('Master/Customer/Edit', [
            'customer' => $customer
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'name')->ignore($customer->id),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'code')->ignore($customer->id),
            ],
        ], [
            'name.unique' => 'Customer sudah ada!',
            'code.unique' => 'Kode sudah digunakan!',
        ]);

        $customer->update([
            'name' => $request->name,
            'code' => $request->code,
        ]);

        return redirect()->route('customers.index')
            ->with('flash', ['success' => 'Customer successfully updated!']);
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);

        if ($customer->ports()->exists() ||
            $customer->uploadBatches()->exists() ||
            $customer->sppRecords()->exists() ||
            $customer->productionWeeks()->exists() ||
            $customer->srMappingTemplates()->exists() ||
            \App\Models\SR::where('customer_id', $customer->id)->exists()
        ) {
            return redirect()->back()
                ->with('flash', ['error' => 'Customer cannot be deleted because it is currently linked to templates, ports, or transaction history.']);
        }

        $customer->delete();

        return redirect()->back()
            ->with('flash', ['success' => 'Customer successfully deleted!']);
    }

    public function apiIndex()
    {
        $customers = Customer::latest()->get();
        return response()->json([
            'data' => $customers
        ]);
    }
}
