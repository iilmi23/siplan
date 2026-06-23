import AdminLayout from "@/Layouts/AdminLayout";
import { Link, router } from "@inertiajs/react";
import { type ReactNode, type SyntheticEvent, useMemo, useState } from "react";
import {
    ArrowPathIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ChevronRightIcon,
    ClockIcon,
    DocumentTextIcon,
    EyeIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    QueueListIcon,
    TableCellsIcon,
    XCircleIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

type HistoryItem = Record<string, any>;
type Customer = { code?: string; name?: string };
type Option = { value: string; label: string };
type Paginated<T> = {
    data: T[];
    current_page?: number;
    last_page?: number;
    prev_page_url?: string;
    next_page_url?: string;
    from?: number;
    to?: number;
    total?: number;
};
type HistoryFilters = {
    type?: string;
    status?: string;
    customer?: string;
    search?: string;
    date_start?: string;
    date_end?: string;
};

export default function History({
    historyItems,
    customers = [],
    filters = {},
    typeOptions = [],
    statusOptions = [],
}: {
    historyItems: Paginated<HistoryItem>;
    customers?: Customer[];
    filters?: HistoryFilters;
    typeOptions?: Option[];
    statusOptions?: Option[];
}) {
    const rows = historyItems?.data ?? [];
    const [type, setType] = useState(filters.type || "");
    const [status, setStatus] = useState(filters.status || "");
    const [customer, setCustomer] = useState(filters.customer || "");
    const [search, setSearch] = useState(filters.search || "");
    const [dateStart, setDateStart] = useState(filters.date_start || "");
    const [dateEnd, setDateEnd] = useState(filters.date_end || "");
    const [selected, setSelected] = useState<HistoryItem | null>(null);
    const [showAdvanced, setShowAdvanced] = useState(false);

    const query = useMemo(() => buildQuery({
        type,
        status,
        customer,
        search,
        date_start: dateStart,
        date_end: dateEnd,
    }), [type, status, customer, search, dateStart, dateEnd]);
    const hasFilters = Boolean(type || status || customer || search || dateStart || dateEnd);

    const applyFilter = (event?: SyntheticEvent) => {
        event?.preventDefault();
        router.get(route("history"), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilter = () => {
        setType("");
        setStatus("");
        setCustomer("");
        setSearch("");
        setDateStart("");
        setDateEnd("");
        router.get(route("history"), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const goToPage = (url?: string) => {
        if (!url) return;
        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AdminLayout title="History">
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-2 mb-4 text-sm" aria-label="Breadcrumb">
                    <span className="text-gray-600">Shipping Release</span>
                    <ChevronRightIcon className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-900 font-medium">History Log</span>
                </nav>

                {/* Main Card Wrapper */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    
                    {/* Header Section inside Card */}
                    <div className="p-6 pb-4 border-b border-gray-100">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">History Log</h1>
                                <p className="mt-1 text-sm text-gray-500">
                                    Monitor system activities (upload, edit, and delete summary/SPP) in a unified timeline.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Sleek Filters & Search Panel */}
                    <div className="p-6 border-b border-gray-100">
                        <form onSubmit={applyFilter} className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div className="flex-1 grid gap-4 md:grid-cols-3">
                                {/* Search Input */}
                                <div>
                                    <label htmlFor="history-search" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Search Activity
                                    </label>
                                    <div className="relative">
                                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                        <input
                                            id="history-search"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search file, batch, user..."
                                            className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-10 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                        />
                                        {search && (
                                            <button type="button" onClick={() => setSearch("")} className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600" aria-label="Clear search">
                                                <XMarkIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Customer Selector */}
                                <div>
                                    <label htmlFor="history-customer" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Customer
                                    </label>
                                    <select 
                                        id="history-customer"
                                        value={customer} 
                                        onChange={(event) => setCustomer(event.target.value)} 
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    >
                                        <option value="">All Customers</option>
                                        {customers.map((c) => (
                                            <option key={c.code ?? c.name} value={c.code ?? c.name}>
                                                {c.code ?? c.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Type Selector */}
                                <div>
                                    <label htmlFor="history-type" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Tipe Aktivitas
                                    </label>
                                    <select 
                                        id="history-type"
                                        value={type} 
                                        onChange={(event) => setType(event.target.value)} 
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    >
                                        <option value="">All Types</option>
                                        {typeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                    </select>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-wrap gap-3">
                                <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#185c38] transition-colors">
                                    <MagnifyingGlassIcon className="h-4 w-4" />
                                    Search
                                </button>

                                <button 
                                    type="button" 
                                    onClick={() => setShowAdvanced(!showAdvanced)} 
                                    className={`inline-flex h-11 items-center justify-center gap-2 rounded-xl border px-5 text-sm font-semibold transition-all ${
                                        showAdvanced 
                                            ? "border-[#1D6F42]/30 bg-green-50/50 text-[#1D6F42]" 
                                            : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                    }`}
                                >
                                    <FunnelIcon className="h-4 w-4" />
                                    Advanced Filter
                                </button>

                                {hasFilters && (
                                    <button type="button" onClick={resetFilter} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                        <ArrowPathIcon className="h-4 w-4" />
                                        Reset
                                    </button>
                                )}
                            </div>
                        </form>

                        {/* Advanced Filters Panel */}
                        {showAdvanced && (
                            <div className="mt-4 grid grid-cols-1 gap-4 border-t border-dashed border-gray-200 pt-4 sm:grid-cols-3">
                                <div>
                                    <label htmlFor="history-status" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Status
                                    </label>
                                    <select 
                                        id="history-status"
                                        value={status} 
                                        onChange={(event) => setStatus(event.target.value)} 
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    >
                                        <option value="">All Statuses</option>
                                        {statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                    </select>
                                </div>

                                <div>
                                    <label htmlFor="history-date-start" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Start Date
                                    </label>
                                    <input 
                                        id="history-date-start"
                                        type="date" 
                                        value={dateStart} 
                                        onChange={(event) => setDateStart(event.target.value)} 
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-950 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20" 
                                    />
                                </div>

                                <div>
                                    <label htmlFor="history-date-end" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                        End Date
                                    </label>
                                    <input 
                                        id="history-date-end"
                                        type="date" 
                                        value={dateEnd} 
                                        onChange={(event) => setDateEnd(event.target.value)} 
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-950 transition-all focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20" 
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Timeline Data Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[950px] table-fixed">
                            <colgroup><col className="w-[150px]" /><col className="w-[220px]" /><col className="w-auto" /><col className="w-[180px]" /><col className="w-[120px]" /></colgroup>
                            <thead>
                                <tr className="bg-gray-100/80 border-b border-gray-200 dark:border-slate-700 dark:bg-[#172033]">
                                    <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:border-slate-700 dark:text-slate-100">Time</th>
                                    <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:border-slate-700 dark:text-slate-100">Activity</th>
                                    <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:border-slate-700 dark:text-slate-100">Source</th>
                                    <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:border-slate-700 dark:text-slate-100">Records</th>
                                    <th className="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-slate-100">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white dark:divide-slate-700/70 dark:bg-[#111827]">
                                {rows.length > 0 ? rows.map((item) => {
                                    const type = String(item.type || "").toLowerCase();
                                    const isEdit = type.includes("updated") || type.includes("edit");
                                    const isDeleted = type.includes("deleted") || type.includes("destroy");
                                    const isTransaction = type === "sr_upload" || type === "spp_generated" || type.startsWith("summary");

                                    return (
                                        <tr key={`${item.type}-${item.id}`} className="transition-colors hover:bg-gray-50/50 dark:hover:bg-slate-800/80">
                                            {/* Column 1: Time */}
                                            <td className="border-r border-gray-100 px-6 py-4 whitespace-nowrap dark:border-slate-700">
                                                <span className="block text-sm font-bold text-gray-900 dark:text-slate-100">{formatRelativeTime(item.occurred_at)}</span>
                                                <span className="text-xs text-gray-400 font-medium dark:text-slate-500">{formatDate(item.occurred_at)} • {formatTime(item.occurred_at)}</span>
                                            </td>

                                            {/* Column 2: Activity */}
                                            <td className="border-r border-gray-100 px-6 py-4 whitespace-nowrap dark:border-slate-700">
                                                <ActivityBadge item={item} />
                                                <span className="mt-1 block text-xs text-gray-500 font-medium dark:text-slate-400">by <span className="font-semibold text-gray-700 dark:text-slate-350">{item.actor || "System"}</span></span>
                                            </td>

                                            {/* Column 3: Source */}
                                            <td className="border-r border-gray-100 px-6 py-4 dark:border-slate-700">
                                                {isTransaction ? (
                                                    <>
                                                        <div className="flex items-center gap-1.5">
                                                            {item.customer && item.customer !== "-" && (
                                                                <span className="inline-flex items-center rounded-md bg-green-50/80 px-2 py-0.5 text-xs font-semibold text-[#1D6F42] border border-green-100 dark:bg-emerald-400/10 dark:text-emerald-300 dark:border-emerald-400/20">
                                                                    {item.customer}
                                                                </span>
                                                            )}
                                                            {item.port && item.port !== "-" && (
                                                                <span className="text-xs font-medium text-gray-400 dark:text-slate-500">
                                                                    ({item.port})
                                                                </span>
                                                            )}
                                                        </div>
                                                        {item.source_file && item.source_file !== "-" && (
                                                            <p className="mt-1 truncate max-w-[320px] text-sm font-medium text-gray-700 dark:text-slate-300" title={item.source_file}>
                                                                {item.source_file}
                                                            </p>
                                                        )}
                                                    </>
                                                ) : (
                                                    <>
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:border-slate-600">
                                                                {getEntityType(item.type)}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 truncate max-w-[320px] text-sm font-bold text-gray-900 dark:text-slate-100">
                                                            {getIdentifierFromNotes(item.notes)}
                                                        </p>
                                                    </>
                                                )}
                                            </td>

                                            {/* Column 4: Records */}
                                            <td className="border-r border-gray-100 px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-semibold dark:border-slate-700 dark:text-slate-300">
                                                {isTransaction ? (
                                                    isEdit ? (
                                                        <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 border border-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">
                                                            {item.record_count} row{item.record_count > 1 ? 's' : ''} updated
                                                        </span>
                                                    ) : isDeleted ? (
                                                        <span className="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 border border-red-100 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20">
                                                            {item.total_qty > 0 ? `${formatNumber(item.total_qty)} qty` : `${item.record_count} records`} deleted
                                                        </span>
                                                    ) : (
                                                        <div className="space-y-0.5">
                                                            <span className="block text-xs text-gray-550 font-bold dark:text-slate-350">{formatNumber(item.record_count)} items</span>
                                                            <span className="block text-xs text-gray-400 font-medium dark:text-slate-500">{formatNumber(item.total_qty)} Qty</span>
                                                        </div>
                                                    )
                                                ) : (
                                                    (type.includes("created") || type.includes("store") || type.includes("add")) ? (
                                                        <span className="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 border border-green-100 dark:bg-emerald-500/10 dark:text-emerald-350 dark:border-emerald-500/20">
                                                            New Record
                                                        </span>
                                                    ) : (type.includes("deleted") || type.includes("destroy") || type.includes("remove")) ? (
                                                        <span className="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 border border-red-100 dark:bg-red-500/10 dark:text-red-305 dark:border-red-500/20">
                                                            Deleted
                                                        </span>
                                                    ) : (
                                                        (function() {
                                                            const changeCount = item.details?.changes ? Object.keys(item.details.changes).length : 0;
                                                            return (
                                                                <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 border border-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">
                                                                    {changeCount} field{changeCount !== 1 ? 's' : ''} changed
                                                                </span>
                                                            );
                                                        })()
                                                    )
                                                )}
                                            </td>

                                            {/* Column 5: Action */}
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setSelected(item)}
                                                    className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 transition-colors hover:border-[#1D6F42]/30 hover:bg-gray-50 hover:text-[#1D6F42] dark:border-slate-650 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-[#1D6F42]"
                                                    title="View detail"
                                                    aria-label="View detail"
                                                >
                                                    <EyeIcon className="h-4 w-4" />
                                                    Detail
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={5} className="py-16 text-center">
                                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-[#172033]">
                                                <TableCellsIcon className="h-8 w-8 text-gray-400" />
                                            </div>
                                            <h2 className="mt-4 text-base font-semibold text-gray-800 dark:text-slate-100 font-sans">No history found</h2>
                                            <p className="mt-1 text-sm text-gray-500 dark:text-slate-400">Try changing the date range or active filters.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {rows.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-3.5 dark:border-slate-750 dark:bg-[#172033]">
                            <p className="text-sm text-gray-500 dark:text-slate-400">
                                Showing {formatNumber(historyItems?.from || 1)} - {formatNumber(historyItems?.to || rows.length)} of {formatNumber(historyItems?.total || rows.length)} entries
                            </p>
                            {Number(historyItems?.last_page ?? 1) > 1 && (
                                <div className="flex items-center gap-2 text-sm">
                                    <button 
                                        type="button" 
                                        disabled={!historyItems.prev_page_url} 
                                        onClick={() => goToPage(historyItems.prev_page_url)} 
                                        className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-gray-50 dark:border-slate-650 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    >
                                        Previous
                                    </button>
                                    <span className="font-semibold text-gray-600 dark:text-slate-350">{historyItems.current_page} / {historyItems.last_page}</span>
                                    <button 
                                        type="button" 
                                        disabled={!historyItems.next_page_url} 
                                        onClick={() => goToPage(historyItems.next_page_url)} 
                                        className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-gray-50 dark:border-slate-650 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    >
                                        Next
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {selected && <DetailDrawer item={selected} onClose={() => setSelected(null)} />}
        </AdminLayout>
    );
}

function ActivityBadge({ item }: { item: HistoryItem }) {
    let Icon = DocumentTextIcon;
    let classes = "border-emerald-100 bg-emerald-50 text-emerald-700";

    const type = String(item.type || "").toLowerCase();

    if (type === "sirep_sync") {
        Icon = ArrowPathIcon;
        classes = "border-sky-100 bg-sky-50 text-sky-700";
    } else if (type === "spp_generated") {
        Icon = QueueListIcon;
        classes = "border-indigo-100 bg-indigo-50 text-indigo-700";
    } else if (type.includes("updated") || type.includes("edit") || type.includes("modify")) {
        Icon = ArrowPathIcon;
        classes = "border-amber-100 bg-amber-50 text-amber-700";
    } else if (type.includes("deleted") || type.includes("destroy") || type.includes("remove")) {
        Icon = XMarkIcon;
        classes = "border-red-100 bg-red-50 text-red-700";
    } else if (type.includes("created") || type.includes("store") || type.includes("add")) {
        Icon = PlusIcon;
        classes = "border-emerald-100 bg-emerald-50 text-emerald-700";
    }

    const status = String(item.status || "").toLowerCase();
    const isFailed = status.includes("fail") || status.includes("error");
    const isProcessing = status.includes("process") && !status.includes("processed");

    return (
        <div className="flex items-center gap-2">
            <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${classes}`}>
                <Icon className="h-3.5 w-3.5" />
                {item.title}
            </span>
            {isFailed && (
                <span className="flex h-2.5 w-2.5 rounded-full bg-red-500 animate-ping" title="Failed" />
            )}
            {isProcessing && (
                <span className="flex h-2.5 w-2.5 rounded-full bg-amber-500 animate-ping" title="Processing" />
            )}
        </div>
    );
}

function DetailDrawer({ item, onClose }: { item: HistoryItem; onClose: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/40 backdrop-blur-xs">
            <div className="h-full w-full max-w-xl overflow-y-auto bg-white shadow-2xl transition-all">
                <div className="sticky top-0 z-10 border-b border-gray-150 bg-white px-6 py-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <ActivityBadge item={item} />
                            <h2 className="mt-3 text-xl font-extrabold text-gray-900">{item.title}</h2>
                            <p className="mt-1 text-xs text-gray-400 font-semibold">{formatDate(item.occurred_at)} at {formatTime(item.occurred_at)}</p>
                        </div>
                        <button type="button" onClick={onClose} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Close detail">
                            <XMarkIcon className="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div className="space-y-6 px-6 py-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {item.customer && item.customer !== "-" && (
                            <Info label="Customer" value={item.customer} />
                        )}
                        {item.port && item.port !== "-" && (
                            <Info label="Port / Destination" value={item.port} />
                        )}
                        <Info label="Actor" value={item.actor || "-"} />
                        <Info label="Status" value={item.status || "-"} />
                        {item.record_count > 0 && (
                            <Info label="Records Affected" value={formatNumber(item.record_count)} />
                        )}
                        {item.total_qty > 0 && (
                            <Info label="Total Qty" value={formatNumber(item.total_qty)} />
                        )}
                        {item.type === "sr_upload" && (
                            <>
                                <Info label="Mapped" value={formatNumber(item.mapped_count)} />
                                <Info label="Unmapped" value={formatNumber(item.unmapped_count)} />
                            </>
                        )}
                        {item.type === "spp_generated" && (
                            <Info label="Source Batches" value={formatNumber(item.source_batch_count || item.source_batches?.length)} />
                        )}
                    </div>

                    {item.source_file && item.source_file !== "-" && (
                        <div className="rounded-xl border border-gray-150 bg-gray-50 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Source File & Batch Information</p>
                            <p className="mt-2 break-words text-sm font-bold text-gray-900">{item.source_file}</p>
                            {item.batch_uuid && item.batch_uuid !== "-" && (
                                <p className="mt-1 text-xs text-gray-500 font-medium">Batch: <span className="font-mono text-gray-700">{item.batch_uuid}</span></p>
                            )}
                            {item.sheet_name && item.sheet_name !== "-" && (
                                <p className="mt-1 text-xs text-gray-500 font-medium">Sheet Name: {item.sheet_name}</p>
                            )}
                        </div>
                    )}

                    {item.notes && (
                        <div className="rounded-xl border border-amber-100 bg-amber-50/50 px-4 py-3 text-sm font-medium text-amber-800">
                            {item.notes}
                        </div>
                    )}

                    {item.details && item.details.changes && (
                        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
                            <p className="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Changes Detail (Dirty State)</p>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm border-collapse">
                                    <thead>
                                        <tr className="border-b border-gray-100 text-xs font-bold text-gray-400">
                                            <th className="py-2 pr-4">Field</th>
                                            <th className="py-2 px-4">Old Value</th>
                                            <th className="py-2 pl-4">New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {Object.entries(item.details.changes).map(([field, value]: [string, any]) => {
                                            const hasOldNew = value && typeof value === 'object' && 'old' in value && 'new' in value;
                                            return (
                                                <tr key={field} className="text-gray-700">
                                                    <td className="py-2 pr-4 font-bold text-gray-900 capitalize">{field.replace('_', ' ')}</td>
                                                    {hasOldNew ? (
                                                        <>
                                                            <td className="py-2 px-4 font-mono text-xs text-red-600 bg-red-50/30 rounded">{String(value.old ?? '-')}</td>
                                                            <td className="py-2 pl-4 font-mono text-xs text-green-700 bg-green-50/30 rounded font-semibold">{String(value.new ?? '-')}</td>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <td className="py-2 px-4 text-xs text-gray-400 font-medium">- (Bulk)</td>
                                                            <td className="py-2 pl-4 font-mono text-xs text-green-700 bg-green-50/30 rounded font-semibold">{String(value ?? '-')}</td>
                                                        </>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {item.type === "sr_upload" && item.related_id && (
                        <Link href={route("summary.show", item.related_id)} className="inline-flex w-full h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] text-sm font-semibold text-white hover:bg-[#185c38] shadow-sm transition-all">
                            <EyeIcon className="h-4 w-4" />
                            Open Summary Detail
                        </Link>
                    )}

                    {item.type === "sirep_sync" && item.details && (
                        <div className="rounded-xl border border-sky-100 bg-sky-50/40 p-4">
                            <p className="mb-3 text-xs font-bold uppercase tracking-wider text-sky-600">
                                SIREP Sync Statistics
                            </p>
                            <div className="space-y-3">
                                {(["assy", "carline"] as const).map((scope) => {
                                    const stat = item.details[scope];
                                    if (!stat) return null;
                                    const label = scope === "assy" ? "Assy" : "Carline";
                                    return (
                                        <SirepScopeCard key={scope} label={label} stat={stat} />
                                    );
                                })}
                            </div>
                        </div>
                    )}


                    {item.type === "spp_generated" && (
                        <div>
                            <div className="mb-3 flex items-center gap-2 text-sm font-bold text-gray-800">
                                <CalendarDaysIcon className="h-5 w-5 text-gray-400" />
                                Source Batches
                            </div>
                            <div className="space-y-2">
                                {(item.source_batches || []).length > 0 ? item.source_batches.map((source) => (
                                    <div key={source.id} className="rounded-xl border border-gray-200 bg-white p-4 hover:border-gray-300 transition-all">
                                        <p className="truncate text-sm font-bold text-gray-900" title={source.source_file || ""}>{source.source_file || "-"}</p>
                                        <div className="mt-2 grid gap-2 text-xs text-gray-500 font-medium sm:grid-cols-3">
                                            <span>Batch: <span className="font-mono text-gray-700">{source.batch_uuid || "-"}</span></span>
                                            <span>Records: <span className="font-semibold text-gray-700">{formatNumber(source.record_count)}</span></span>
                                            <span>Qty: <span className="font-semibold text-gray-700">{formatNumber(source.total_qty)}</span></span>
                                        </div>
                                        {source.sr_id && (
                                            <Link href={route("summary.show", source.sr_id)} className="mt-3 inline-flex text-xs font-semibold text-[#1D6F42] hover:underline">
                                                Open source summary detail
                                            </Link>
                                        )}
                                    </div>
                                )) : (
                                    <p className="rounded-xl border border-gray-250 bg-gray-50 px-4 py-3 text-sm text-gray-500">No source batch recorded.</p>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function Info({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-xl border border-gray-150 bg-white px-4 py-3 shadow-xs">
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">{label}</p>
            <p className="mt-1 truncate text-sm font-bold text-gray-900">{value}</p>
        </div>
    );
}

function SirepScopeCard({ label, stat }: { label: string; stat: any }) {
    const [showCreated, setShowCreated] = useState(false);
    const [showUpdated, setShowUpdated] = useState(false);

    const createdItems: string[] = stat.created_items ?? [];
    const updatedItems: string[] = stat.updated_items ?? [];
    const PREVIEW = 10;

    return (
        <div className="rounded-lg border border-sky-100 bg-white px-4 py-3">
            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">{label}</p>

            {/* Stats grid */}
            <div className="grid grid-cols-4 gap-2 text-center">
                {[
                    { label: "Total",   value: stat.total   ?? 0, color: "text-gray-700"   },
                    { label: "Created", value: stat.created ?? 0, color: "text-emerald-600" },
                    { label: "Updated", value: stat.updated ?? 0, color: "text-amber-600"  },
                    { label: "Skipped", value: stat.skipped ?? 0, color: "text-red-500"    },
                ].map((col) => (
                    <div key={col.label}>
                        <p className={`text-base font-extrabold ${col.color}`}>{formatNumber(col.value)}</p>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">{col.label}</p>
                    </div>
                ))}
            </div>

            {/* Created items */}
            {createdItems.length > 0 && (
                <div className="mt-3 border-t border-gray-100 pt-3">
                    <div className="flex items-center justify-between mb-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-600">
                            Newly Added ({createdItems.length})
                        </p>
                        {createdItems.length > PREVIEW && (
                            <button
                                type="button"
                                onClick={() => setShowCreated(!showCreated)}
                                className="text-[10px] font-semibold text-gray-400 hover:text-gray-600 underline"
                            >
                                {showCreated ? "Hide" : `+${createdItems.length - PREVIEW} more`}
                            </button>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {(showCreated ? createdItems : createdItems.slice(0, PREVIEW)).map((code, i) => (
                            <span
                                key={i}
                                className="inline-flex items-center rounded border border-emerald-100 bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                            >
                                {code}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Updated items */}
            {updatedItems.length > 0 && (
                <div className="mt-3 border-t border-gray-100 pt-3">
                    <div className="flex items-center justify-between mb-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-amber-600">
                            Updated ({updatedItems.length})
                        </p>
                        {updatedItems.length > PREVIEW && (
                            <button
                                type="button"
                                onClick={() => setShowUpdated(!showUpdated)}
                                className="text-[10px] font-semibold text-gray-400 hover:text-gray-600 underline"
                            >
                                {showUpdated ? "Hide" : `+${updatedItems.length - PREVIEW} more`}
                            </button>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {(showUpdated ? updatedItems : updatedItems.slice(0, PREVIEW)).map((code, i) => (
                            <span
                                key={i}
                                className="inline-flex items-center rounded border border-amber-100 bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700"
                            >
                                {code}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Errors */}
            {(stat.errors ?? []).length > 0 && (
                <div className="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2">
                    <p className="mb-1 text-[10px] font-bold uppercase tracking-wider text-red-500">
                        Errors ({stat.errors.length})
                    </p>
                    <ul className="space-y-0.5">
                        {stat.errors.slice(0, 5).map((err: string, i: number) => (
                            <li key={i} className="text-xs text-red-700 font-medium">{err}</li>
                        ))}
                        {stat.errors.length > 5 && (
                            <li className="text-xs text-red-400">...and {stat.errors.length - 5} other errors</li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

function buildQuery(filters: HistoryFilters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && String(value).trim() !== "")
    ) as Record<string, string>;
}

function toNumber(value: unknown) {
    const number = Number(value);
    return number && !Number.isNaN(number) ? number : 0;
}

function formatNumber(value: unknown) {
    return toNumber(value).toLocaleString("en-US");
}

function formatDate(value: unknown) {
    if (!value) return "-";
    const date = new Date(value as string | number | Date);
    if (Number.isNaN(date.getTime())) return "-";
    return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

function formatTime(value: unknown) {
    if (!value) return "-";
    const date = new Date(value as string | number | Date);
    if (Number.isNaN(date.getTime())) return "-";
    return date.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" });
}

function formatRelativeTime(value: unknown) {
    if (!value) return "";
    const date = new Date(value as string | number | Date);
    if (Number.isNaN(date.getTime())) return "";
    
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return "Just now";
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays === 1) return "Yesterday";
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short" });
}

function getEntityType(type: string): string {
    const lower = String(type || "").toLowerCase();
    if (lower === 'sirep_sync') return 'SIREP';
    if (lower === 'sr_upload') return 'Shipping Release';
    if (lower === 'spp_generated') return 'SPP';
    if (lower.startsWith('summary_')) return 'Summary';
    
    const parts = lower.split('_');
    if (parts.length > 1) {
        parts.pop(); // Remove action suffix (created/updated/deleted)
        return parts.map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(' ');
    }
    return 'Master Data';
}

function getIdentifierFromNotes(notes: string): string {
    if (!notes) return '-';
    const match = notes.match(/'([^']+)'/);
    return match ? match[1] : '-';
}
