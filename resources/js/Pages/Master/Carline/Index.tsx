import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Link, router, useForm } from "@inertiajs/react";
import { useEffect, useState, useMemo } from "react";
import ImportModal from "./ImportModal";
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    PencilIcon,
    TrashIcon,
    PlusIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ChevronUpDownIcon,
    DocumentArrowUpIcon,
    ArrowPathIcon,
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

export default function Index({ carlines, flash, filters, isLocal = false }) {
    const [showSuccess, setShowSuccess] = useState(false);
    const [showError, setShowError] = useState(false);
    const [successMessage, setSuccessMessage] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [searchTerm, setSearchTerm] = useState(filters?.search || "");
    const [isProcessing, setIsProcessing] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [sortConfig, setSortConfig] = useState({ key: 'code', direction: 'asc' });
    const [showAddModal, setShowAddModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [showImportModal, setShowImportModal] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const perPage = 10;

    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm]);

    const { data, setData, post, reset, processing: addProcessing, errors: addErrors } = useForm({
        code: "",
    });

    const { data: editData, setData: setEditData, put: putEdit, reset: resetEdit, processing: editProcessing, errors: editErrors } = useForm({
        code: "",
    });

    // Handle flash messages
    useEffect(() => {
        const successMsg = flash?.success || flash?.message || flash?.flash?.success;
        const errorMsg = flash?.error || flash?.flash?.error;

        if (successMsg) {
            setSuccessMessage(successMsg);
            setShowSuccess(true);
            setIsProcessing(false);

            const timer = setTimeout(() => {
                setShowSuccess(false);
                setSuccessMessage("");
            }, 3000);
            return () => clearTimeout(timer);
        }

        if (errorMsg) {
            setErrorMessage(errorMsg);
            setShowError(true);
            setIsProcessing(false);

            const timer = setTimeout(() => {
                setShowError(false);
                setErrorMessage("");
            }, 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Debounce search
    useEffect(() => {
        const delayDebounce = setTimeout(() => {
            if (searchTerm !== (filters?.search || "")) {
                router.get(
                    route("carline.index"),
                    { search: searchTerm || undefined },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                        only: ["carlines", "filters"],
                    }
                );
            }
        }, 500);

        return () => clearTimeout(delayDebounce);
    }, [searchTerm]);

    const handleDelete = (id, code) => {
        setDeleteTarget({ id, code });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;

        setIsProcessing(true);
        router.delete(route("carline.destroy", deleteTarget.id), {
            onSuccess: () => {
                setDeleteTarget(null);
            },
            onError: (errors) => {
                console.error("Delete failed:", errors);
                setIsProcessing(false);
                setDeleteTarget(null);
                setErrorMessage("Failed to delete carline");
                setShowError(true);
            }
        });
    };

    const handleSort = (key) => {
        setSortConfig(current => ({
            key,
            direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc'
        }));
    };

    const getSortIcon = (key) => {
        if (sortConfig.key !== key) {
            return <ChevronUpDownIcon className="w-4 h-4 text-gray-400" />;
        }
        return sortConfig.direction === 'asc'
            ? <ArrowUpIcon className="w-4 h-4 text-[#1D6F42]" />
            : <ArrowDownIcon className="w-4 h-4 text-[#1D6F42]" />;
    };

    const handleAddCarline = (e) => {
        e.preventDefault();
        post(route("carline.store"), {
            onSuccess: () => {
                setShowAddModal(false);
                reset();
            },
            onError: () => {
                // Error handling is automatic via useForm
            }
        });
    };

    const handleSyncSirep = () => {
        setIsProcessing(true);

        router.post(window.route("carline.sync-sirep"), {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    const openAddModal = () => {
        reset();
        setShowAddModal(true);
    };

    const openEditModal = (carline) => {
        setEditTarget(carline);
        setEditData("code", carline.code);
        setShowEditModal(true);
    };

    const handleEditCarline = (e) => {
        e.preventDefault();
        putEdit(route("carline.update", editTarget.id), {
            onSuccess: () => {
                setShowEditModal(false);
                setEditTarget(null);
                resetEdit();
            },
        });
    };

    // Handle both paginated object and plain array
    const carlineList = Array.isArray(carlines) ? carlines : (carlines?.data ?? []);
    const totalData = carlines?.total || carlineList.length;

    const processedCarlines = useMemo(() => {
        let filtered = carlineList.filter(c =>
            (c.code || '').toLowerCase().includes(searchTerm.toLowerCase())
        );

        if (sortConfig.key) {
            filtered.sort((a, b) => {
                let aVal = a[sortConfig.key] ?? '';
                let bVal = b[sortConfig.key] ?? '';
                const comparison = String(aVal).localeCompare(String(bVal), undefined, { numeric: true });
                return sortConfig.direction === 'asc' ? comparison : -comparison;
            });
        }

        return filtered;
    }, [carlineList, searchTerm, sortConfig]);

    const paginatedCarlines = useMemo(() => {
        const startIndex = (currentPage - 1) * perPage;
        return processedCarlines.slice(startIndex, startIndex + perPage);
    }, [processedCarlines, currentPage, perPage]);

    const totalRecords = processedCarlines.length;
    const lastPage = Math.max(1, Math.ceil(totalRecords / perPage));

    const firstRecord = totalRecords > 0 ? (currentPage - 1) * perPage + 1 : 0;
    const lastRecord = Math.min(currentPage * perPage, totalRecords);

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Carline" }]} />

                {/* Success Alert */}
                {showSuccess && successMessage && (
                    <div className="mb-6 animate-slideDown">
                        <div className="flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-200 border-l-4 border-l-[#1D6F42] shadow-sm">
                            <div className="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center text-[#1D6F42] flex-shrink-0">
                                <CheckCircleIcon className="w-5 h-5" />
                            </div>
                            <p className="flex-1 text-sm font-medium text-gray-800">{successMessage}</p>
                            <button
                                onClick={() => {
                                    setShowSuccess(false);
                                    setSuccessMessage("");
                                }}
                                className="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Error Alert */}
                {showError && errorMessage && (
                    <div className="mb-6 animate-slideDown">
                        <div className="flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-200 border-l-4 border-l-red-500 shadow-sm">
                            <div className="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center text-red-500 flex-shrink-0">
                                <ExclamationTriangleIcon className="w-5 h-5" />
                            </div>
                            <p className="flex-1 text-sm font-medium text-gray-800">{errorMessage}</p>
                            <button
                                onClick={() => {
                                    setShowError(false);
                                    setErrorMessage("");
                                }}
                                className="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Loading Overlay */}
                {(isProcessing || addProcessing) && (
                    <div className="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
                        <div className="bg-white rounded-xl p-6 shadow-xl flex items-center gap-3">
                            <div className="w-5 h-5 border-2 border-[#1D6F42] border-t-transparent rounded-full animate-spin" />
                            <span className="text-sm text-gray-700">
                                {addProcessing ? "Saving..." : "Processing..."}
                            </span>
                        </div>
                    </div>
                )}

                {/* Main Card */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                                    Carline Management
                                </h1>
                                <p className="text-sm text-gray-500 mt-1">
                                    Manage all car lines in the system
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {isLocal && (
                                    <button
                                        onClick={openAddModal}
                                        className="inline-flex items-center justify-center gap-2 h-9 px-4 bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] transition-all shadow-sm active:scale-[0.98]"
                                    >
                                        <PlusIcon className="w-4 h-4" />
                                        Add Carline
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Toolbar: Search + secondary actions */}
                    <div className="px-6 py-4 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div className="relative flex-1 max-w-md w-full">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon className="w-5 h-5 text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    className="w-full h-11 pl-10 pr-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all shadow-sm"
                                    placeholder="Search by code..."
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
                            <div className="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    onClick={handleSyncSirep}
                                    disabled={isProcessing}
                                    className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-all shadow-sm active:scale-[0.98] disabled:opacity-60"
                                >
                                    <ArrowPathIcon className={`w-5 h-5 ${isProcessing ? 'animate-spin' : ''}`} />
                                    Sync SIREP
                                </button>
                                {isLocal && (
                                    <button
                                        type="button"
                                        onClick={() => setShowImportModal(true)}
                                        className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-all shadow-sm active:scale-[0.98]"
                                    >
                                        <DocumentArrowUpIcon className="w-5 h-5" />
                                        Import
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[400px]">
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200">
                                    <th className="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-16 border-r border-gray-200">
                                        #
                                    </th>
                                    <th
                                        className="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-[#1D6F42] border-r border-gray-200"
                                        onClick={() => handleSort('code')}
                                    >
                                        <div className="flex items-center justify-center gap-1.5">
                                            Code
                                            {getSortIcon('code')}
                                        </div>
                                    </th>
                                    {isLocal && (
                                        <th className="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-28">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {paginatedCarlines.length > 0 ? (
                                    paginatedCarlines.map((carline, index) => {
                                        const rowNumber = (currentPage - 1) * perPage + index + 1;
                                        return (
                                            <tr key={carline.id} className="hover:bg-gray-50/60 transition-colors group">
                                                <td className="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-500 font-medium tabular-nums border-r border-gray-100">
                                                    {rowNumber.toString().padStart(2, '0')}
                                                </td>
                                                <td className="px-6 py-4 text-center whitespace-nowrap border-r border-gray-100">
                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-[#1D6F42] text-xs font-medium border border-green-100">
                                                        {carline.code}
                                                    </span>
                                                </td>
                                                {isLocal && (
                                                    <td className="px-6 py-4 text-center whitespace-nowrap">
                                                        <div className="flex items-center justify-center gap-2">
                                                            <button
                                                                onClick={() => openEditModal(carline)}
                                                                title="Edit"
                                                                aria-label={`Edit ${carline.code}`}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-colors"
                                                            >
                                                                <PencilIcon className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(carline.id, carline.code)}
                                                                title="Delete"
                                                                aria-label={`Delete ${carline.code}`}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 bg-white border border-red-200 hover:bg-red-50 hover:border-red-300 transition-colors"
                                                                disabled={isProcessing}
                                                            >
                                                                <TrashIcon className="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={isLocal ? 3 : 2} className="px-6 py-16 text-center">
                                            <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-gray-200">
                                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 004 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <h3 className="text-base font-semibold text-gray-800 mb-1">No carlines found</h3>
                                            <p className="text-sm text-gray-500">
                                                {searchTerm ? `No match for "${searchTerm}"` : 'No carline data yet'}
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
                                    {totalData > totalRecords && (
                                        <span className="text-gray-400 ml-1.5">(filtered from {totalData} total)</span>
                                    )}
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

                {/* Add Carline Modal */}
                {showAddModal && (
                    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                        <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                            {/* Header */}
                            <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 leading-none">Add New Carline</h3>
                                    <p className="text-xs text-gray-500 mt-1">Enter the car line code</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => { setShowAddModal(false); reset(); }}
                                    className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                    aria-label="Close modal"
                                >
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleAddCarline}>
                                <div className="p-5 space-y-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                            Code <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.code}
                                            onChange={(e) => setData("code", e.target.value.toUpperCase())}
                                            placeholder="e.g., 495D, 564D, J72A"
                                            className="w-full h-11 px-4 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            autoFocus
                                            required
                                        />
                                        {addErrors.code && (
                                            <p className="text-red-500 text-xs mt-1">{addErrors.code}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Footer */}
                                <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-3.5 shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowAddModal(false);
                                            reset();
                                        }}
                                        className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={addProcessing}
                                        className="px-4 py-2 bg-[#1D6F42] text-white rounded-xl text-xs font-semibold hover:bg-[#185c38] transition-colors disabled:opacity-50"
                                    >
                                        {addProcessing ? "Saving..." : "Save"}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Edit Carline Modal */}
                {showEditModal && editTarget && (
                    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                        <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-slideDown border border-gray-200">
                            {/* Header */}
                            <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 leading-none">Edit Carline</h3>
                                    <p className="text-xs text-gray-500 mt-1">Update the car line code</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowEditModal(false);
                                        setEditTarget(null);
                                        resetEdit();
                                    }}
                                    className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                    aria-label="Close modal"
                                >
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleEditCarline}>
                                <div className="p-5 space-y-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-gray-550 mb-1">
                                            Code <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={editData.code}
                                            onChange={(e) => setEditData("code", e.target.value.toUpperCase())}
                                            placeholder="e.g., 495D, 564D, J72A"
                                            className="w-full h-11 px-4 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            autoFocus
                                            required
                                        />
                                        {editErrors.code && (
                                            <p className="text-red-500 text-xs mt-1">{editErrors.code}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Footer */}
                                <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-3.5 shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowEditModal(false);
                                            setEditTarget(null);
                                            resetEdit();
                                        }}
                                        className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={editProcessing}
                                        className="px-4 py-2 bg-[#1D6F42] text-white rounded-xl text-xs font-semibold hover:bg-[#185c38] transition-colors disabled:opacity-50"
                                    >
                                        {editProcessing ? "Updating..." : "Update"}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Delete Confirmation Modal */}
                {deleteTarget && (
                    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
                        <div className="bg-white p-6 rounded-2xl w-full max-w-sm shadow-2xl animate-slideDown border border-gray-200">
                            <h3 className="text-lg font-semibold mb-2 text-gray-900 leading-none">
                                Delete Carline
                            </h3>
                            <p className="text-xs text-gray-500 mt-1.5 mb-6">
                                Are you sure you want to delete <b>{deleteTarget.code}</b>?
                                <br />
                                <span className="text-[11px] text-red-500 mt-2.5 block font-semibold leading-relaxed">
                                    Warning: This action cannot be undone.
                                </span>
                            </p>
                            <div className="flex justify-end gap-3">
                                <button
                                    onClick={() => setDeleteTarget(null)}
                                    className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                    disabled={isProcessing}
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={confirmDelete}
                                    className="px-4 py-2 bg-red-600 text-white rounded-xl text-xs font-semibold hover:bg-red-700 transition-colors disabled:opacity-50"
                                    disabled={isProcessing}
                                >
                                    {isProcessing ? "Deleting..." : "Delete"}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {showImportModal && (
                    <ImportModal
                        onClose={() => setShowImportModal(false)}
                        onSuccess={(message) => {
                            setShowImportModal(false);
                            setSuccessMessage(message);
                            setShowSuccess(true);
                            router.reload({ only: ["carlines"] });
                        }}
                    />
                )}
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
        </AdminLayout>
    );
}
