import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Link, useForm } from "@inertiajs/react";
import React from "react";
import { PencilIcon } from "@heroicons/react/24/outline";

interface Customer {
    id: number;
    name: string;
    code?: string;
}

interface Port {
    id: number;
    name: string;
}

interface EditProps {
    customer: Customer;
    port: Port;
}

export default function Edit({ customer, port }: EditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: port.name,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/customers/${customer.id}/ports/${port.id}`);
    };

    const inputClass = (hasError = false) =>
        `w-full h-11 px-4 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all ${
            hasError ? "border-red-400 focus:ring-red-200 focus:border-red-400" : "border-gray-200"
        }`;

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb
                    items={[
                        { label: "Masters" },
                        { label: "Ports", href: "/ports" },
                        { label: customer.name, href: `/customers/${customer.id}/ports` },
                        { label: "Edit Port" },
                    ]}
                />

                <div className="max-w-2xl mx-auto mt-4">
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        {/* Header */}
                        <div className="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div className="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                                <PencilIcon className="w-5 h-5 text-amber-600" />
                            </div>
                            <div>
                                <h1 className="text-xl font-semibold text-gray-900">Edit Port</h1>
                                <p className="text-sm text-gray-500 mt-0.5">Update port details for <b>{customer.name}</b></p>
                            </div>
                        </div>

                        {/* Form */}
                        <form onSubmit={handleSubmit}>
                            <div className="p-6 space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                                        Port Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="Enter port name"
                                        className={inputClass(!!errors.name)}
                                        autoFocus
                                    />
                                    {errors.name && (
                                        <p className="text-red-500 text-xs mt-1.5 font-medium">{errors.name}</p>
                                    )}
                                </div>
                            </div>

                            {/* Footer / Actions */}
                            <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                                <Link
                                    href={`/customers/${customer.id}/ports`}
                                    className="inline-flex h-11 px-5 items-center justify-center border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 px-5 items-center justify-center bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] transition-colors disabled:opacity-50 shadow-sm active:scale-[0.98]"
                                >
                                    {processing ? "Updating..." : "Update Port"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
