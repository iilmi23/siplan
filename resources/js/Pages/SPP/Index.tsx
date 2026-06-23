import AdminLayout from "@/Layouts/AdminLayout";
import { Link, router, usePage } from "@inertiajs/react";
import { type ReactNode, type SyntheticEvent, useEffect, useMemo, useState } from "react";
import Alert from "@/Components/Alert";
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    ChevronRightIcon,
    EyeIcon,
    MagnifyingGlassIcon,
    SparklesIcon,
    XMarkIcon,
    ClockIcon,
    CubeIcon,
    DocumentTextIcon,
    CheckCircleIcon,
    TrashIcon,
    ExclamationTriangleIcon,
} from "@heroicons/react/24/outline";

type SrListItem = Record<string, any>;
type Customer = { code?: string; name?: string };
type SppFilters = { customer?: string; search?: string };
type SavedPlanItem = {
    id: number;
    batch_uuid: string;
    source_file: string;
    sheet_name: string;
    customer: string;
    port: string;
    unique_assy_numbers: number;
    total_qty: number;
    last_updated: string;
    upload_batch_id: number;
    period: string;
    period_label: string;
};

export default function SPPIndex({
    srList = [],
    savedPlans = [],
    customers = [],
    filters = {},
}: {
    srList?: SrListItem[];
    savedPlans?: SavedPlanItem[];
    customers?: Customer[];
    filters?: SppFilters;
}) {
    const { props } = usePage();
    const flash = (props as any).flash || {};
    const [customer, setCustomer] = useState(filters.customer || "");
    const [search, setSearch] = useState(filters.search || "");
    const [activeTab, setActiveTab] = useState<"workspace" | "saved">("workspace");
    const [notification, setNotification] = useState<{ show: boolean; type: "success" | "warning" | "error"; message: string }>({ show: false, type: "success", message: "" });
    const [lockTarget, setLockTarget] = useState<SrListItem | null>(null);
    const [isLocking, setIsLocking] = useState(false);
    const [unlockTarget, setUnlockTarget] = useState<SavedPlanItem | null>(null);
    const [isUnlocking, setIsUnlocking] = useState(false);
    const [exportingId, setExportingId] = useState<string | null>(null);

    const handleExport = (url: string, id: string) => {
        if (exportingId) return;
        setExportingId(id);
        fetch(url)
            .then((res) => {
                if (!res.ok) throw new Error("Export failed");
                const disposition = res.headers.get("Content-Disposition") || "";
                const match = disposition.match(/filename[^;=\n]*=(['"])?(.*?)\1/);
                const filename = match ? match[2] : "export.xlsx";
                return res.blob().then((blob) => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                const objUrl = URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = objUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(objUrl);
            })
            .catch(() => {
                setNotification({ show: true, type: "error", message: "Export failed. Please try again." });
            })
            .finally(() => {
                setExportingId(null);
            });
    };

    useEffect(() => {
        const successMessage = flash?.success;
        const errorMessage = flash?.error;
        const message = successMessage || errorMessage;

        if (!message) return;

        const type = successMessage ? "success" : "error";
        setNotification({ show: true, type, message });
    }, [flash?.success, flash?.error]);

    const confirmLock = () => {
        if (!lockTarget) return;

        setIsLocking(true);
        router.post(route("spp.storeDirect", lockTarget.id), {}, {
            preserveScroll: true,
            onSuccess: () => setLockTarget(null),
            onFinish: () => setIsLocking(false),
        });
    };

    const confirmUnlock = () => {
        if (!unlockTarget) return;

        setIsUnlocking(true);
        router.delete(route("spp.destroy", unlockTarget.id), {
            preserveScroll: true,
            onSuccess: () => setUnlockTarget(null),
            onFinish: () => setIsUnlocking(false),
        });
    };

    const rows = Array.isArray(srList) ? srList : [];
    const savedRows = Array.isArray(savedPlans) ? savedPlans : [];
    const hasFilters = Boolean(customer || search);
    const query = useMemo(() => buildQuery({ customer, search }), [customer, search]);

    const totalPendingQty = useMemo(() => rows.reduce((sum, row) => sum + toNumber(row.total_qty), 0), [rows]);
    const totalAssy = useMemo(() => rows.reduce((sum, row) => sum + toNumber(row.unique_assy_numbers), 0), [rows]);



    const applyFilter = (event?: SyntheticEvent) => {
        event?.preventDefault();
        router.get(route("spp"), query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const resetFilter = () => {
        setCustomer("");
        setSearch("");
        router.get(route("spp"), {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AdminLayout title="SPP – Six-Month Production Planning">
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">

                {/* ── Breadcrumb ── */}
                <nav className="flex items-center gap-2 mb-4 text-sm" aria-label="Breadcrumb">
                    <span className="text-gray-650">Shipping Release</span>
                    <ChevronRightIcon className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-900 font-medium">SPP</span>
                </nav>

                {notification.show && notification.message && (
                    <Alert
                        type={notification.type}
                        message={notification.message}
                        onClose={() => setNotification((current) => ({ ...current, show: false }))}
                    />
                )}

                {/* ── Stat Bar (hanya muncul jika ada data) ── */}
                {rows.length > 0 && activeTab === "workspace" && (
                    <div className="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <StatCard label="SR Available" value={rows.length} icon={<DocumentTextIcon className="h-5 w-5" />} color="slate" />
                        <StatCard label="Total Assy" value={formatNumber(totalAssy)} icon={<CubeIcon className="h-5 w-5" />} color="slate" />
                        <StatCard label="Total Qty" value={formatNumber(totalPendingQty)} icon={<CalendarDaysIcon className="h-5 w-5" />} color="green" />
                    </div>
                )}

                {/* ── Main Panel Wrapper ── */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
                    {/* Header Section inside Card */}
                    <div className="p-6 pb-4 border-b border-gray-100">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                                    Six-Month Production Planning
                                </h1>
                                <p className="mt-1 text-sm text-gray-500">
                                Select locked SR, then generate 6-month SPP.
                                </p>
                            </div>

                            {/* Workflow hint — compact, tidak memakan ruang */}
                            <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 shadow-sm text-xs font-semibold text-gray-600">
                                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#1D6F42] text-white text-[10px] font-extrabold">1</span>
                                <span className="text-gray-800 font-bold">Select SR</span>
                                <ChevronRightIcon className="h-3 w-3 text-gray-300" />
                                <span>Validation</span>
                                <ChevronRightIcon className="h-3 w-3 text-gray-300" />
                                <span>Matrix Results</span>
                            </div>
                        </div>
                    </div>

                    {/* Tab Bar + Search */}
                    <div className="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        {/* Tabs */}
                        <div className="flex gap-1 rounded-xl bg-gray-100 p-1 shrink-0">
                            <TabButton active={activeTab === "workspace"} onClick={() => setActiveTab("workspace")} count={rows.length}>
                                Workspace
                            </TabButton>
                            <TabButton active={activeTab === "saved"} onClick={() => setActiveTab("saved")} count={savedRows.length}>
                                Fixed Plans
                            </TabButton>
                        </div>

                        {/* Search + Customer Filter */}
                        <form onSubmit={applyFilter} className="flex flex-wrap items-center gap-3">
                            <div className="relative">
                                <MagnifyingGlassIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search SR file..."
                                    className="h-11 w-48 rounded-xl border border-gray-200 bg-white pl-10 pr-8 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#1D6F42] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 transition"
                                />
                                {search && (
                                    <button type="button" onClick={() => setSearch("")} className="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                                        <XMarkIcon className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                            <select
                                value={customer}
                                onChange={(e) => setCustomer(e.target.value)}
                                className="h-11 rounded-xl border border-gray-200 bg-white px-4 pr-10 text-sm text-gray-750 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 transition cursor-pointer"
                            >
                                <option value="">All Customers</option>
                                {customers.map((c) => (
                                    <option key={c.code ?? c.name} value={c.code ?? c.name}>{c.code ?? c.name}</option>
                                ))}
                            </select>
                            <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#185c38] transition-colors">
                                <MagnifyingGlassIcon className="h-4 w-4" />
                                Search
                            </button>
                            {hasFilters && (
                                <button type="button" onClick={resetFilter} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                    <XMarkIcon className="h-4 w-4" />
                                    Reset
                                </button>
                            )}
                        </form>
                    </div>

                    {/* ── TAB: Workspace & Pending ── */}
                    {activeTab === "workspace" && (
                        <div className="overflow-hidden">
                            {rows.length > 0 ? (
                                <table className="w-full table-fixed">
                                    <colgroup>
                                        <col className="w-[40px]" />
                                        <col className="w-[90px]" />
                                        <col className="w-[70px]" />
                                        <col className="w-[170px]" />
                                        <col className="w-[110px]" />
                                        <col className="w-[60px]" />
                                        <col className="w-[80px]" />
                                        <col className="w-[115px]" />
                                        <col className="w-[116px]" />
                                    </colgroup>
                                    <thead>
                                        <tr className="bg-gray-100/80 border-b border-gray-200">
                                            <TableHeader>#</TableHeader>
                                            <TableHeader>Customer</TableHeader>
                                            <TableHeader>Port</TableHeader>
                                            <TableHeader>Source File</TableHeader>
                                            <TableHeader>Sheet</TableHeader>
                                            <TableHeader align="right">Assy</TableHeader>
                                            <TableHeader align="right">Qty</TableHeader>
                                            <TableHeader>Upload Date</TableHeader>
                                            <th className="sticky right-0 z-20 border-l border-gray-200 bg-gray-100/95 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)]">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {rows.map((sr, index) => (
                                            <tr key={`${sr.upload_batch_id || ''}-${sr.id}`} className="group transition-colors hover:bg-gray-50/80">
                                                <td className="px-3 py-4 text-sm font-medium text-gray-500 tabular-nums border-r border-gray-100">
                                                    {(index + 1).toString().padStart(2, "0")}
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100">
                                                    <span className="inline-flex max-w-full rounded-lg border border-green-100 bg-green-50 px-2.5 py-1 text-xs font-semibold text-[#1D6F42]">
                                                        <span className="truncate">{sr.customer || "-"}</span>
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    <span className="block truncate">{sr.port || "-"}</span>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100">
                                                    <p className="truncate text-sm font-semibold text-gray-900" title={sr.source_file || ""}>
                                                        {sr.source_file || "-"}
                                                    </p>
                                                    <p className="mt-0.5 truncate text-xs text-gray-400">
                                                        Batch: {sr.upload_batch || "-"}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    <span className="block truncate">{sr.sheet_name || "-"}</span>
                                                </td>
                                                <td className="px-3 py-4 text-right font-mono text-sm text-gray-700 border-r border-gray-100">
                                                    {formatNumber(sr.unique_assy_numbers)}
                                                </td>
                                                <td className="px-3 py-4 text-right font-mono text-sm text-gray-700 border-r border-gray-100">
                                                    {formatNumber(sr.total_qty)}
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    {formatDate(sr.upload_date)}
                                                </td>
                                                <td className="sticky right-0 z-10 border-l border-gray-100 bg-white px-3 py-4 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)] group-hover:bg-gray-50">
                                                    <div className="flex items-center gap-1.5">
                                                        <Link
                                                            href={route("spp.preview", sr.id)}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50/30 text-blue-600 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700 shadow-xs"
                                                            title="Preview & Adjust"
                                                            aria-label="Preview and adjust SPP"
                                                        >
                                                            <EyeIcon className="h-4 w-4" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleExport(route("spp.exportDraftDirect", sr.id), `draft-${sr.id}`)}
                                                            disabled={exportingId !== null}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-green-200 bg-green-50/30 text-[#1D6F42] transition-colors hover:border-[#1D6F42]/50 hover:bg-green-50 hover:text-[#185c38] shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                            title="Export Draft Excel"
                                                            aria-label="Export draft SPP"
                                                        >
                                                            {exportingId === `draft-${sr.id}` ? (
                                                                <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                                </svg>
                                                            ) : (
                                                                <ArrowDownTrayIcon className="h-4 w-4" />
                                                            )}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setLockTarget(sr)}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50/30 text-amber-600 transition-colors hover:border-amber-400 hover:bg-amber-50 hover:text-amber-700 shadow-xs"
                                                            title="Quick Lock Plan"
                                                            aria-label="Quick lock plan"
                                                        >
                                                            <CheckCircleIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    icon={<DocumentTextIcon className="h-8 w-8 text-gray-300" />}
                                    title="No SR Available"
                                    description={
                                        hasFilters
                                            ? "No SR matches the current filter."
                                            : "Upload the customer's Shipping Release file first."
                                    }
                                    hint="Only Summary with locked status appears here."
                                />
                            )}
                        </div>
                    )}

                    {/* ── TAB: Fixed Supply Plans ── */}
                    {activeTab === "saved" && (
                        <div className="overflow-x-auto">
                            {savedRows.length > 0 ? (
                                <table className="w-full table-fixed">
                                    <colgroup>
                                        <col className="w-[40px]" />
                                        <col className="w-[90px]" />
                                        <col className="w-[70px]" />
                                        <col className="w-[170px]" />
                                        <col className="w-[110px]" />
                                        <col className="w-[60px]" />
                                        <col className="w-[80px]" />
                                        <col className="w-[140px]" />
                                        <col className="w-[115px]" />
                                        <col className="w-[116px]" />
                                    </colgroup>
                                    <thead>
                                        <tr className="bg-gray-100/80 border-b border-gray-200">
                                            <TableHeader>#</TableHeader>
                                            <TableHeader>Customer</TableHeader>
                                            <TableHeader>Port</TableHeader>
                                            <TableHeader>Source File</TableHeader>
                                            <TableHeader>Sheet</TableHeader>
                                            <TableHeader align="right">Assy</TableHeader>
                                            <TableHeader align="right">Qty</TableHeader>
                                            <TableHeader>Period Horizon</TableHeader>
                                            <TableHeader>Saved Date</TableHeader>
                                            <th className="sticky right-0 z-20 border-l border-gray-200 bg-gray-100/95 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)]">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {savedRows.map((plan, index) => (
                                            <tr key={`${plan.id}-${index}`} className="group transition-colors hover:bg-gray-50/80">
                                                <td className="px-3 py-4 text-sm font-medium text-gray-500 tabular-nums border-r border-gray-100">
                                                    {(index + 1).toString().padStart(2, "0")}
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100">
                                                    <span className="inline-flex max-w-full rounded-lg border border-green-100 bg-green-50 px-2.5 py-1 text-xs font-semibold text-[#1D6F42]">
                                                        <span className="truncate">{plan.customer}</span>
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    <span className="block truncate">{plan.port}</span>
                                                </td>
                                                <td className="px-3 py-4 border-r border-gray-100">
                                                    <p className="truncate text-sm font-semibold text-gray-900" title={plan.source_file}>
                                                        {plan.source_file}
                                                    </p>
                                                    <p className="mt-0.5 truncate text-xs text-gray-400">
                                                        Batch: {plan.batch_uuid}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    <span className="block truncate">{plan.sheet_name}</span>
                                                </td>
                                                <td className="px-3 py-4 text-right font-mono text-sm text-gray-700 border-r border-gray-100">
                                                    {formatNumber(plan.unique_assy_numbers)}
                                                </td>
                                                <td className="px-3 py-4 text-right font-mono text-sm text-gray-700 border-r border-gray-100">
                                                    {formatNumber(plan.total_qty)}
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-900 font-semibold border-r border-gray-100">
                                                    {plan.period_label}
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-650 border-r border-gray-100">
                                                    {formatDate(plan.last_updated)}
                                                </td>
                                                <td className="sticky right-0 z-10 border-l border-gray-100 bg-white px-3 py-4 shadow-[-8px_0_14px_-14px_rgba(15,23,42,0.5)] group-hover:bg-gray-50">
                                                    <div className="flex items-center gap-1.5">
                                                        <Link
                                                            href={route("spp.show", plan.period) + `?customer=${plan.customer}&sr_batch=${plan.upload_batch_id}`}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50/30 text-blue-600 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700 shadow-xs"
                                                            title="Lihat detail"
                                                            aria-label="View SPP detail"
                                                        >
                                                            <EyeIcon className="h-4 w-4" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleExport(route("spp.export", plan.period) + `?customer=${plan.customer}&sr_batch=${plan.upload_batch_id}`, `plan-${plan.period}-${plan.customer}`)}
                                                            disabled={exportingId !== null}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-green-200 bg-green-50/30 text-[#1D6F42] transition-colors hover:border-[#1D6F42]/50 hover:bg-green-50 hover:text-[#185c38] shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                                            title="Export Excel"
                                                            aria-label="Export SPP Excel"
                                                        >
                                                            {exportingId === `plan-${plan.period}-${plan.customer}` ? (
                                                                <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                                </svg>
                                                            ) : (
                                                                <ArrowDownTrayIcon className="h-4 w-4" />
                                                            )}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setUnlockTarget(plan)}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50/30 text-red-600 transition-colors hover:border-red-400 hover:bg-red-50 hover:text-red-700 shadow-xs"
                                                            title="Unlock / Hapus Fixed SPP"
                                                            aria-label="Unlock SPP batch"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    icon={<CheckCircleIcon className="h-8 w-8 text-gray-300" />}
                                    title="No Locked SPP Yet"
                                    description="No 6-month supply plan has been saved permanently yet."
                                />
                            )}
                        </div>
                    )}
                </div>
            </div>
            {/* ── Export Loading Overlay ── */}
            {exportingId && (
                <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/50 backdrop-blur-sm">
                    <div className="flex flex-col items-center gap-4 rounded-2xl bg-white px-10 py-8 shadow-2xl">
                        <svg className="h-10 w-10 animate-spin text-[#1D6F42]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <div className="text-center">
                            <p className="text-base font-semibold text-gray-900">Preparing Excel file…</p>
                            <p className="mt-1 text-sm text-gray-500">Please wait, this may take a moment.</p>
                        </div>
                    </div>
                </div>
            )}

            {lockTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                        <div className="border-b border-gray-100 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900">Quick Lock SPP</h3>
                                    <p className="mt-1 text-sm text-gray-500">Lock the production plan directly.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setLockTarget(null)}
                                    disabled={isLocking}
                                    className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 disabled:opacity-60"
                                >
                                    <XMarkIcon className="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div className="p-6">
                            <p className="text-sm text-gray-600">
                                Are you sure you want to lock the production plan for file:
                                <br />
                                <span className="font-semibold text-gray-900">{lockTarget.source_file || "-"}</span>?
                            </p>
                            <p className="mt-3 text-xs text-amber-700 bg-amber-50 rounded-xl px-3.5 py-2.5 border border-amber-100 leading-relaxed">
                                💡 <strong>Note:</strong> System default calculation will be used directly without manual adjustments. Ensure master data (Assembly, Standard Pack, UMH) is fully mapped.
                            </p>
                        </div>

                        <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                            <button
                                type="button"
                                onClick={() => setLockTarget(null)}
                                disabled={isLocking}
                                className="h-10 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmLock}
                                disabled={isLocking}
                                className="h-10 rounded-lg bg-[#1D6F42] px-4 text-sm font-medium text-white transition-colors hover:bg-[#185c38] disabled:opacity-60"
                            >
                                {isLocking ? "Locking..." : "Lock SPP"}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {unlockTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                        <div className="border-b border-gray-100 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900">Unlock Fixed SPP</h3>
                                    <p className="mt-1 text-sm text-gray-500">Unlock and delete this fixed plan batch.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setUnlockTarget(null)}
                                    disabled={isUnlocking}
                                    className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 disabled:opacity-60"
                                >
                                    <XMarkIcon className="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div className="p-6">
                            <p className="text-sm text-gray-600">
                                Are you sure you want to unlock the production plan for file:
                                <br />
                                <span className="font-semibold text-gray-900">{unlockTarget.source_file || "-"}</span>?
                            </p>
                            <p className="mt-3 text-xs text-red-700 bg-red-50 rounded-xl px-3.5 py-2.5 border border-red-100 leading-relaxed">
                                ⚠️ <strong>Warning:</strong> This will delete all fixed SPP entries for this batch. The original draft summaries will remain in the Workspace for editing.
                            </p>
                        </div>

                        <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                            <button
                                type="button"
                                onClick={() => setUnlockTarget(null)}
                                disabled={isUnlocking}
                                className="h-10 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmUnlock}
                                disabled={isUnlocking}
                                className="h-10 rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-60"
                            >
                                {isUnlocking ? "Unlocking..." : "Unlock Plan"}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}


/* ─── Sub-components ─── */

function TableHeader({ children, align = "left" }: { children: ReactNode; align?: "left" | "right" }) {
    const alignment = align === "right" ? "text-right" : "text-left";

    return (
        <th className={`border-r border-gray-200 px-5 py-4 text-xs font-bold uppercase tracking-wider text-gray-700 ${alignment}`}>
            {children}
        </th>
    );
}

function TabButton({
    active,
    onClick,
    count,
    children,
}: {
    active: boolean;
    onClick: () => void;
    count: number;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition-all ${active
                ? "bg-white text-gray-900 shadow-sm"
                : "text-gray-500 hover:text-gray-700"
                }`}
        >
            {children}
            {count > 0 && (
                <span
                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none ${active ? "bg-[#1D6F42]/10 text-[#1D6F42]" : "bg-gray-200 text-gray-500"
                        }`}
                >
                    {count}
                </span>
            )}
        </button>
    );
}

function StatCard({
    label,
    value,
    icon,
    color,
}: {
    label: string;
    value: string | number;
    icon: React.ReactNode;
    color: "slate" | "green";
}) {
    const colorMap = {
        slate: "border-gray-200 bg-white text-gray-700",
        green: "border-green-100 bg-green-50 text-[#1D6F42]",
    };
    const iconMap = {
        slate: "text-gray-400",
        green: "text-[#1D6F42]",
    };
    return (
        <div className={`flex items-center gap-4 rounded-2xl border px-5 py-4 shadow-sm ${colorMap[color]}`}>
            <span className={iconMap[color]}>{icon}</span>
            <div>
                <p className="text-[10px] font-bold uppercase tracking-wider opacity-60">{label}</p>
                <p className="text-lg font-bold tabular-nums">{value}</p>
            </div>
        </div>
    );
}

function EmptyState({
    icon,
    title,
    description,
    hint,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
    hint?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                {icon}
            </div>
            <h2 className="text-sm font-semibold text-gray-700">{title}</h2>
            <p className="mt-1 max-w-xs text-xs text-gray-400">{description}</p>
            {hint && (
                <div className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
                    <ExclamationTriangleIcon className="h-3.5 w-3.5" />
                    {hint}
                </div>
            )}
        </div>
    );
}

/* ─── Helpers ─── */

function buildQuery(filters: SppFilters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && String(v).trim() !== "")
    ) as Record<string, string>;
}

function toNumber(value: unknown) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
}

function formatNumber(value: unknown) {
    return toNumber(value).toLocaleString("en-US");
}

function formatDate(value: unknown) {
    if (!value) return "—";
    const dateStr = typeof value === "string" ? value.replace(" ", "T") : value;
    const d = new Date(dateStr as any);
    if (Number.isNaN(d.getTime())) return "—";
    return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

function formatDateRange(start?: unknown, end?: unknown) {
    if (!start && !end) return "—";
    if (start === end) return formatDate(start);
    return `${formatDate(start)} – ${formatDate(end)}`;
}
