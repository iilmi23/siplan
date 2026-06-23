import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { usePage, useForm, router } from "@inertiajs/react";
import { type SyntheticEvent, useEffect, useMemo, useState } from "react";
import type { SharedPageProps } from "@/types/global";
import {
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon as ChevronNextIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";
import { Assy, Carline } from "@/types/models";

// Import subcomponents
import AssyFilters from "./Components/AssyFilters";
import AssyTable from "./Components/AssyTable";
import AssyFormModal from "./Components/AssyFormModal";
import DeleteConfirmModal from "./Components/DeleteConfirmModal";

const normalizeStatusFilter = (value: any) => {
    if (value === true) return "1";
    if (value === false) return "0";
    if (value === 1) return "1";
    if (value === 0) return "0";
    return value ?? "";
};

const normalizeIdFilter = (value: any) => {
    if (value === null || value === undefined) return "";
    return String(value);
};

const getVisiblePages = (currentPage: number, lastPage: number) => {
    const maxVisible = 5;
    if (!lastPage || lastPage <= maxVisible) {
        return Array.from({ length: lastPage || 0 }, (_, index) => index + 1);
    }

    if (currentPage <= 3) {
        return Array.from({ length: maxVisible }, (_, index) => index + 1);
    }

    if (currentPage >= lastPage - 2) {
        return Array.from({ length: maxVisible }, (_, index) => lastPage - maxVisible + index + 1);
    }

    return Array.from({ length: maxVisible }, (_, index) => currentPage - 2 + index);
};

type AssyFiltersType = {
    search?: string;
    carline_id?: string | number | null;
    is_active?: string | number | boolean | null;
    per_page?: string | number | null;
};

type Flash = {
    success?: string;
    message?: string;
    warning?: string;
    error?: string;
};

const PER_PAGE_OPTIONS = [20, 50, 100, 200];

export default function Index({
    assy,
    carlines = [],
    filters = {},
    flash: propFlash,
}: {
    assy: {
        data: Assy[];
        current_page: number;
        last_page: number;
        per_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    carlines?: Carline[];
    filters?: AssyFiltersType;
    flash?: Flash;
}) {
    const { flash: sharedFlash } = usePage<SharedPageProps>().props;
    const flash = (propFlash || sharedFlash || {}) as Flash;

    const [search, setSearch] = useState(filters?.search || "");
    const [carlineId, setCarlineId] = useState(normalizeIdFilter(filters?.carline_id));
    const [isActive, setIsActive] = useState(normalizeStatusFilter(filters?.is_active));
    const [perPage, setPerPage] = useState(String(filters?.per_page || assy?.per_page || 20));
    const [filterOpen, setFilterOpen] = useState(
        Boolean(filters?.carline_id || normalizeStatusFilter(filters?.is_active) !== "")
    );
    const [showAlert, setShowAlert] = useState(false);
    const [alertType, setAlertType] = useState<"success" | "warning" | "error">("success");
    const [alertMessage, setAlertMessage] = useState("");
    const [deleteTarget, setDeleteTarget] = useState<Assy | null>(null);
    const [isProcessing, setIsProcessing] = useState(false);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editTarget, setEditTarget] = useState<Assy | null>(null);

    const emptyAssyForm = {
        carline_id: "", assy_number: "", assy_code: "", level: "",
        pattern: "", umh: "", standard_sea_quantity: "", standard_air_quantity: "",
        max_quantity_sea: "", max_quantity_air: "", is_active: true,
    };

    const {
        data: addData, setData: setAddData, post: postAssy,
        processing: addProcessing, errors: addErrors, reset: resetAdd,
    } = useForm({ ...emptyAssyForm });

    const {
        data: editData, setData: setEditData, put: putAssy,
        processing: editProcessing, errors: editErrors, reset: resetEdit,
    } = useForm({ ...emptyAssyForm });

    const openAddModal = () => { resetAdd(); setShowAddModal(true); };

    const openEditModal = (row: Assy) => {
        setEditData({
            carline_id: row.carline_id?.toString() || "",
            assy_number: row.assy_number || "",
            assy_code: row.assy_code || "",
            level: row.level || "",
            pattern: row.pattern || "",
            umh: row.umh?.toString() || "",
            standard_sea_quantity: row.standard_sea_quantity?.toString() || "",
            standard_air_quantity: row.standard_air_quantity?.toString() || "",
            max_quantity_sea: row.max_quantity_sea?.toString() || "",
            max_quantity_air: row.max_quantity_air?.toString() || "",
            is_active: row.is_active === true || (row.is_active as unknown) === 1 || String(row.is_active) === "1" || String(row.is_active) === "true",
        });
        setEditTarget(row);
    };

    const handleAddAssy = (e: SyntheticEvent) => {
        e.preventDefault();
        postAssy(window.route("assy.store"), {
            onSuccess: () => { setShowAddModal(false); resetAdd(); },
        });
    };

    const handleEditAssy = (e: SyntheticEvent) => {
        e.preventDefault();
        putAssy(window.route("assy.update", editTarget!.id), {
            onSuccess: () => { setEditTarget(null); resetEdit(); },
        });
    };

    const assyRows = assy?.data ?? [];
    const hasActiveFilters = Boolean(search || carlineId || isActive !== "");

    const currentPage = assy?.current_page ?? 1;
    const lastPage = assy?.last_page ?? 1;
    const visiblePages = useMemo(
        () => getVisiblePages(currentPage, lastPage),
        [currentPage, lastPage]
    );

    useEffect(() => {
        setSearch(filters?.search || "");
        setCarlineId(normalizeIdFilter(filters?.carline_id));
        setIsActive(normalizeStatusFilter(filters?.is_active));
        setPerPage(String(filters?.per_page || assy?.per_page || 20));
    }, [filters?.search, filters?.carline_id, filters?.is_active, filters?.per_page, assy?.per_page]);

    useEffect(() => {
        const successMessage = flash?.success || flash?.message;
        const warningMessage = flash?.warning;
        const errorMessage = flash?.error;
        const message = successMessage || warningMessage || errorMessage;

        if (!message) return;

        setAlertType(successMessage ? "success" : warningMessage ? "warning" : "error");
        setAlertMessage(message);
        setShowAlert(true);

        const timer = setTimeout(() => setShowAlert(false), 4000);
        return () => clearTimeout(timer);
    }, [flash?.success, flash?.message, flash?.warning, flash?.error]);

    const getQuery = (page?: number) => ({
        search: search.trim() || undefined,
        carline_id: carlineId || undefined,
        is_active: isActive === "" ? undefined : isActive,
        per_page: perPage || undefined,
        page: page || undefined,
    });

    const applyFilters = (event?: SyntheticEvent) => {
        event?.preventDefault();

        router.get(window.route("assy.index"), getQuery(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearch("");
        setCarlineId("");
        setIsActive("");

        router.get(window.route("assy.index"), { per_page: perPage || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const changePerPage = (value: string) => {
        setPerPage(value);

        router.get(window.route("assy.index"), {
            search: search.trim() || undefined,
            carline_id: carlineId || undefined,
            is_active: isActive === "" ? undefined : isActive,
            per_page: value,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const visitPage = (page: number) => {
        if (!page || page < 1 || page > lastPage || page === currentPage) return;

        router.get(window.route("assy.index"), getQuery(page), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleToggleStatus = (assyItem: Assy) => {
        setIsProcessing(true);

        router.patch(window.route("assy.toggle-status", assyItem.id), {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;

        setIsProcessing(true);
        router.delete(window.route("assy.destroy", deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onError: () => {
                setAlertType("error");
                setAlertMessage("Failed to delete assy. Please try again.");
                setShowAlert(true);
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    const handleSyncSirep = () => {
        setIsProcessing(true);

        router.post(window.route("assy.sync-sirep"), {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    const firstRecord = assy?.from ?? (assyRows.length > 0 ? (currentPage - 1) * (assy?.per_page ?? assyRows.length) + 1 : 0);
    const lastRecord = assy?.to ?? Math.min(currentPage * (assy?.per_page ?? assyRows.length), assy?.total ?? assyRows.length);

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Assy" }]} />

                {showAlert && alertMessage && (
                    <div className="mb-5 animate-slideDown">
                        <div
                            className={`flex items-start gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-200 border-l-4 ${alertType === "success"
                                ? "border-l-[#1D6F42]"
                                : alertType === "warning"
                                    ? "border-l-amber-500"
                                    : "border-l-red-500"
                                }`}
                        >
                            <div
                                className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${alertType === "success"
                                    ? "bg-green-50 text-[#1D6F42]"
                                    : alertType === "warning"
                                        ? "bg-amber-50 text-amber-600"
                                        : "bg-red-50 text-red-500"
                                    }`}
                            >
                                {alertType === "success" ? (
                                    <CheckCircleIcon className="w-5 h-5" />
                                ) : (
                                    <ExclamationTriangleIcon className="w-5 h-5" />
                                )}
                            </div>
                            <p className="flex-1 text-sm font-medium text-gray-800 whitespace-pre-line">
                                {alertMessage}
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowAlert(false)}
                                className="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                aria-label="Close alert"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {isProcessing && !deleteTarget && (
                    <div className="fixed inset-0 z-50 bg-black/20 flex items-center justify-center">
                        <div className="bg-white rounded-xl px-6 py-5 shadow-xl flex items-center gap-3">
                            <div className="w-5 h-5 border-2 border-[#1D6F42] border-t-transparent rounded-full animate-spin" />
                            <span className="text-sm font-medium text-gray-700">Processing...</span>
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 border-b border-gray-100">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                                    Assy Master
                                </h1>
                                <p className="text-sm text-gray-500 mt-1">
                                    Manage assy numbers, assy codes, car lines, UMH, and master assy statuses.
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <button
                                    onClick={openAddModal}
                                    className="inline-flex items-center justify-center gap-2 h-9 px-4 bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] transition-all shadow-sm active:scale-[0.98]"
                                >
                                    <PlusIcon className="w-4 h-4" />
                                    Add Assy
                                </button>
                            </div>
                        </div>
                    </div>

                    <AssyFilters
                        search={search}
                        setSearch={setSearch}
                        carlineId={carlineId}
                        setCarlineId={setCarlineId}
                        isActive={isActive}
                        setIsActive={setIsActive}
                        carlines={carlines}
                        filterOpen={filterOpen}
                        setFilterOpen={setFilterOpen}
                        hasActiveFilters={hasActiveFilters}
                        isProcessing={isProcessing}
                        onApply={applyFilters}
                        onReset={clearFilters}
                        onSyncSirep={handleSyncSirep}
                        perPage={perPage}
                    />

                    <AssyTable
                        assyRows={assyRows}
                        currentPage={currentPage}
                        perPage={Number(perPage)}
                        isProcessing={isProcessing}
                        hasActiveFilters={hasActiveFilters}
                        onToggleStatus={handleToggleStatus}
                        onEdit={openEditModal}
                        onDelete={(assyItem) => setDeleteTarget(assyItem)}
                        onResetFilters={clearFilters}
                    />

                    {assyRows.length > 0 && (
                        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-500">
                                <span>
                                    Showing {firstRecord} - {lastRecord} of {assy?.total ?? assyRows.length} records
                                </span>
                                <span className="hidden sm:inline text-gray-300">|</span>
                                <div className="flex items-center gap-1.5">
                                    <span>Show</span>
                                    <select
                                        value={perPage}
                                        onChange={(event) => changePerPage(event.target.value)}
                                        className="h-8 rounded-lg border border-gray-200 bg-white px-2 pr-7 text-xs font-semibold text-gray-700 focus:border-[#1D6F42] focus:outline-none focus:ring-1 focus:ring-[#1D6F42]/20"
                                        aria-label="Rows per page"
                                    >
                                        {PER_PAGE_OPTIONS.map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </select>
                                    <span>data</span>
                                </div>
                            </div>

                            {lastPage > 1 && (
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => visitPage(currentPage - 1)}
                                        disabled={currentPage === 1}
                                        className={`p-2 rounded-lg border text-sm transition-colors ${currentPage === 1
                                            ? "opacity-40 cursor-not-allowed border-gray-200 text-gray-400"
                                            : "border-gray-300 text-gray-700 hover:bg-gray-100"
                                            }`}
                                        aria-label="Previous page"
                                    >
                                        <ChevronLeftIcon className="w-4 h-4" />
                                    </button>

                                    {visiblePages.map((page) => (
                                        <button
                                            key={page}
                                            type="button"
                                            onClick={() => visitPage(page)}
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
                                        onClick={() => visitPage(currentPage + 1)}
                                        disabled={currentPage === lastPage}
                                        className={`p-2 rounded-lg border text-sm transition-colors ${currentPage === lastPage
                                            ? "opacity-40 cursor-not-allowed border-gray-200 text-gray-400"
                                            : "border-gray-300 text-gray-700 hover:bg-gray-100"
                                            }`}
                                        aria-label="Next page"
                                    >
                                        <ChevronNextIcon className="w-4 h-4" />
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
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-slideDown {
                    animation: slideDown 0.25s ease-out;
                }
            `}</style>

            <AssyFormModal
                isOpen={showAddModal}
                onClose={() => { setShowAddModal(false); resetAdd(); }}
                title="Add Assy Master"
                carlines={carlines}
                submitLabel={addProcessing ? "Saving..." : "Save Assy"}
                data={addData}
                setData={setAddData}
                errors={addErrors}
                onSubmit={handleAddAssy}
                processing={addProcessing}
                isEdit={false}
            />

            <AssyFormModal
                isOpen={!!editTarget}
                onClose={() => { setEditTarget(null); resetEdit(); }}
                title="Edit Assy Master"
                carlines={carlines}
                submitLabel={editProcessing ? "Updating..." : "Update Assy"}
                data={editData}
                setData={setEditData}
                errors={editErrors}
                onSubmit={handleEditAssy}
                processing={editProcessing}
                isEdit={true}
            />

            <DeleteConfirmModal
                isOpen={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                assyNumber={deleteTarget?.assy_number}
                isProcessing={isProcessing}
            />
        </AdminLayout>
    );
}
