import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { router, useForm } from "@inertiajs/react";
import { useState, useMemo, useEffect } from "react";
import {
    MagnifyingGlassIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
} from "@heroicons/react/24/outline";

type Customer = { id: number; name: string };
type Port = { id: number; name: string };
type Flash = { success?: string; warning?: string; error?: string };

export default function Index({
    customer,
    ports,
    flash = {},
}: {
    customer: Customer;
    ports: Port[];
    flash?: Flash;
}) {
    const [search, setSearch] = useState("");
    const [sortDir, setSortDir] = useState<"asc" | "desc">("asc");
    const [deletePort, setDeletePort] = useState<Port | null>(null);
    const [showAlert, setShowAlert] = useState(false);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editTarget, setEditTarget] = useState<Port | null>(null);

    // Add form
    const {
        data: addData, setData: setAddData, post, processing: addProcessing,
        errors: addErrors, reset: resetAdd,
    } = useForm({ name: "" });

    // Edit form
    const {
        data: editData, setData: setEditData, put, processing: editProcessing,
        errors: editErrors, reset: resetEdit,
    } = useForm({ name: "" });

    useEffect(() => {
        if (flash?.success || flash?.error || flash?.warning) {
            setShowAlert(true);
            const timer = setTimeout(() => setShowAlert(false), 4500);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const processedPorts = useMemo(() => {
        const filtered = ports.filter((p) =>
            p.name.toLowerCase().includes(search.toLowerCase())
        );
        filtered.sort((a, b) => {
            const comp = a.name.localeCompare(b.name);
            return sortDir === "asc" ? comp : -comp;
        });
        return filtered;
    }, [ports, search, sortDir]);

    const openAddModal = () => {
        resetAdd();
        setShowAddModal(true);
    };

    const openEditModal = (port: Port) => {
        setEditTarget(port);
        setEditData("name", port.name);
    };

    const handleAdd = (e) => {
        e.preventDefault();
        post(`/customers/${customer.id}/ports`, {
            onSuccess: () => { setShowAddModal(false); resetAdd(); },
        });
    };

    const handleEdit = (e) => {
        e.preventDefault();
        put(`/customers/${customer.id}/ports/${editTarget!.id}`, {
            onSuccess: () => { setEditTarget(null); resetEdit(); },
        });
    };

    const confirmDelete = () => {
        if (!deletePort) return;
        router.delete(`/customers/${customer.id}/ports/${deletePort.id}`);
        setDeletePort(null);
    };

    const inputClass = (hasError = false) =>
        `w-full h-11 px-4 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all ${
            hasError ? "border-red-400" : "border-gray-200"
        }`;

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Ports", href: "/ports" }, { label: `Manage ${customer.name}` }]} />

                {/* Alert */}
                {showAlert && (flash?.success || flash?.error || flash?.warning) && (
                    <div className="mb-6 animate-slideDown">
                        <div className={`flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-200 border-l-4 shadow-sm ${
                            flash?.error ? "border-l-red-500" : flash?.warning ? "border-l-amber-500" : "border-l-[#1D6F42]"
                        }`}>
                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${
                                flash?.error ? "bg-red-50 text-red-500" : flash?.warning ? "bg-amber-50 text-amber-600" : "bg-green-50 text-[#1D6F42]"
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

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    {/* Header */}
                    <div className="px-6 py-5 border-b border-gray-100">
                        <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">Manage Ports</h1>
                        <p className="text-sm text-gray-500 mt-1">Customer: {customer.name}</p>
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
                                    placeholder="Search ports..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full h-11 pl-10 pr-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all shadow-sm"
                                />
                                {search && (
                                    <button
                                        onClick={() => setSearch("")}
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
                                Add Port
                            </button>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[500px]">
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200">
                                    <th className="px-4 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-16 border-r border-gray-200">#</th>
                                    <th
                                        className="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-[#1D6F42] border-r border-gray-200"
                                        onClick={() => setSortDir(sortDir === "asc" ? "desc" : "asc")}
                                    >
                                        <div className="flex items-center gap-1.5">
                                            Port Name
                                            {sortDir === "asc" ? (
                                                <ArrowUpIcon className="w-4 h-4 text-[#1D6F42]" />
                                            ) : (
                                                <ArrowDownIcon className="w-4 h-4 text-[#1D6F42]" />
                                            )}
                                        </div>
                                    </th>
                                    <th className="px-4 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-36">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {processedPorts.length > 0 ? (
                                    processedPorts.map((port, index) => (
                                        <tr key={port.id} className="hover:bg-gray-50/60 transition-colors">
                                            <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500 font-medium tabular-nums border-r border-gray-100 text-center">
                                                {(index + 1).toString().padStart(2, "0")}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900 border-r border-gray-100">
                                                {port.name}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-center">
                                                <div className="flex items-center gap-2 justify-center">
                                                    <button
                                                        onClick={() => openEditModal(port)}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-colors shadow-sm"
                                                        title="Edit"
                                                    >
                                                        <PencilIcon className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => setDeletePort(port)}
                                                        title="Delete"
                                                        aria-label={`Delete ${port.name}`}
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
                                        <td colSpan={3} className="py-16 text-center">
                                            <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-gray-200">
                                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 004 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <h3 className="text-base font-semibold text-gray-800 mb-1">No ports found</h3>
                                            <p className="text-sm text-gray-500">
                                                {search ? `No match for "${search}"` : "No ports available for this customer"}
                                            </p>
                                            {search && (
                                                <button
                                                    onClick={() => setSearch("")}
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
                    {processedPorts.length > 0 && (
                        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center text-sm text-gray-600">
                            <span className="w-2 h-2 bg-[#1D6F42] rounded-full animate-pulse mr-2" />
                            Showing {processedPorts.length} port{processedPorts.length !== 1 ? "s" : ""}
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

            {/* ── Add Port Modal ── */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                        {/* Header */}
                        <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 leading-none">Add Port</h3>
                                <p className="text-xs text-gray-500 mt-1">Add a new port for <b>{customer.name}</b>.</p>
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
                                        Port Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={addData.name}
                                        onChange={(e) => setAddData("name", e.target.value)}
                                        placeholder="Enter port name"
                                        className={inputClass(!!addErrors.name)}
                                        autoFocus
                                    />
                                    {addErrors.name && <p className="text-red-500 text-xs mt-1">{addErrors.name}</p>}
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
                                    {addProcessing ? "Saving..." : "Save Port"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Edit Port Modal ── */}
            {editTarget && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                        {/* Header */}
                        <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 leading-none">Edit Port</h3>
                                <p className="text-xs text-gray-500 mt-1">Update port for <b>{customer.name}</b>.</p>
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
                                        Port Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={editData.name}
                                        onChange={(e) => setEditData("name", e.target.value)}
                                        placeholder="Enter port name"
                                        className={inputClass(!!editErrors.name)}
                                        autoFocus
                                    />
                                    {editErrors.name && <p className="text-red-500 text-xs mt-1">{editErrors.name}</p>}
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
                                    {editProcessing ? "Updating..." : "Update Port"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Delete Confirmation Modal ── */}
            {deletePort && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                    <div className="bg-white p-6 rounded-2xl w-full max-w-sm shadow-2xl animate-slideDown border border-gray-200">
                        <h3 className="text-lg font-semibold mb-2 text-gray-900 leading-none">Delete Port</h3>
                        <p className="text-xs text-gray-500 mt-1.5 mb-6">
                            Are you sure you want to delete <b>{deletePort.name}</b>?
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setDeletePort(null)}
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
