import AdminLayout from "@/Layouts/AdminLayout";
import { router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    ArrowPathIcon,
    ArrowTrendingDownIcon,
    ArrowTrendingUpIcon,
    MagnifyingGlassIcon,
    ScaleIcon,
} from "@heroicons/react/24/outline";

export default function VarianceIndex({
    customers = [],
    batchOptions = [],
    filters = {},
    summary = {},
    comparisons = [],
    rows = [],
}) {
    const [customer, setCustomer] = useState(filters.customer || "");
    const [batchId, setBatchId] = useState(filters.batch_id || "");
    const [search, setSearch] = useState("");
    const [direction, setDirection] = useState("");

    const filteredRows = useMemo(() => {
        return rows.filter((row) => {
            const matchesSearch = !search
                || row.part_number?.toLowerCase().includes(search.toLowerCase())
                || row.current_file?.toLowerCase().includes(search.toLowerCase())
                || row.previous_file?.toLowerCase().includes(search.toLowerCase());
            const delta = Number(row.variance_qty || 0);
            const matchesDirection = !direction
                || (direction === "up" && delta > 0)
                || (direction === "down" && delta < 0);

            return matchesSearch && matchesDirection;
        });
    }, [rows, search, direction]);

    const applyFilters = () => {
        router.get(route("variance.index"), buildQuery({ customer, batch_id: batchId }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setCustomer("");
        setBatchId("");
        setSearch("");
        setDirection("");
        router.get(route("variance.index"), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AdminLayout title="Variance">
            <div className="min-h-screen bg-gray-50/40 px-5 pb-8 pt-2 font-sans md:px-8">
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-100 p-6">
                        <h1 className="text-2xl font-semibold tracking-tight text-gray-900">Variance Order</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Monitor perubahan firm order qty antara SR terbaru dan SR sebelumnya.
                        </p>
                    </div>

                    <div className="border-b border-gray-100 px-6 py-4">
                        <div className="grid gap-4 xl:grid-cols-[1fr_1fr_1fr_auto] xl:items-end">
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-gray-700">Customer</label>
                                <select
                                    value={customer}
                                    onChange={(event) => {
                                        setCustomer(event.target.value);
                                        setBatchId("");
                                    }}
                                    className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                >
                                    <option value="">All Customers</option>
                                    {customers.map((item) => (
                                        <option key={item.code} value={item.code}>
                                            {item.code}{item.name ? ` - ${item.name}` : ""}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-gray-700">Batch</label>
                                <select
                                    value={batchId}
                                    onChange={(event) => setBatchId(event.target.value)}
                                    disabled={!customer}
                                    className="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-900 disabled:bg-gray-50 disabled:text-gray-400 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                >
                                    <option value="">Latest completed batch</option>
                                    {batchOptions.map((batch) => (
                                        <option key={batch.id} value={batch.id}>
                                            {batch.source_file || batch.batch_uuid}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-gray-700">Search</label>
                                <div className="relative">
                                    <MagnifyingGlassIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                    <input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Part number or file..."
                                        className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white hover:bg-[#185c38]"
                                >
                                    <MagnifyingGlassIcon className="h-4 w-4" />
                                    Apply
                                </button>
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    <ArrowPathIcon className="h-4 w-4" />
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-5 p-6">
                        <div className="grid gap-4 md:grid-cols-4">
                            <MetricCard icon={ScaleIcon} label="Delta Qty" value={formatSigned(summary.variance_qty)} tone={Number(summary.variance_qty || 0) < 0 ? "down" : "up"} />
                            <MetricCard icon={ArrowTrendingUpIcon} label="Naik" value={formatNumber(summary.increase_count)} tone="up" />
                            <MetricCard icon={ArrowTrendingDownIcon} label="Turun" value={formatNumber(summary.decrease_count)} tone="down" />
                            <MetricCard icon={MagnifyingGlassIcon} label="Changed Parts" value={formatNumber(summary.changed_parts)} tone="neutral" />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {[
                                { key: "", label: "All" },
                                { key: "up", label: "Naik" },
                                { key: "down", label: "Turun" },
                            ].map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setDirection(item.key)}
                                    className={`h-9 rounded-lg px-4 text-sm font-semibold transition-colors ${
                                        direction === item.key
                                            ? "bg-[#1D6F42] text-white"
                                            : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                    }`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-gray-200">
                            <table className="w-full min-w-[1180px] text-sm">
                                <thead className="bg-gray-100">
                                    <tr>
                                        {["Customer", "Part Number", "Type", "Month", "Week", "ETD", "ETA", "Prev Qty", "Current Qty", "Variance", "%", "Compared Files"].map((header) => (
                                            <th key={header} className="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                                {header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {filteredRows.length === 0 ? (
                                        <tr>
                                            <td colSpan={12} className="px-3 py-10 text-center text-sm text-gray-500">
                                                {comparisons.length === 0
                                                    ? "Belum ada batch yang bisa dibandingkan."
                                                    : "Tidak ada perubahan sesuai filter saat ini."}
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredRows.map((row, index) => {
                                            const delta = Number(row.variance_qty || 0);
                                            return (
                                                <tr key={`${row.customer}-${row.part_number}-${row.month}-${row.week}-${index}`} className="hover:bg-gray-50">
                                                    <td className="px-3 py-3 font-semibold text-[#1D6F42]">{row.customer || "-"}</td>
                                                    <td className="px-3 py-3 font-medium text-gray-900">{row.part_number || "-"}</td>
                                                    <td className="px-3 py-3 text-gray-600">{row.order_type || "-"}</td>
                                                    <td className="px-3 py-3 text-gray-600">{row.month || "-"}</td>
                                                    <td className="px-3 py-3 text-gray-600">{row.week || "-"}</td>
                                                    <td className="px-3 py-3 text-gray-600">{shortDate(row.etd)}</td>
                                                    <td className="px-3 py-3 text-gray-600">{shortDate(row.eta)}</td>
                                                    <td className="px-3 py-3 text-right font-mono text-gray-700">{formatNumber(row.previous_qty)}</td>
                                                    <td className="px-3 py-3 text-right font-mono text-gray-900">{formatNumber(row.current_qty)}</td>
                                                    <td className={`px-3 py-3 text-right font-mono font-bold ${delta < 0 ? "text-rose-600" : "text-emerald-700"}`}>
                                                        {formatSigned(delta)}
                                                    </td>
                                                    <td className="px-3 py-3 text-right font-mono text-gray-600">
                                                        {row.variance_percent === null || row.variance_percent === undefined ? "-" : `${formatSigned(row.variance_percent)}%`}
                                                    </td>
                                                    <td className="px-3 py-3 text-xs text-gray-500">
                                                        <div className="max-w-[260px] truncate" title={row.current_file || ""}>{row.current_file || "-"}</div>
                                                        <div className="max-w-[260px] truncate" title={row.previous_file || ""}>{row.previous_file || "-"}</div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function MetricCard({ icon: Icon, label, value, tone }) {
    const toneClass = {
        up: "bg-emerald-50 text-emerald-700 border-emerald-100",
        down: "bg-rose-50 text-rose-700 border-rose-100",
        neutral: "bg-slate-50 text-slate-700 border-slate-100",
    }[tone] || "bg-slate-50 text-slate-700 border-slate-100";

    return (
        <div className={`rounded-xl border p-4 ${toneClass}`}>
            <div className="flex items-center gap-2 text-sm font-semibold">
                <Icon className="h-5 w-5" />
                {label}
            </div>
            <div className="mt-2 text-2xl font-bold text-gray-950">{value}</div>
        </div>
    );
}

function buildQuery(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && String(value).trim() !== "")
    );
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function formatSigned(value) {
    const number = Number(value || 0);
    const formatted = Math.abs(number).toLocaleString(undefined, { maximumFractionDigits: 2 });
    if (number > 0) return `+${formatted}`;
    if (number < 0) return `-${formatted}`;
    return "0";
}

function shortDate(value) {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "-";
    return `${date.getDate().toString().padStart(2, "0")}/${(date.getMonth() + 1).toString().padStart(2, "0")}`;
}
