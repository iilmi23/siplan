import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Link, router } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    PencilIcon,
    TrashIcon,
    PlusIcon,
    DocumentDuplicateIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ChevronUpDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from "@heroicons/react/24/outline";

const getVisiblePages = (currentPage, lastPage) => {
    const maxVisible = 5;
    if (!lastPage || lastPage <= maxVisible) {
        return Array.from({ length: lastPage || 0 }, (_, index) => index + 1);
    }

    let start = currentPage - 2;
    let end = currentPage + 2;

    if (start < 1) {
        start = 1;
        end = maxVisible;
    } else if (end > lastPage) {
        end = lastPage;
        start = lastPage - maxVisible + 1;
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
};

export default function Index({ templates = [], flash }) {
    const [showAlert, setShowAlert] = useState(false);
    const [searchTerm, setSearchTerm] = useState("");
    const [sortConfig, setSortConfig] = useState({ key: "customer_code", direction: "asc" });
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(10);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [isProcessing, setIsProcessing] = useState(false);

    useEffect(() => {
        if (flash?.success || flash?.error || flash?.warning) {
            setShowAlert(true);
            const timer = setTimeout(() => setShowAlert(false), 4500);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm]);

    const handleDelete = (template) => {
        setDeleteTarget(template);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        setIsProcessing(true);
        router.delete(route("sr-mapping-templates.destroy", deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsProcessing(false);
            },
            onError: () => {
                setIsProcessing(false);
            }
        });
    };

    const handleSort = (key) => {
        setSortConfig((current) => ({
            key,
            direction: current.key === key && current.direction === "asc" ? "desc" : "asc",
        }));
    };

    const getSortIcon = (key) => {
        if (sortConfig.key !== key) {
            return <ChevronUpDownIcon className="w-4 h-4 text-gray-400" />;
        }
        return sortConfig.direction === "asc"
            ? <ArrowUpIcon className="w-4 h-4 text-[#1D6F42]" />
            : <ArrowDownIcon className="w-4 h-4 text-[#1D6F42]" />;
    };

    const processedTemplates = useMemo(() => {
        if (!templates) return [];

        let filtered = templates.filter((t) => {
            const search = searchTerm.toLowerCase();
            const custCode = (t.customer?.code || "").toLowerCase();
            const custName = (t.customer?.name || "").toLowerCase();
            const tName = (t.name || "").toLowerCase();
            return custCode.includes(search) || custName.includes(search) || tName.includes(search);
        });

        if (sortConfig.key) {
            filtered.sort((a, b) => {
                let aVal = "";
                let bVal = "";

                if (sortConfig.key === "customer_code") {
                    aVal = a.customer?.code ?? "";
                    bVal = b.customer?.code ?? "";
                } else if (sortConfig.key === "customer_name") {
                    aVal = a.customer?.name ?? "";
                    bVal = b.customer?.name ?? "";
                } else {
                    aVal = a[sortConfig.key] ?? "";
                    bVal = b[sortConfig.key] ?? "";
                }

                const comparison = String(aVal).localeCompare(String(bVal), undefined, { numeric: true });
                return sortConfig.direction === "asc" ? comparison : -comparison;
            });
        }

        return filtered;
    }, [templates, searchTerm, sortConfig]);

    const paginatedTemplates = useMemo(() => {
        const startIndex = (currentPage - 1) * perPage;
        return processedTemplates.slice(startIndex, startIndex + perPage);
    }, [processedTemplates, currentPage, perPage]);

    const totalRecords = processedTemplates.length;
    const lastPage = Math.max(1, Math.ceil(totalRecords / perPage));

    const firstRecord = totalRecords > 0 ? (currentPage - 1) * perPage + 1 : 0;
    const lastRecord = Math.min(currentPage * perPage, totalRecords);

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "SR Templates" }]} />

                {/* Alert */}
                {showAlert && (flash?.success || flash?.error || flash?.warning) && (
                    <div className="mb-6 animate-slideDown">
                        <div className={`flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-200 border-l-4 shadow-sm ${
                            flash?.error ? 'border-l-red-500' : flash?.warning ? 'border-l-amber-500' : 'border-l-[#1D6F42]'
                        }`}>
                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${
                                flash?.error ? 'bg-red-50 text-red-500' : flash?.warning ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-[#1D6F42]'
                            }`}>
                                {flash?.error || flash?.warning ? (
                                    <ExclamationTriangleIcon className="w-5 h-5" />
                                ) : (
                                    <CheckCircleIcon className="w-5 h-5" />
                                )}
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

                {/* Loading Overlay */}
                {isProcessing && (
                    <div className="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
                        <div className="bg-white rounded-xl p-6 shadow-xl flex items-center gap-3">
                            <div className="w-5 h-5 border-2 border-[#1D6F42] border-t-transparent rounded-full animate-spin" />
                            <span className="text-sm text-gray-700">Processing...</span>
                        </div>
                    </div>
                )}

                {/* Main Card */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    {/* Header */}
                    <div className="p-6 pb-4 border-b border-gray-100">
                        <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                            SR Mapping Templates
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage column mapping configurations for SR Excel data dynamically per customer.
                        </p>
                    </div>

                    {/* Toolbar */}
                    <div className="px-6 py-4 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                            {/* Search */}
                            <div className="relative flex-1 max-w-md w-full">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon className="w-5 h-5 text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    className="w-full h-11 pl-10 pr-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all shadow-sm"
                                    placeholder="Search template name or customer code..."
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

                            <div className="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                                <Link
                                    href={route("sr-mapping-templates.create")}
                                    className="inline-flex items-center justify-center gap-2 h-11 px-5 bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] transition-all shadow-sm active:scale-[0.98] w-full sm:w-auto shrink-0"
                                >
                                    <PlusIcon className="w-5 h-5" />
                                    Add Template
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50">
                        <table className="w-full min-w-[900px]">
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <th className="px-4 py-4 border-r border-gray-200 text-center w-12">
                                        #
                                    </th>
                                    <th
                                        className="px-3 py-4 border-r border-gray-200 cursor-pointer hover:text-[#1D6F42] w-[95px] text-center"
                                        onClick={() => handleSort('customer_code')}
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            Customer
                                            {getSortIcon('customer_code')}
                                        </div>
                                    </th>
                                    <th
                                        className="px-4 py-4 border-r border-gray-200 cursor-pointer hover:text-[#1D6F42]"
                                        onClick={() => handleSort('name')}
                                    >
                                        <div className="flex items-center gap-1.5">
                                            Name
                                            {getSortIcon('name')}
                                        </div>
                                    </th>
                                    <th
                                        className="px-3 py-4 border-r border-gray-200 cursor-pointer hover:text-[#1D6F42] w-[110px] text-center"
                                        onClick={() => handleSort('orientation')}
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            Mode
                                            {getSortIcon('orientation')}
                                        </div>
                                    </th>
                                    <th className="px-3 py-4 border-r border-gray-200 w-[130px] text-center">
                                        Row
                                    </th>
                                    <th className="px-3 py-4 border-r border-gray-200 w-[160px] text-center">
                                        Column
                                    </th>
                                    <th
                                        className="px-3 py-4 border-r border-gray-200 cursor-pointer hover:text-[#1D6F42] w-[110px] text-center"
                                        onClick={() => handleSort('is_active')}
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            Status
                                            {getSortIcon('is_active')}
                                        </div>
                                    </th>
                                    <th className="px-4 py-4 w-24 text-center">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {paginatedTemplates.length > 0 ? (
                                    paginatedTemplates.map((template, index) => {
                                        const rowNumber = (currentPage - 1) * perPage + index + 1;
                                        return (
                                            <tr key={template.id} className="hover:bg-gray-50/60 transition-colors group">
                                                <td className="px-4 py-4 text-center whitespace-nowrap text-sm text-gray-500 font-medium tabular-nums border-r border-gray-100 align-middle">
                                                    {rowNumber.toString().padStart(2, '0')}
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100 align-middle text-center">
                                                    <span className="inline-flex items-center justify-center px-2.5 py-1 text-xs font-semibold text-[#1D6F42] bg-green-50 border border-green-100 rounded-lg uppercase">
                                                        {template.customer?.code}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 border-r border-gray-100 align-middle">
                                                    <span className="text-sm font-semibold text-gray-800 leading-tight block">
                                                        {template.name}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100 whitespace-nowrap align-middle text-center">
                                                    <span className={`inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${template.orientation === 'horizontal'
                                                        ? 'border-sky-100 bg-sky-50 text-sky-700'
                                                        : 'border-purple-100 bg-purple-50 text-purple-700'
                                                        }`}>
                                                        {template.orientation}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100 align-middle text-xs font-medium text-gray-700 text-center">
                                                    <div className="space-y-1 inline-block text-left">
                                                        <div>Header: <span className="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-800 font-bold">{template.header_row || "-"}</span></div>
                                                        <div>Start: <span className="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-800 font-bold">{template.data_start_row}</span></div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100 align-middle text-xs font-medium text-gray-700 text-center">
                                                    <div className="space-y-1 inline-block text-left">
                                                        <div>Assy: <span className="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-800 font-bold">{template.assy_number_column}</span></div>
                                                        <div>Qty: <span className="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-800 font-bold whitespace-nowrap">{template.qty_column || `${template.qty_start_column} ~ ${template.qty_end_column}`}</span></div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100 whitespace-nowrap align-middle text-center">
                                                    <span className={`inline-flex items-center justify-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${template.is_active
                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                                                        : 'bg-slate-100 text-slate-500 border-slate-200'
                                                        }`}>
                                                        {template.is_active ? (
                                                            <>
                                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                                                Active
                                                            </>
                                                        ) : (
                                                            <>
                                                                <span className="h-1.5 w-1.5 rounded-full bg-slate-400" />
                                                                Inactive
                                                            </>
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 whitespace-nowrap align-middle">
                                                    <div className="flex items-center gap-2 justify-center">
                                                        <Link
                                                            href={route("sr-mapping-templates.edit", template.id)}
                                                            title="Edit Template"
                                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-colors shadow-sm"
                                                        >
                                                            <PencilIcon className="w-4 h-4" />
                                                        </Link>
                                                        <button
                                                            onClick={() => handleDelete(template)}
                                                            title="Delete"
                                                            aria-label={`Delete ${template.name}`}
                                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 bg-white border border-red-200 hover:bg-red-50 hover:border-red-300 transition-colors shadow-sm"
                                                        >
                                                            <TrashIcon className="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="py-16 text-center">
                                            <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-gray-200">
                                                <DocumentDuplicateIcon className="w-8 h-8 text-gray-400" />
                                            </div>
                                            <h3 className="text-base font-semibold text-gray-800 mb-1">No templates found</h3>
                                            <p className="text-sm text-gray-500">
                                                {searchTerm ? `No match for "${searchTerm}"` : 'No SR templates data yet'}
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

                    {/* Footer / Pagination Controls */}
                    {totalRecords > 0 && (
                        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                            <div className="text-sm text-gray-500 flex items-center gap-2">
                                <span className="w-2 h-2 bg-[#1D6F42] rounded-full animate-pulse" />
                                <span>
                                    Showing {firstRecord} - {lastRecord} of {totalRecords} records
                                </span>
                            </div>

                            {lastPage > 1 && (
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => setCurrentPage(currentPage - 1)}
                                        disabled={currentPage === 1}
                                        className={`p-2 rounded-lg border text-sm transition-colors ${currentPage === 1
                                            ? "opacity-40 cursor-not-allowed border-gray-200 text-gray-400"
                                            : "border-gray-300 text-gray-700 hover:bg-gray-100"
                                            }`}
                                        aria-label="Previous page"
                                    >
                                        <ChevronLeftIcon className="w-4 h-4" />
                                    </button>

                                    {getVisiblePages(currentPage, lastPage).map((page) => (
                                        <button
                                            key={page}
                                            type="button"
                                            onClick={() => setCurrentPage(page)}
                                            className={`w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors ${page === currentPage
                                                ? "bg-[#1D6F42] text-white"
                                                : "bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"
                                                }`}
                                        >
                                            {page}
                                        </button>
                                    ))}

                                    <button
                                        type="button"
                                        onClick={() => setCurrentPage(currentPage + 1)}
                                        disabled={currentPage === lastPage}
                                        className={`p-2 rounded-lg border text-sm transition-colors ${currentPage === lastPage
                                            ? "opacity-40 cursor-not-allowed border-gray-200 text-gray-400"
                                            : "border-gray-300 text-gray-700 hover:bg-gray-100"
                                            }`}
                                        aria-label="Next page"
                                    >
                                        <ChevronRightIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <style>{`
                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                .animate-slideDown {
                    animation: slideDown 0.25s ease-out;
                }
            `}</style>

            {/* Delete Modal */}
            {deleteTarget && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div className="bg-white p-6 rounded-xl w-96 shadow-xl">
                        <h3 className="text-lg font-semibold mb-2 text-gray-900">
                            Delete SR Template
                        </h3>
                        <p className="text-sm text-gray-600 mb-6">
                            Are you sure you want to delete <b>{deleteTarget.name}</b>?
                            <br />
                            <span className="text-xs text-red-500 mt-1 block">
                                Warning: This action cannot be undone.
                            </span>
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setDeleteTarget(null)}
                                className="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                                disabled={isProcessing}
                            >
                                Cancel
                            </button>
                            <button
                                onClick={confirmDelete}
                                className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                                disabled={isProcessing}
                            >
                                {isProcessing ? "Deleting..." : "Delete"}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
