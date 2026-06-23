import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { router, useForm } from "@inertiajs/react";
import { useEffect, useState, useMemo } from "react";
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    PencilIcon,
    TrashIcon,
    PlusIcon,
    UserGroupIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ChevronUpDownIcon,
} from "@heroicons/react/24/outline";

export default function Index({ customers, flash }) {
    const [showAlert, setShowAlert] = useState(false);
    const [searchTerm, setSearchTerm] = useState("");
    const [sortConfig, setSortConfig] = useState({ key: "name", direction: "asc" });
    const [deleteCustomer, setDeleteCustomer] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editTarget, setEditTarget] = useState(null);

    // Add form
    const {
        data: addData, setData: setAddData, post, processing: addProcessing,
        errors: addErrors, reset: resetAdd,
    } = useForm({ name: "", code: "" });

    // Edit form
    const {
        data: editData, setData: setEditData, put, processing: editProcessing,
        errors: editErrors, reset: resetEdit,
    } = useForm({ name: "", code: "" });

    useEffect(() => {
        if (flash?.success || flash?.error || flash?.warning) {
            setShowAlert(true);
            const timer = setTimeout(() => setShowAlert(false), 4500);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const openAddModal = () => {
        resetAdd();
        setShowAddModal(true);
    };

    const openEditModal = (customer) => {
        setEditTarget(customer);
        setEditData("name", customer.name);
        setEditData("code", customer.code || "");
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post("/customers", {
            onSuccess: () => { setShowAddModal(false); resetAdd(); },
        });
    };

    const handleEdit = (e) => {
        e.preventDefault();
        put(`/customers/${editTarget.id}`, {
            onSuccess: () => { setEditTarget(null); resetEdit(); },
        });
    };

    const confirmDelete = () => {
        router.delete(`/customers/${deleteCustomer.id}`);
        setDeleteCustomer(null);
    };

    const handleSort = (key) => {
        setSortConfig((current) => ({
            key,
            direction: current.key === key && current.direction === "asc" ? "desc" : "asc",
        }));
    };

    const getSortIcon = (key) => {
        if (sortConfig.key !== key) return <ChevronUpDownIcon className="w-4 h-4 text-gray-400" />;
        return sortConfig.direction === "asc"
            ? <ArrowUpIcon className="w-4 h-4 text-[#1D6F42]" />
            : <ArrowDownIcon className="w-4 h-4 text-[#1D6F42]" />;
    };

    const processedCustomers = useMemo(() => {
        if (!customers) return [];
        let filtered = customers.filter(
            (c) =>
                (c.name || "").toLowerCase().includes(searchTerm.toLowerCase()) ||
                (c.code || "").toLowerCase().includes(searchTerm.toLowerCase())
        );
        if (sortConfig.key) {
            filtered.sort((a, b) => {
                const aVal = a[sortConfig.key] ?? "";
                const bVal = b[sortConfig.key] ?? "";
                const comparison = String(aVal).localeCompare(String(bVal), undefined, { numeric: true });
                return sortConfig.direction === "asc" ? comparison : -comparison;
            });
        }
        return filtered;
    }, [customers, searchTerm, sortConfig]);

    const inputClass = (hasError = false) =>
        `w-full h-11 px-4 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all ${hasError ? "border-red-400" : "border-gray-200"
        }`;

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Customers" }]} />

                {/* Alert */}
                {showAlert && (flash?.success || flash?.error || flash?.warning) && (
                    <div className="mb-6 animate-slideDown">
                        <div className={`flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-200 border-l-4 shadow-sm ${flash?.error ? "border-l-red-500" : flash?.warning ? "border-l-amber-500" : "border-l-[#1D6F42]"
                            }`}>
                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${flash?.error ? "bg-red-50 text-red-500" : flash?.warning ? "bg-amber-50 text-amber-600" : "bg-green-50 text-[#1D6F42]"
                                }`}>
                                {flash?.error || flash?.warning
                                    ? <ExclamationTriangleIcon className="w-5 h-5" />
                                    : <CheckCircleIcon className="w-5 h-5" />}
                            </div>
                            <p className="flex-1 text-sm font-medium text-gray-800">
                                {flash?.success || flash?.error || flash?.warning}
                            </p>
                            <button
                                onClick={() => setShowAlert(false)}
                                className="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Main Card */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    {/* Header */}
                    <div className="px-6 py-5 border-b border-gray-100">
                        <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">Customer List</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage all customers and their codes in the system.</p>
                    </div>

                    {/* Toolbar */}
                    <div className="px-6 py-4 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="relative flex-1 max-w-md w-full">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon className="w-5 h-5 text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    className="w-full h-11 pl-10 pr-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all shadow-sm"
                                    placeholder="Search name or code..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                />
                                {searchTerm && (
                                    <button
                                        onClick={() => setSearchTerm("")}
                                        className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        <XMarkIcon className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                            <button
                                onClick={openAddModal}
                                className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] transition-all shadow-sm active:scale-[0.98] shrink-0 w-full sm:w-auto"
                            >
                                <PlusIcon className="w-4 h-4" />
                                Add Customer
                            </button>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50">
                        <table className="w-full min-w-[700px]">
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200">
                                    <th className="px-4 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-16 border-r border-gray-200">#</th>
                                    <th
                                        className="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-[#1D6F42] border-r border-gray-200"
                                        onClick={() => handleSort("name")}
                                    >
                                        <div className="flex items-center gap-1.5">Customer Name {getSortIcon("name")}</div>
                                    </th>
                                    <th className="px-4 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-36 border-r border-gray-200">Code</th>
                                    <th className="px-4 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-36">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {processedCustomers.length > 0 ? (
                                    processedCustomers.map((customer, index) => (
                                        <tr key={customer.id} className="hover:bg-gray-50/60 transition-colors">
                                            <td className="px-4 py-4 text-center whitespace-nowrap text-sm text-gray-500 font-medium tabular-nums border-r border-gray-100">
                                                {(index + 1).toString().padStart(2, "0")}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r border-gray-100">
                                                {customer.name}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-center border-r border-gray-100">
                                                {customer.code?.trim() ? (
                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-[#1D6F42] text-xs font-medium border border-green-100">
                                                        {customer.code}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-gray-300">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-center">
                                                <div className="flex items-center gap-2 justify-center">
                                                    <button
                                                        onClick={() => openEditModal(customer)}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-colors shadow-sm"
                                                        title="Edit"
                                                    >
                                                        <PencilIcon className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => setDeleteCustomer(customer)}
                                                        title="Delete"
                                                        aria-label={`Delete ${customer.name}`}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 bg-white border border-red-200 hover:bg-red-50 hover:border-red-300 transition-colors shadow-sm"
                                                    >
                                                        <TrashIcon className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="py-16 text-center">
                                            <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-gray-200">
                                                <UserGroupIcon className="w-8 h-8 text-gray-400" />
                                            </div>
                                            <h3 className="text-base font-semibold text-gray-800 mb-1">No customers found</h3>
                                            <p className="text-sm text-gray-500">
                                                {searchTerm ? `No match for "${searchTerm}"` : "No customer data yet"}
                                            </p>
                                            {searchTerm && (
                                                <button
                                                    onClick={() => setSearchTerm("")}
                                                    className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50"
                                                >
                                                    <XMarkIcon className="w-4 h-4" />
                                                    Clear search
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer */}
                    {processedCustomers.length > 0 && (
                        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-sm text-gray-600">
                            <div className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-[#1D6F42] rounded-full animate-pulse" />
                                Showing {processedCustomers.length} customer{processedCustomers.length !== 1 ? "s" : ""}
                            </div>
                            <div>
                                {processedCustomers.length !== (customers?.length || 0) && "Filtered results"}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <style>{`
                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                .animate-slideDown { animation: slideDown 0.25s ease-out; }
            `}</style>

            {/* ── Add Customer Modal ── */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                        {/* Header */}
                        <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 leading-none">Add Customer</h3>
                                <p className="text-xs text-gray-500 mt-1">Add a new customer to the master list.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => { setShowAddModal(false); resetAdd(); }}
                                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                aria-label="Close modal"
                            >
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleAdd}>
                            <div className="p-5 space-y-4">
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                        Customer Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={addData.name}
                                        onChange={(e) => setAddData("name", e.target.value)}
                                        placeholder="Enter customer name"
                                        className={inputClass(!!addErrors.name)}
                                        autoFocus
                                    />
                                    {addErrors.name && <p className="text-red-500 text-xs mt-1">{addErrors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                        Customer Code <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={addData.code}
                                        onChange={(e) => setAddData("code", e.target.value)}
                                        placeholder="Enter customer code"
                                        className={inputClass(!!addErrors.code)}
                                    />
                                    {addErrors.code && <p className="text-red-500 text-xs mt-1">{addErrors.code}</p>}
                                </div>
                            </div>
                            {/* Footer */}
                            <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-3.5 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => { setShowAddModal(false); resetAdd(); }}
                                    className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={addProcessing}
                                    className="px-4 py-2 bg-[#1D6F42] text-white rounded-xl text-xs font-semibold hover:bg-[#185c38] transition-colors disabled:opacity-50"
                                >
                                    {addProcessing ? "Saving..." : "Save Customer"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Edit Customer Modal ── */}
            {editTarget && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                        {/* Header */}
                        <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 leading-none">Edit Customer</h3>
                                <p className="text-xs text-gray-500 mt-1">Update details for <b>{editTarget.name}</b>.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => { setEditTarget(null); resetEdit(); }}
                                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                aria-label="Close modal"
                            >
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleEdit}>
                            <div className="p-5 space-y-4">
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                        Customer Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={editData.name}
                                        onChange={(e) => setEditData("name", e.target.value)}
                                        placeholder="Enter customer name"
                                        className={inputClass(!!editErrors.name)}
                                        autoFocus
                                    />
                                    {editErrors.name && <p className="text-red-500 text-xs mt-1">{editErrors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                        Customer Code <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={editData.code}
                                        onChange={(e) => setEditData("code", e.target.value)}
                                        placeholder="Enter customer code"
                                        className={inputClass(!!editErrors.code)}
                                    />
                                    {editErrors.code && <p className="text-red-500 text-xs mt-1">{editErrors.code}</p>}
                                </div>
                            </div>
                            {/* Footer */}
                            <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-3.5 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => { setEditTarget(null); resetEdit(); }}
                                    className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editProcessing}
                                    className="px-4 py-2 bg-[#1D6F42] text-white rounded-xl text-xs font-semibold hover:bg-[#185c38] transition-colors disabled:opacity-50"
                                >
                                    {editProcessing ? "Updating..." : "Update Customer"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Delete Confirmation Modal ── */}
            {deleteCustomer && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white p-6 rounded-2xl w-full max-w-sm shadow-2xl animate-slideDown border border-gray-200">
                        <h3 className="text-lg font-semibold mb-2 text-gray-900 leading-none">Delete Customer</h3>
                        <p className="text-xs text-gray-500 mt-1.5 mb-6">
                            Are you sure you want to delete customer <b>{deleteCustomer.name}</b>?<br />
                            <span className="text-[11px] text-red-500 mt-2.5 block font-semibold leading-relaxed">
                                Warning: This action is permanent and will delete all ports, mapping rules (SR Templates), and data associated with this customer.
                            </span>
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setDeleteCustomer(null)}
                                className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={confirmDelete}
                                className="px-4 py-2 bg-red-600 text-white rounded-xl text-xs font-semibold hover:bg-red-700 transition-colors"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
