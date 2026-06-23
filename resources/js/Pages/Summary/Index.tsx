import AdminLayout from "@/Layouts/AdminLayout";
import { Link, router } from "@inertiajs/react";
import { type ReactNode, type SyntheticEvent, useCallback, useEffect, useMemo, useState } from "react";
import Alert from "@/Components/Alert";
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ChevronRightIcon,
    EyeIcon,
    MagnifyingGlassIcon,
    TableCellsIcon,
    TrashIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

type SummaryFilters = {
    customer?: string;
    search?: string;
};

type Flash = {
    success?: string;
    warning?: string;
    error?: string;
};

type SummaryRow = Record<string, any>;

type Paginated<T> = {
    data: T[];
    [key: string]: any;
};

export default function SummaryIndex({
    srList = [],
    customers = [],
    filters = {},
    flash = {},
}: {
    srList?: SummaryRow[] | Paginated<SummaryRow>;
    customers?: Record<string, any>[];
    filters?: SummaryFilters;
    flash?: Flash;
}) {
    const [customer, setCustomer] = useState(filters?.customer || "");
    const [search, setSearch] = useState(filters?.search || "");
    const [notification, setNotification] = useState<{ show: boolean; type: "success" | "warning" | "error"; message: string }>({ show: false, type: "success", message: "" });
    const [deleteTarget, setDeleteTarget] = useState<SummaryRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const rows = useMemo(() => Array.isArray(srList) ? srList : (srList?.data || []), [srList]);
    const pagination = Array.isArray(srList) ? null : srList;
    const hasFilters = Boolean(customer || search);
    const query = useMemo(() => buildQuery({ customer, search }), [customer, search]);

    useEffect(() => {
        setCustomer(filters?.customer || "");
        setSearch(filters?.search || "");
    }, [filters?.customer, filters?.search]);

    useEffect(() => {
        const successMessage = flash?.success;
        const warningMessage = flash?.warning;
        const errorMessage = flash?.error;
        const message = successMessage || warningMessage || errorMessage;

        if (!message) return;

        const type = successMessage ? "success" : warningMessage ? "warning" : "error";
        setNotification({ show: true, type, message });

        const timer = setTimeout(() => {
            setNotification((current) => ({ ...current, show: false }));
        }, 5000);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.warning, flash?.error]);

    const applyFilter = (event?: SyntheticEvent) => {
        event?.preventDefault();

        router.get(route("summary.index"), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilter = () => {
        setCustomer("");
        setSearch("");

        router.get(route("summary.index"), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const goToPage = useCallback((url?: string) => {
        if (!url) return;

        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    const confirmDelete = () => {
        if (!deleteTarget) return;

        setIsDeleting(true);
        router.delete(route("summary.destroy", deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onError: () => {
                setNotification({
                    show: true,
                    type: "error",
                    message: "Failed to delete upload. Please try again.",
                });
            },
            onFinish: () => setIsDeleting(false),
        });
    };

    return (
        <AdminLayout title="Summary">
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <nav className="flex items-center gap-2 mb-4 text-sm" aria-label="Breadcrumb">
                    <span className="text-gray-600">Shipping Release</span>
                    <ChevronRightIcon className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-900 font-medium">Summary</span>
                </nav>

                {notification.show && notification.message && (
                    <Alert
                        type={notification.type}
                        message={notification.message}
                        onClose={() => setNotification((current) => ({ ...current, show: false }))}
                    />
                )}

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div className="p-6 pb-4 border-b border-gray-100">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                                    Summary Upload
                                </h1>
                                <p className="mt-1 text-sm text-gray-500">
                                    Review SR upload batches, export detail, or remove an uploaded batch.
                                </p>
                            </div>

                        </div>
                    </div>

                    <div className="px-6 py-4 border-b border-gray-100">
                        <form onSubmit={applyFilter} className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div className="grid flex-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label htmlFor="summary-search" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Source File
                                    </label>
                                    <div className="relative">
                                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                        <input
                                            id="summary-search"
                                            type="text"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search source file..."
                                            className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-10 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                        />
                                        {search && (
                                            <button
                                                type="button"
                                                onClick={() => setSearch("")}
                                                className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600"
                                                aria-label="Clear search"
                                            >
                                                <XMarkIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="summary-customer" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Customer
                                    </label>
                                    <select
                                        id="summary-customer"
                                        value={customer}
                                        onChange={(event) => setCustomer(event.target.value)}
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    >
                                        <option value="">All Customers</option>
                                        {customers.map((customerItem) => (
                                            <option key={customerItem.code ?? customerItem.name} value={customerItem.code ?? customerItem.name}>
                                                {customerItem.code ?? customerItem.name}
                                                {customerItem.name && customerItem.code ? ` - ${customerItem.name}` : ""}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#185c38]"
                                >
                                    <MagnifyingGlassIcon className="h-4 w-4" />
                                    Apply
                                </button>

                                {hasFilters && (
                                    <button
                                        type="button"
                                        onClick={resetFilter}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                                    >
                                        <ArrowPathIcon className="h-4 w-4" />
                                        Reset
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>

                    <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50 dark:scrollbar-thumb-slate-700 dark:scrollbar-track-[#111827]">
                        <table className="w-full table-fixed">
                            <colgroup><col className="w-10" /><col className="w-20" /><col className="w-20" /><col className="w-60" /><col className="w-32" /><col className="w-16" /><col className="w-44" /><col className="w-28" /><col className="w-32" /></colgroup>
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200 dark:border-slate-700 dark:bg-[#172033]">
                                    <TableHeader>#</TableHeader>
                                    <TableHeader>Customer</TableHeader>
                                    <TableHeader>Port</TableHeader>
                                    <TableHeader>Source File</TableHeader>
                                    <TableHeader>Sheet</TableHeader>
                                    <TableHeader align="right">Assy</TableHeader>
                                    <TableHeader>ETD Range</TableHeader>
                                    <TableHeader>Upload Date</TableHeader>
                                    <th className="sticky right-0 z-20 border-l border-gray-200 bg-gray-100/95 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)] dark:border-slate-700 dark:bg-[#172033] dark:text-slate-100 dark:shadow-[-8px_0_14px_-14px_rgba(0,0,0,0.9)]">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-slate-700/70">
                                {rows.length > 0 ? (
                                    rows.map((sr, index) => (
                                        <tr key={`${sr.upload_batch_id || ''}-${sr.id}`} className="group transition-colors hover:bg-gray-50/80 dark:hover:bg-slate-800/80">
                                            <td className="px-3 py-4 text-sm font-medium text-gray-500 tabular-nums border-r border-gray-100 dark:border-slate-700 dark:text-slate-400 whitespace-nowrap">
                                                {(index + 1).toString().padStart(2, "0")}
                                            </td>
                                            <td className="px-3 py-4 border-r border-gray-100 dark:border-slate-700 whitespace-nowrap">
                                                <span className="inline-flex max-w-full rounded-lg border border-green-100 bg-green-50 px-2.5 py-1 text-xs font-semibold text-[#1D6F42] dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300">
                                                    <span className="truncate">{sr.customer || "-"}</span>
                                                </span>
                                            </td>
                                            <td className="px-3 py-4 text-sm text-gray-600 border-r border-gray-100 dark:border-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                <span className="block truncate">{sr.port || "-"}</span>
                                            </td>
                                            <td className="px-3 py-4 border-r border-gray-100 dark:border-slate-700 max-w-[240px]">
                                                <p className="truncate text-sm font-semibold text-gray-900 dark:text-slate-100" title={sr.source_file || ""}>
                                                    {sr.source_file || "-"}
                                                </p>
                                                <p className="mt-0.5 truncate text-xs text-gray-400 dark:text-slate-500">
                                                    Batch: {typeof sr.upload_batch === 'object' && sr.upload_batch !== null ? sr.upload_batch.batch_uuid : (sr.upload_batch || "-")}
                                                </p>
                                            </td>
                                            <td className="px-3 py-4 text-sm text-gray-600 border-r border-gray-100 dark:border-slate-700 dark:text-slate-300 whitespace-nowrap truncate max-w-[150px]" title={sr.sheet_name || ""}>
                                                <span className="block truncate">{sr.sheet_name || "-"}</span>
                                            </td>
                                            <td className="px-3 py-4 text-right font-mono text-sm text-gray-700 border-r border-gray-100 dark:border-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {formatNumber(sr.unique_assy_numbers)}
                                            </td>
                                            <td className="px-3 py-4 text-sm text-gray-600 border-r border-gray-100 dark:border-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                <span className="block truncate">{formatDateRange(sr.earliest_etd, sr.latest_etd)}</span>
                                            </td>
                                            <td className="px-3 py-4 text-sm text-gray-600 border-r border-gray-100 dark:border-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                {formatDate(sr.upload_date)}
                                            </td>
                                            <td className="sticky right-0 z-10 border-l border-gray-100 bg-white px-3 py-4 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)] group-hover:bg-gray-50 dark:border-slate-700 dark:bg-[#111827] dark:shadow-[-8px_0_14px_-14px_rgba(0,0,0,0.9)] dark:group-hover:bg-slate-800 whitespace-nowrap">
                                                <div className="flex items-center gap-1.5">
                                                    <Link
                                                        href={route("summary.show", sr.id)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 transition-colors hover:border-[#1D6F42]/30 hover:bg-gray-50 hover:text-[#1D6F42] dark:border-indigo-400/30 dark:bg-indigo-400/10 dark:text-indigo-200 dark:hover:border-indigo-300/60 dark:hover:bg-indigo-400/20"
                                                        title="View"
                                                        aria-label="View summary"
                                                    >
                                                        <EyeIcon className="h-4 w-4" />
                                                    </Link>
                                                    <a
                                                        href={route("summary.export", sr.id)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 transition-colors hover:border-[#1D6F42]/30 hover:bg-gray-50 hover:text-[#1D6F42] dark:border-slate-600 dark:bg-[#111827] dark:text-slate-100 dark:hover:border-emerald-400/50 dark:hover:bg-slate-700 dark:hover:text-emerald-300"
                                                        title="Export"
                                                        aria-label="Export summary"
                                                    >
                                                        <ArrowDownTrayIcon className="h-4 w-4" />
                                                    </a>
                                                    <button
                                                        type="button"
                                                        onClick={() => setDeleteTarget(sr)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:border-red-400/40 dark:bg-red-400/10 dark:text-red-300 dark:hover:border-red-300/70 dark:hover:bg-red-400/20"
                                                        title="Delete"
                                                        aria-label="Delete summary"
                                                    >
                                                        <TrashIcon className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={9} className="py-16 text-center">
                                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                                                <TableCellsIcon className="h-8 w-8 text-gray-400" />
                                            </div>
                                            <h2 className="mt-4 text-base font-semibold text-gray-800">No SR summary found</h2>
                                            <p className="mt-1 text-sm text-gray-500">
                                                {hasFilters ? "No upload matches the current filters." : "Upload SR first to view summary."}
                                            </p>
                                            {hasFilters && (
                                                <button
                                                    type="button"
                                                    onClick={resetFilter}
                                                    className="mt-4 inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                                >
                                                    <ArrowPathIcon className="h-4 w-4" />
                                                    Reset filter
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {rows.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-6 py-3.5">
                            <p className="text-sm text-gray-500">
                                Showing {formatNumber(pagination?.from || 1)}-{formatNumber(pagination?.to || rows.length)} of {formatNumber(pagination?.total || rows.length)} upload batch{rows.length === 1 ? "" : "es"}
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                {pagination?.last_page > 1 && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <button
                                            type="button"
                                            disabled={!pagination.prev_page_url}
                                            onClick={() => goToPage(pagination.prev_page_url)}
                                            className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            Previous
                                        </button>
                                        <span className="font-semibold text-gray-600">
                                            {pagination.current_page} / {pagination.last_page}
                                        </span>
                                        <button
                                            type="button"
                                            disabled={!pagination.next_page_url}
                                            onClick={() => goToPage(pagination.next_page_url)}
                                            className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            Next
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {deleteTarget && (
                    <DeleteDialog
                        target={deleteTarget}
                        isDeleting={isDeleting}
                        onCancel={() => setDeleteTarget(null)}
                        onConfirm={confirmDelete}
                    />
                )}
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
        </AdminLayout>
    );
}


function TableHeader({ children, align = "left" }: { children: ReactNode; align?: "left" | "right" }) {
    const alignment = align === "right" ? "text-right" : "text-left";

    return (
        <th className={`border-r border-gray-200 px-5 py-4 text-xs font-bold uppercase tracking-wider text-gray-700 ${alignment} whitespace-nowrap`}>
            {children}
        </th>
    );
}

function DeleteDialog({ target, isDeleting, onCancel, onConfirm }: { target: SummaryRow; isDeleting: boolean; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="px-6 pt-6 pb-3">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Delete Upload</h3>
                            <p className="mt-1 text-sm text-gray-500 leading-relaxed">This will permanently delete all SR rows in this batch.</p>
                        </div>
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={isDeleting}
                            className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-gray-200"
                            aria-label="Close"
                        >
                            <XMarkIcon className="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div className="px-6 pt-2 pb-6">
                    <p className="text-sm text-gray-650 leading-relaxed">
                        Are you sure you want to delete source file <span className="font-semibold text-gray-900 break-all">"{target.source_file || "-"}"</span>?
                    </p>
                    <div className="mt-3 rounded-xl border border-gray-100 bg-gray-50/50 px-4 py-2.5 text-xs text-gray-500">
                        <span className="font-medium text-gray-400">Batch UUID:</span>{" "}
                        <span className="font-mono text-gray-700 break-all select-all">{typeof target.upload_batch === 'object' && target.upload_batch !== null ? target.upload_batch.batch_uuid : (target.upload_batch || "-")}</span>
                    </div>
                </div>

                <div className="flex justify-end gap-3 bg-gray-50 px-6 py-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={isDeleting}
                        className="h-10 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-gray-200"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={isDeleting}
                        className="h-10 rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    >
                        {isDeleting ? "Deleting..." : "Delete"}
                    </button>
                </div>
            </div>
        </div>
    );
}

function buildQuery(filters: SummaryFilters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && String(value).trim() !== "")
    ) as Record<string, string>;
}

function toNumber(value: unknown) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
}

function formatNumber(value: unknown) {
    return toNumber(value).toLocaleString("en-US");
}

function formatDate(value) {
    if (!value) return "-";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "-";

    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function formatDateRange(startDate, endDate) {
    if (!startDate && !endDate) return "-";
    if (startDate === endDate) return formatDate(startDate);

    return `${formatDate(startDate)} - ${formatDate(endDate)}`;
}
