import AdminLayout from "@/Layouts/AdminLayout";
import { Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    ArrowPathIcon,
    ChevronRightIcon,
    CloudArrowUpIcon,
    ExclamationTriangleIcon,
    MagnifyingGlassIcon,
    TableCellsIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

type UnmappedAssyRow = {
    assy_number: string;
    customer?: string;
    record_count?: number | string;
    total_qty?: number | string;
    latest_upload?: string;
    latest_source_file?: string;
    latest_batch?: string;
};

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

type Filters = {
    search?: string;
    customer?: string;
};

export default function UnmappedAssyIndex({
    items,
    summary,
    customers = [],
    filters = {},
    flash = {},
}: {
    items: Paginated<UnmappedAssyRow>;
    summary?: Record<string, number>;
    customers?: string[];
    filters?: Filters;
    flash?: { success?: string; warning?: string; error?: string };
}) {
    const rows = items?.data ?? [];
    const [search, setSearch] = useState(filters.search || "");
    const [customer, setCustomer] = useState(filters.customer || "");
    const [selected, setSelected] = useState<string[]>([]);
    const [isRemapping, setIsRemapping] = useState(false);

    const selectedAssyNumbers = useMemo(
        () => Array.from(new Set(selected.map((key) => key.split("||")[0]).filter(Boolean))),
        [selected]
    );
    const visibleKeys = useMemo(() => rows.map(rowKey), [rows]);
    const allVisibleSelected = visibleKeys.length > 0 && visibleKeys.every((key) => selected.includes(key));
    const hasFilters = Boolean(search || customer);
    const flashMessage = flash.success || flash.warning || flash.error;
    const flashTone = flash.success ? "success" : flash.warning ? "warning" : flash.error ? "error" : "";

    const applyFilters = (event?: React.FormEvent) => {
        event?.preventDefault();
        router.get(route("unmapped-assy.index"), cleanQuery({ search, customer }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch("");
        setCustomer("");
        router.get(route("unmapped-assy.index"), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const toggleAllVisible = () => {
        if (allVisibleSelected) {
            setSelected((current) => current.filter((key) => !visibleKeys.includes(key)));
            return;
        }

        setSelected((current) => Array.from(new Set([...current, ...visibleKeys])));
    };

    const toggleRow = (key: string) => {
        setSelected((current) =>
            current.includes(key)
                ? current.filter((item) => item !== key)
                : [...current, key]
        );
    };

    const remapSelected = () => {
        if (selectedAssyNumbers.length === 0) return;

        setIsRemapping(true);
        router.post(route("unmapped-assy.remap"), {
            assy_numbers: selectedAssyNumbers,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
            onFinish: () => setIsRemapping(false),
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
        <AdminLayout title="Unmapped Assy">
            <div className="min-h-screen bg-gray-50/40 px-5 pb-8 pt-2 font-sans md:px-8">
                <nav className="mb-4 flex items-center gap-2 text-sm" aria-label="Breadcrumb">
                    <span className="text-gray-600">Shipping Release</span>
                    <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                    <span className="font-medium text-gray-900">Unmapped Assy</span>
                </nav>

                {flashMessage && (
                    <div className={`mb-4 rounded-xl border px-4 py-3 text-sm font-semibold ${flashTone === "success"
                            ? "border-emerald-200 bg-emerald-50 text-emerald-800"
                            : flashTone === "warning"
                                ? "border-amber-200 bg-amber-50 text-amber-800"
                                : "border-red-200 bg-red-50 text-red-700"
                        }`}>
                        {flashMessage}
                    </div>
                )}

                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-100 p-6">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-gray-900">Unmapped Assy</h1>
                                <p className="mt-1 max-w-3xl text-sm text-gray-500">
                                    The SR remains stored exactly as in the original file. Use this page to monitor assembly numbers that are not yet in master, then remap in bulk once the master is complete.
                                </p>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <MiniStat label="Unique" value={formatNumber(summary?.unique_assy)} />
                                <MiniStat label="Records" value={formatNumber(summary?.records)} />
                                <MiniStat label="Qty" value={formatNumber(summary?.total_qty)} />
                            </div>
                        </div>
                    </div>

                    <div className="border-b border-gray-100 bg-gray-50/70 px-6 py-4">
                        <form onSubmit={applyFilters} className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div className="grid flex-1 gap-4 lg:grid-cols-3">
                                <label className="lg:col-span-2">
                                    <span className="mb-1.5 block text-sm font-semibold text-gray-700">Search assy</span>
                                    <div className="relative">
                                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                        <input
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search assy exactly as in SR..."
                                            className="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-10 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                        />
                                        {search && (
                                            <button type="button" onClick={() => setSearch("")} className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600" aria-label="Clear search">
                                                <XMarkIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-sm font-semibold text-gray-700">Customer</span>
                                    <select
                                        value={customer}
                                        onChange={(event) => setCustomer(event.target.value)}
                                        className="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-gray-900 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    >
                                        <option value="">All customers</option>
                                        {customers.map((item) => (
                                            <option key={item} value={item}>{item}</option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#1D6F42] px-5 text-sm font-semibold text-white hover:bg-[#185c38]">
                                    <MagnifyingGlassIcon className="h-4 w-4" />
                                    Apply
                                </button>
                                {hasFilters && (
                                    <button type="button" onClick={resetFilters} className="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        <ArrowPathIcon className="h-4 w-4" />
                                        Reset
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>

                    <div className="flex flex-col gap-3 border-b border-gray-100 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <p className="text-sm text-gray-500">
                            {selectedAssyNumbers.length > 0
                                ? `${selectedAssyNumbers.length} assemblies selected for exact-match remap.`
                                : "Select assemblies after master data is complete, then try bulk remap."}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Link href={route("assy.importPage")} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                <CloudArrowUpIcon className="h-4 w-4" />
                                Import Master Assy
                            </Link>
                            <button
                                type="button"
                                onClick={remapSelected}
                                disabled={selectedAssyNumbers.length === 0 || isRemapping}
                                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#1D6F42] px-4 text-sm font-semibold text-white hover:bg-[#185c38] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <ArrowPathIcon className={`h-4 w-4 ${isRemapping ? "animate-spin" : ""}`} />
                                {isRemapping ? "Remapping..." : "Remap"}
                            </button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[980px] table-fixed">
                            <colgroup>
                                <col className="w-[56px]" />
                                <col className="w-[300px]" />
                                <col className="w-[120px]" />
                                <col className="w-[120px]" />
                                <col className="w-[130px]" />
                                <col className="w-[180px]" />
                                <col className="w-[220px]" />
                            </colgroup>
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-100/80 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                    <th className="px-5 py-4">
                                        <input
                                            type="checkbox"
                                            checked={allVisibleSelected}
                                            onChange={toggleAllVisible}
                                            className="h-4 w-4 rounded border-gray-300 text-[#1D6F42] focus:ring-[#1D6F42]"
                                            aria-label="Select all visible rows"
                                        />
                                    </th>
                                    <th className="px-5 py-4">Assy from SR</th>
                                    <th className="px-5 py-4">Customer</th>
                                    <th className="px-5 py-4 text-right">Records</th>
                                    <th className="px-5 py-4 text-right">Qty</th>
                                    <th className="px-5 py-4">Latest Upload</th>
                                    <th className="px-5 py-4">Source</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.length > 0 ? rows.map((row) => {
                                    const key = rowKey(row);
                                    const checked = selected.includes(key);

                                    return (
                                        <tr key={key} className="hover:bg-gray-50/80">
                                            <td className="px-5 py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => toggleRow(key)}
                                                    className="h-4 w-4 rounded border-gray-300 text-[#1D6F42] focus:ring-[#1D6F42]"
                                                    aria-label={`Select ${row.assy_number}`}
                                                />
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 font-mono text-sm font-semibold text-amber-900">
                                                    {row.assy_number || "-"}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-sm font-semibold text-gray-700">{row.customer || "-"}</td>
                                            <td className="px-5 py-4 text-right font-mono text-sm text-gray-700">{formatNumber(row.record_count)}</td>
                                            <td className="px-5 py-4 text-right font-mono text-sm text-gray-700">{formatNumber(row.total_qty)}</td>
                                            <td className="px-5 py-4 text-sm text-gray-600">{formatDate(row.latest_upload)}</td>
                                            <td className="px-5 py-4">
                                                <p className="truncate text-sm font-semibold text-gray-800" title={row.latest_source_file || ""}>{row.latest_source_file || "-"}</p>
                                                <p className="mt-0.5 truncate text-xs text-gray-400">Batch: {row.latest_batch || "-"}</p>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={7} className="py-16 text-center">
                                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                                                <TableCellsIcon className="h-8 w-8 text-gray-400" />
                                            </div>
                                            <h2 className="mt-4 text-base font-semibold text-gray-800">No unmapped assemblies</h2>
                                            <p className="mt-1 text-sm text-gray-500">All assemblies shown from the SR are already linked to master, or the filter is too specific.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {rows.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-6 py-3.5">
                            <p className="text-sm text-gray-500">
                                Showing {formatNumber(items?.from || 1)}-{formatNumber(items?.to || rows.length)} of {formatNumber(items?.total || rows.length)} groups
                            </p>
                            {Number(items?.last_page ?? 1) > 1 && (
                                <div className="flex items-center gap-2 text-sm">
                                    <button type="button" disabled={!items.prev_page_url} onClick={() => goToPage(items.prev_page_url)} className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                                    <span className="font-semibold text-gray-600">{items.current_page} / {items.last_page}</span>
                                    <button type="button" disabled={!items.next_page_url} onClick={() => goToPage(items.next_page_url)} className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-right shadow-sm">
            <div className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">{label}</div>
            <div className="text-lg font-bold text-gray-900">{value}</div>
        </div>
    );
}

function rowKey(row: UnmappedAssyRow) {
    return `${row.assy_number || ""}||${row.customer || ""}`;
}

function cleanQuery(filters: Filters) {
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

function formatDate(value: unknown) {
    if (!value) return "-";
    const date = new Date(value as string | number | Date);
    if (Number.isNaN(date.getTime())) return "-";
    return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}
