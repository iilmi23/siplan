<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

use App\Models\Customer;
use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PortController extends Controller
{
    public function index(Customer $customer)
    {
        $ports = $customer->ports()->latest()->get();

        return Inertia::render('Master/Ports/Index', [
            'customer' => $customer,
            'ports' => $ports,
            'flash' => session('flash') ?: [
                'success' => session('success'),
                'warning' => session('warning'),
                'error' => session('error'),
            ],
        ]);
    }

    public function all()
    {
        $customers = Customer::withCount('ports')->orderBy('name')->get();

        return Inertia::render('Master/Ports/All', [
            'customers' => $customers,
            'flash' => session('flash') ?: [
                'success' => session('success'),
                'warning' => session('warning'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(Customer $customer)
    {
        return Inertia::render('Master/Ports/Create', [
            'customer' => $customer
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ports', 'name')->where('customer_id', $customer->id)
            ]
        ], [
            'name.unique' => 'Port with this name already exists for this customer.'
        ]);

        $customer->ports()->create([
            'name' => $validated['name']
        ]);

        return redirect()
            ->route('customers.ports.index', $customer->id)
            ->with('success', 'Port created successfully');
    }

    public function edit(Customer $customer, Port $port)
    {
        return Inertia::render('Master/Ports/Edit', [
            'customer' => $customer,
            'port' => $port
        ]);
    }

    public function update(Request $request, Customer $customer, Port $port)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ports', 'name')->where('customer_id', $customer->id)->ignore($port->id)
            ]
        ], [
            'name.unique' => 'Port with this name already exists for this customer.'
        ]);

        $port->update([
            'name' => $validated['name']
        ]);

        return redirect()
            ->route('customers.ports.index', $customer->id)
            ->with('success', 'Port updated successfully');
    }

    public function destroy(Customer $customer, Port $port)
    {
        if (\App\Models\SR::where('port_id', $port->id)->exists() || 
            \App\Models\SPP::where('port_id', $port->id)->exists() ||
            \App\Models\SppBatch::where('port_id', $port->id)->exists()
        ) {
            return redirect()
                ->route('customers.ports.index', $customer->id)
                ->with('error', 'Port cannot be deleted because it is referenced in transactions.');
        }

        $port->delete();

        return redirect()
            ->route('customers.ports.index', $customer->id)
            ->with('success', 'Port deleted successfully');
    }
}
