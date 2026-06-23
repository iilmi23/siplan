import AdminLayout from "@/Layouts/AdminLayout";
import { Link, router } from "@inertiajs/react";
import { useMemo, useState, useEffect } from "react";
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ChevronRightIcon,
    CubeTransparentIcon,
    MagnifyingGlassIcon,
    TableCellsIcon,
    XMarkIcon,
    ArrowPathIcon,
    CircleStackIcon,
    ExclamationTriangleIcon,
} from "@heroicons/react/24/outline";

type Customer = { id: number; code: string; name: string };
type SrBatch = Record<string, any>;
type SppRecord = Record<string, any>;
type Summary = Record<string, any>;
type SppShowFilters = {
    customer?: string;
    sr_batch?: string;
};

type SppMonth = {
    period: string;
    [key: string]: any;
};

type SppCell = {
    bal?: number;
    del?: number;
    prod?: number;
};

type SppRow = {
    months?: Record<string, SppCell>;
    total_qty?: number;
    [key: string]: any;
};

const COLUMN_CONFIGS: Record<string, any[]> = {
    SAI: [
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "assy_code", label: "ASSY CODE", width: 80, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "carline", label: "CARLINE", width: 80 },
        { id: "umh", label: "UMH", width: 70, align: "right" }
    ],
    TYC: [
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "std_pack", label: "Std Pack", width: 70, align: "right" },
        { id: "umh", label: "UMH", width: 70, align: "right" }
    ],
    YC: [
        { id: "carline", label: "CARLINE", width: 80, sticky: true },
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "umh", label: "UMH", width: 70, align: "right" }
    ],
    YNA: [
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "carline", label: "CARLINE", width: 80, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "std_pack", label: "Std Pack", width: 70, align: "right" },
        { id: "umh", label: "UMH", width: 70, align: "right" }
    ]
};

const DEFAULT_COLUMNS = [
    { id: "carline", label: "CARLINE", width: 70, sticky: true },
    { id: "pattern", label: "TYPE", width: 120, sticky: true },
    { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
    { id: "level", label: "LEVEL", width: 80 },
    { id: "assy_code", label: "ASSY CODE", width: 80 },
    { id: "std_pack", label: "Std Pack", width: 70, align: "right" },
    { id: "umh", label: "UMH", width: 70, align: "right" }
];

export default function SPPShow({
    customers,
    srBatches = [],
    filters,
    period,
    records,
    summary,
    months = [],
    rows = [],
}: {
    customers: Customer[];
    srBatches?: SrBatch[];
    filters: SppShowFilters;
    period: string;
    records: SppRecord[];
    summary: Summary;
    months?: SppMonth[];
    rows?: SppRow[];
}) {
    const [customer, setCustomer] = useState(filters.customer || "");
    const [srBatch, setSrBatch] = useState(filters.sr_batch || "");
    const [activeTab, setActiveTab] = useState<'spp' | 'as400'>('spp');
    const [as400Loading, setAs400Loading] = useState(false);
    const [as400Error, setAs400Error] = useState<string | null>(null);
    const [as400Headers, setAs400Headers] = useState<string[]>(["TYPE", "HARNESS No.", "LEVEL"]);
    const [as400Rows, setAs400Rows] = useState<any[]>([]);

    const selectedCustomerId = useMemo(() => {
        const found = customers.find((c) => c.code === customer);
        return found ? found.id : null;
    }, [customers, customer]);

    const selectedSr = useMemo(() => {
        return srBatches.find((b) => String(b.id) === String(srBatch)) || summary.selected_sr || null;
    }, [srBatches, srBatch, summary.selected_sr]);

    const isSpp = summary.source === "spp";

    const activeColumns = useMemo(() => {
        const custCode = customer || (rows && rows[0]?.customer) || "";
        const cols = COLUMN_CONFIGS[custCode] || DEFAULT_COLUMNS;
        let currentLeft = 0;
        return cols.map((col) => {
            const res = {
                ...col,
                left: col.sticky ? currentLeft : undefined
            };
            if (col.sticky) {
                currentLeft += col.width;
            }
            return res;
        });
    }, [customer, rows]);

    const totals = useMemo(() => {
        return Object.fromEntries(
            months.map((month) => {
                const totalBal = rows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.bal), 0);
                const totalDel = rows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.del), 0);
                const totalProd = rows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.prod), 0);
                return [month.period, { bal: totalBal, del: totalDel, prod: totalProd }];
            })
        );
    }, [rows, months]);

    const grandTotal = useMemo(
        () => Object.values(totals).reduce((sum, item) => sum + toNumber(item.del), 0),
        [totals]
    );

    const handleFilter = () => {
        router.get(`/spp/${period}`, buildQuery({ customer, sr_batch: srBatch }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setCustomer("");
        setSrBatch("");
        router.get(`/spp/${period}`, {}, { preserveState: true, preserveScroll: true });
    };

    const fetchAs400Data = async () => {
        if (!selectedCustomerId) {
            setAs400Rows([]);
            setAs400Headers(["TYPE", "HARNESS No.", "LEVEL"]);
            return;
        }

        setAs400Loading(true);
        setAs400Error(null);
        try {
            const response = await fetch(`/as400/data?customer_id=${selectedCustomerId}&period=${period}`);
            if (!response.ok) {
                throw new Error("Failed to fetch AS-400 preview data.");
            }
            const data = await response.json();
            if (data.success) {
                setAs400Headers(data.headers);
                setAs400Rows(data.rows);
            } else {
                throw new Error(data.message || "Failed to fetch AS-400 preview data.");
            }
        } catch (err: any) {
            setAs400Error(err.message || "An error occurred while fetching data.");
            setAs400Rows([]);
        } finally {
            setAs400Loading(false);
        }
    };

    useEffect(() => {
        if (activeTab === 'as400') {
            fetchAs400Data();
        }
    }, [activeTab, selectedCustomerId, period]);

    return (
        <AdminLayout title="SPP Details">
            <div className="min-h-screen bg-gray-50/40 px-5 pb-8 pt-2 font-sans md:px-8">

                {/* Breadcrumb */}
                <nav className="mb-4 flex items-center gap-2 text-sm" aria-label="Breadcrumb">
                    <Link href={route("dashboard")} className="text-gray-600 hover:text-[#1D6F42] transition-colors">
                        Home
                    </Link>
                    <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                    <Link href={route("spp")} className="text-gray-600 hover:text-[#1D6F42] transition-colors">
                        SPP
                    </Link>
                    <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                    <span className="font-medium text-gray-900">Details</span>
                </nav>

                {/* Workflow Stepper */}
                <div className="mb-6 bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <div className="flex flex-col md:flex-row items-center justify-between gap-4 md:gap-8 max-w-3xl mx-auto">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-green-50 border border-green-200 text-[#1D6F42] flex items-center justify-center font-semibold text-sm">
                                ✓
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-900">Select Document</p>
                                <p className="text-xs text-gray-500">Select SR file & upload batch</p>
                            </div>
                        </div>
                        
                        <div className="hidden md:block flex-1 h-0.5 bg-[#1D6F42]/30" />
                        
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-green-50 border border-green-200 text-[#1D6F42] flex items-center justify-center font-semibold text-sm">
                                ✓
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-900">Validation Mapper</p>
                                <p className="text-xs text-gray-500">Verify template parsing results</p>
                            </div>
                        </div>
                        
                        <div className="hidden md:block flex-1 h-0.5 bg-[#1D6F42]/30" />
                        
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-[#1D6F42] text-white flex items-center justify-center font-semibold text-sm shadow-sm ring-4 ring-green-100">
                                3
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-900">Matrix Results</p>
                                <p className="text-xs text-gray-500">View 6-month supply plan</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Main Card */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    {/* Header */}
                    <div className="flex flex-col gap-4 border-b border-gray-100 p-6 pb-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-gray-900">SPP Details</h1>
                            <p className="mt-1 text-sm text-gray-500">
                                Periode: <span className="font-medium text-gray-700">{summary.period || period}</span>
                                {activeTab === "spp" && selectedSr && (
                                    <> · SR: <span className="font-medium text-gray-700">
                                        {selectedSr.source_file || "—"}{selectedSr.sheet_name ? ` / ${selectedSr.sheet_name}` : ""}
                                    </span></>
                                )}
                            </p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                            <Link
                                href={route("spp")}
                                className="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                            >
                                <ArrowLeftIcon className="h-4 w-4" />
                                Back
                            </Link>
                            {activeTab === "spp" && isSpp && (
                                <a
                                    href={`/spp/${period}/export?customer=${customer}&sr_batch=${srBatch}`}
                                    className="inline-flex h-9 items-center gap-2 rounded-lg bg-[#1D6F42] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#185c38]"
                                >
                                    <ArrowDownTrayIcon className="h-4 w-4" />
                                    Export Excel
                                </a>
                            )}
                            {activeTab === "as400" && selectedCustomerId && as400Rows.length > 0 && (
                                <a
                                    href={`/as400/export?customer_id=${selectedCustomerId}&period=${period}`}
                                    className="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                                >
                                    <ArrowDownTrayIcon className="h-4 w-4" />
                                    Export CSV AS-400
                                </a>
                            )}
                        </div>
                    </div>

                    {/* Summary Stats */}
                    <div className="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
                        <div className="px-6 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Records</p>
                            <p className="mt-1 text-2xl font-bold text-gray-900">{activeTab === "spp" ? (summary.total_records ?? "—") : as400Rows.length}</p>
                        </div>
                        <div className="px-6 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Quantity</p>
                            <p className="mt-1 text-2xl font-bold text-[#1D6F42]">
                                {activeTab === "spp" 
                                    ? (summary.total_qty ?? "—") 
                                    : as400Rows.reduce((sum, r) => sum + (r.total_prod || 0), 0).toLocaleString()}
                            </p>
                        </div>
                        <div className="px-6 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Unique Assy Numbers</p>
                            <p className="mt-1 text-2xl font-bold text-gray-900">
                                {activeTab === "spp" 
                                    ? (summary.unique_assy_numbers ?? "—") 
                                    : new Set(as400Rows.map(r => r.harness_no)).size}
                            </p>
                        </div>
                    </div>

                    {/* Tabs switcher */}
                    <div className="flex border-b border-gray-100 bg-gray-50/50 px-6">
                        <button
                            onClick={() => setActiveTab("spp")}
                            className={`border-b-2 px-4 py-3 text-sm font-semibold transition-all ${
                                activeTab === "spp"
                                    ? "border-[#1D6F42] text-[#1D6F42]"
                                    : "border-transparent text-gray-500 hover:text-gray-700"
                            }`}
                        >
                            SPP 6-Month Plan
                        </button>
                        {/* Hide for presentation
                        <button
                            onClick={() => setActiveTab("as400")}
                            className={`border-b-2 px-4 py-3 text-sm font-semibold transition-all ${
                                activeTab === "as400"
                                    ? "border-[#1D6F42] text-[#1D6F42]"
                                    : "border-transparent text-gray-500 hover:text-gray-700"
                            }`}
                        >
                            AS-400 Format
                        </button>
                        */}
                    </div>

                    {activeTab === "spp" ? (
                        <>
                            {/* Filters */}
                            <div className="border-b border-gray-100 px-6 py-4">
                                <div className="grid gap-4 lg:grid-cols-[1fr_1.5fr_auto] items-end">
                                    <div>
                                        <label htmlFor="spp-customer" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Customer
                                        </label>
                                        <select
                                            id="spp-customer"
                                            className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 transition focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                            value={customer}
                                            onChange={(e) => { setCustomer(e.target.value); setSrBatch(""); }}
                                        >
                                            <option value="">All Customers</option>
                                            {customers.map((c) => (
                                                <option key={c.code} value={c.code}>{c.code}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label htmlFor="spp-sr-batch" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                            SR Upload
                                        </label>
                                        <select
                                            id="spp-sr-batch"
                                            className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 transition focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                            value={srBatch}
                                            onChange={(e) => setSrBatch(e.target.value)}
                                        >
                                            <option value="">All SR Uploads</option>
                                            {srBatches.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.label}{b.uploaded_at ? ` (${b.uploaded_at})` : ""}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex items-end gap-2">
                                        <button
                                            onClick={handleFilter}
                                            className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#185c38]"
                                        >
                                            <MagnifyingGlassIcon className="h-4 w-4" />
                                            Filter
                                        </button>
                                        <button
                                            onClick={handleReset}
                                            className="inline-flex h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                                        >
                                            <XMarkIcon className="h-4 w-4" />
                                            Reset
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Data Table */}
                            {isSpp && months && months.length > 0 ? (
                                <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50 max-h-[70vh] border-b border-gray-200">
                                    <table className="w-full text-sm border-collapse">
                                        <thead>
                                            <tr className="bg-gray-100/80 border-b border-gray-200">
                                                {activeColumns.map((col, idx) => (
                                                    <ExcelTh
                                                        key={col.id}
                                                        rowSpan={3}
                                                        sticky={col.sticky}
                                                        left={col.left}
                                                        width={col.width}
                                                        className={idx === activeColumns.length - 1 ? "border-r-2 border-r-gray-400" : ""}
                                                    >
                                                        {col.label}
                                                    </ExcelTh>
                                                ))}
                                                {months.map((month) => (
                                                    <th
                                                        key={month.period}
                                                        colSpan={3}
                                                        className="sticky top-0 z-20 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-center text-xs font-bold text-gray-700"
                                                    >
                                                        {month.label}
                                                    </th>
                                                ))}
                                                <ExcelTh rowSpan={3} width={76}>TOTAL</ExcelTh>
                                            </tr>
                                            <tr>
                                                {months.map((month) => (
                                                    <th
                                                        key={`range-${month.period}`}
                                                        colSpan={3}
                                                        className="sticky top-[25px] z-20 border-b border-r border-gray-200 bg-gray-50/50 px-2 py-1 text-center text-[9px] font-semibold text-gray-400"
                                                    >
                                                        {month.range_label || `${month.bucket === "firm" ? "ORDER" : "ESTIMATE"} ${month.year}`}
                                                    </th>
                                                ))}
                                            </tr>
                                            <tr>
                                                {months.flatMap((month) =>
                                                    ["BAL", "DEL", "PROD"].map((label) => (
                                                        <th
                                                            key={`${month.period}-${label}`}
                                                            className="sticky top-[47px] z-20 border-b border-r border-gray-200 bg-slate-50/85 px-2 py-1 text-center text-[10px] font-bold text-gray-650"
                                                            style={{ minWidth: 52 }}
                                                        >
                                                            {label}
                                                        </th>
                                                    ))
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={activeColumns.length + months.length * 3 + 1} className="border-b border-r border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                                        No SPP data found.
                                                    </td>
                                                </tr>
                                            ) : rows.map((row, index) => {
                                                const bgClass = index % 2 === 0 ? "bg-white" : "bg-gray-50";
                                                return (
                                                    <tr key={`${row.assy_number}-${index}`} className={bgClass}>
                                                        {activeColumns.map((col) => {
                                                            const value = row[col.id] || "";
                                                            if (col.id === "assy_number") {
                                                                return (
                                                                    <StickyTd
                                                                        key={col.id}
                                                                        left={col.left}
                                                                        width={col.width}
                                                                        className={`font-semibold text-gray-800 border-r-2 border-r-gray-400 shadow-[1px_0_3px_rgba(0,0,0,0.06)] ${col.className || ""}`}
                                                                        bgClass={bgClass}
                                                                    >
                                                                        {value}
                                                                    </StickyTd>
                                                                );
                                                            }
                                                            return (
                                                                <StickyTd
                                                                    key={col.id}
                                                                    sticky={col.sticky}
                                                                    left={col.left}
                                                                    width={col.width}
                                                                    align={col.align || "left"}
                                                                    bgClass={bgClass}
                                                                >
                                                                    {value}
                                                                </StickyTd>
                                                            );
                                                        })}
                                                        {months.flatMap((month) => {
                                                            const cell = row.months?.[month.period] || {};
                                                            const isFirm = month.bucket === "firm";
                                                            return [
                                                                <QtyInput key={`${row.assy_number}-${month.period}-bal`} value={cell.bal} isFirm={isFirm} />,
                                                                <QtyInput key={`${row.assy_number}-${month.period}-del`} value={cell.del} strong isFirm={isFirm} />,
                                                                <QtyInput key={`${row.assy_number}-${month.period}-prod`} value={cell.prod} isFirm={isFirm} />,
                                                            ];
                                                        })}
                                                        <td className="border-b border-r border-gray-200 bg-gray-100/60 px-2 py-1 text-right font-bold text-gray-800">
                                                            {formatNumber(row.total_qty)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                {activeColumns.map((col, idx) => {
                                                    const isLastSticky = col.sticky && (!activeColumns[idx + 1] || !activeColumns[idx + 1].sticky);
                                                    const style = {
                                                        left: col.left !== undefined ? `${col.left}px` : undefined,
                                                        minWidth: col.width,
                                                        width: col.width
                                                    };
                                                    if (isLastSticky) {
                                                        return (
                                                            <td
                                                                key={col.id}
                                                                className="sticky bottom-0 z-30 border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-right text-[11px] font-bold text-slate-800 shadow-[1px_0_3px_rgba(0,0,0,0.06)]"
                                                                style={style}
                                                            >
                                                                TOTAL
                                                            </td>
                                                        );
                                                    }
                                                    return (
                                                        <td
                                                            key={col.id}
                                                            className={`sticky bottom-0 ${col.sticky ? "z-30" : "z-20"} border-b border-r border-gray-200 bg-gray-50`}
                                                            style={style}
                                                        />
                                                    );
                                                })}
                                                {months.flatMap((month) => {
                                                    const total = (totals[month.period] || {}) as SppCell;
                                                    const isFirm = month.bucket === "firm";
                                                    return [
                                                        <TotalTd key={`${month.period}-total-bal`} value={total.bal} isFirm={isFirm} />,
                                                        <TotalTd key={`${month.period}-total-del`} value={total.del} isFirm={isFirm} />,
                                                        <TotalTd key={`${month.period}-total-prod`} value={total.prod} isFirm={isFirm} />,
                                                    ];
                                                })}
                                                <td className="sticky bottom-0 z-20 border-b border-r border-gray-200 bg-slate-100 px-2 py-2 text-right text-[11px] font-bold text-slate-900">
                                                    {formatNumber(grandTotal)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            ) : (
                                <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="bg-gray-100/80 border-b border-gray-200">
                                                <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">No</th>
                                                <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Customer</th>
                                                {isSpp && (
                                                    <>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Carline</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Pattern</th>
                                                    </>
                                                )}
                                                <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Assy Number</th>
                                                {isSpp && (
                                                    <>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Level</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Assy Code</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">Std Pack</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">UMH</th>
                                                    </>
                                                )}
                                                <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">
                                                    {isSpp ? "Period" : "ETA"}
                                                </th>
                                                {isSpp ? (
                                                    <>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">BAL</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">DEL</th>
                                                        <th className="border-r border-gray-200 px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">PROD</th>
                                                        <th className="px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">Total Qty</th>
                                                    </>
                                                ) : (
                                                    <th className="px-3 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">Qty</th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {records.length === 0 ? (
                                                <tr>
                                                    <td colSpan={isSpp ? 14 : 5} className="py-16 text-center">
                                                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                                                            <CubeTransparentIcon className="h-8 w-8 text-gray-400" />
                                                        </div>
                                                        <p className="mt-4 text-sm font-semibold text-gray-500">No records found for this period.</p>
                                                    </td>
                                                </tr>
                                            ) : (
                                                records.map((record, index) => (
                                                    <tr key={record.id ?? index} className="group transition-colors hover:bg-gray-50/80">
                                                        <td className="border-r border-gray-100 px-3 py-3.5 text-sm font-medium text-gray-500 tabular-nums">{index + 1}</td>
                                                        <td className="border-r border-gray-100 px-3 py-3.5">
                                                            <span className="inline-flex max-w-full rounded-lg border border-green-100 bg-green-50 px-2.5 py-1 text-xs font-semibold text-[#1D6F42]">
                                                                <span className="truncate">{record.customer}</span>
                                                            </span>
                                                        </td>
                                                        {isSpp && (
                                                            <>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-sm text-gray-600">{record.carline || record.assy?.carline?.code || "—"}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-sm text-gray-600">{record.pattern || record.assy?.pattern || "—"}</td>
                                                            </>
                                                        )}
                                                        <td className="border-r border-gray-100 px-3 py-3.5 font-mono text-sm font-semibold text-gray-900">{record.assy_number}</td>
                                                        {isSpp && (
                                                            <>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-sm text-gray-600">{record.level || "—"}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-sm text-gray-600">{record.assy_code || "—"}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm text-gray-700">{record.std_pack ?? "—"}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm text-gray-700">{record.umh ?? "—"}</td>
                                                            </>
                                                        )}
                                                        <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm text-gray-600">
                                                            {isSpp ? record.period : record.eta}
                                                        </td>
                                                        {isSpp ? (
                                                            <>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm text-gray-700">{record.bal_qty}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm font-semibold text-gray-900">{record.del_qty}</td>
                                                                <td className="border-r border-gray-100 px-3 py-3.5 text-right font-mono text-sm text-gray-700">{record.prod_qty}</td>
                                                                <td className="px-3 py-3.5 text-right font-mono text-sm font-bold text-gray-900 bg-gray-50/60">{record.total_qty}</td>
                                                            </>
                                                        ) : (
                                                            <td className="px-3 py-3.5 text-right font-mono text-sm font-semibold text-gray-900">{record.total_qty ?? record.qty}</td>
                                                        )}
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            {/* AS-400 Filters */}
                            <div className="border-b border-gray-100 px-6 py-4">
                                <div className="grid gap-4 md:grid-cols-[1fr_auto] items-end">
                                    <div>
                                        <label htmlFor="as400-customer" className="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Customer
                                        </label>
                                        <select
                                            id="as400-customer"
                                            className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 transition focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                            value={customer}
                                            onChange={(e) => { setCustomer(e.target.value); }}
                                        >
                                            <option value="">Select Customer</option>
                                            {customers.map((c) => (
                                                <option key={c.id} value={c.code}>
                                                    {c.code} - {c.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={fetchAs400Data}
                                            disabled={!selectedCustomerId || as400Loading}
                                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50"
                                            title="Refresh data"
                                        >
                                            <ArrowPathIcon className={`h-4 w-4 ${as400Loading ? 'animate-spin' : ''}`} />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* AS-400 Error Banner */}
                            {as400Error && (
                                <div className="m-6 flex items-start gap-3 rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-rose-800">
                                    <ExclamationTriangleIcon className="h-5 w-5 shrink-0 text-rose-500" />
                                    <div>
                                        <h4 className="text-sm font-bold">An Error Occurred</h4>
                                        <p className="mt-0.5 text-xs text-rose-700 leading-relaxed">{as400Error}</p>
                                    </div>
                                </div>
                            )}

                            {/* AS-400 Preview Grid Data */}
                            <div className="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50">
                                {as400Loading ? (
                                    <div className="flex flex-col items-center justify-center py-20">
                                        <div className="h-10 w-10 animate-spin rounded-full border-4 border-[#1D6F42] border-t-transparent"></div>
                                        <p className="mt-4 text-sm font-semibold text-gray-500">Loading AS-400 preview data...</p>
                                    </div>
                                ) : as400Rows.length > 0 ? (
                                    <table className="w-full text-sm border-collapse">
                                        <thead>
                                            <tr className="bg-gray-100/80 border-b border-gray-200">
                                                <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">TYPE</th>
                                                <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">HARNESS No.</th>
                                                <th className="border-r border-gray-200 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">LEVEL</th>
                                                {as400Headers.slice(3).map((h, idx) => (
                                                    <th key={idx} className="border-r border-gray-200 px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">
                                                        {h}
                                                    </th>
                                                ))}
                                                <th className="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-gray-700">TOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {as400Rows.map((row, rIdx) => (
                                                <tr key={rIdx} className="hover:bg-gray-50/80 transition-colors">
                                                    <td className="border-r border-gray-100 px-6 py-3.5 text-sm font-semibold text-gray-700">{row.type || "-"}</td>
                                                    <td className="border-r border-gray-100 px-6 py-3.5 text-sm font-mono font-semibold text-gray-900">{row.harness_no}</td>
                                                    <td className="border-r border-gray-100 px-6 py-3.5 text-sm text-gray-600">{row.level || "-"}</td>
                                                    {row.weeks.map((val: any, wIdx: number) => (
                                                        <td key={wIdx} className="border-r border-gray-100 px-6 py-3.5 text-sm font-mono text-right text-gray-800">
                                                            {val === " " ? (
                                                                <span className="text-gray-300 italic select-none">—</span>
                                                            ) : (
                                                                val.toLocaleString()
                                                            )}
                                                        </td>
                                                    ))}
                                                    <td className="px-6 py-3.5 text-sm font-mono font-bold text-right text-emerald-700 bg-emerald-50/20">
                                                        {row.total_prod.toLocaleString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-20 text-center px-4">
                                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-gray-50 mb-4">
                                            <CircleStackIcon className="h-8 w-8 text-gray-400" />
                                        </div>
                                        <h3 className="text-base font-bold text-gray-800">No preview data</h3>
                                        <p className="mt-1 text-sm text-gray-500 max-w-md">
                                            {!customer
                                                ? "Please select a Customer above to preview the AS-400 data ready for export."
                                                : "No SPP data found in the database for the selected customer. Make sure SPP has been generated and saved first."}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

/* ─── Helpers ─── */

function buildQuery(filters: SppShowFilters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && String(v).trim() !== "")
    ) as Record<string, string>;
}

/* ─── Matrix Layout Helper Components ─── */

function ExcelTh({ children, rowSpan, sticky = false, left = 0, width = 80, className = "" }) {
    return (
        <th
            rowSpan={rowSpan}
            className={`border-b border-r border-gray-200 bg-gray-50 px-2 py-1.5 text-center text-[10px] uppercase tracking-wider font-semibold text-gray-500 ${sticky ? "sticky z-30" : "sticky top-0 z-20"} ${className}`}
            style={{ left: sticky ? `${left}px` : undefined, top: 0, minWidth: width, width }}
        >
            {children}
        </th>
    );
}

function StickyTd({ children, sticky = true, left = 0, width = 80, align = "left", className = "", bgClass = "bg-white" }) {
    return (
        <td
            className={`${sticky ? "sticky z-10" : ""} border-b border-r border-gray-200 px-2 py-1 text-${align} ${bgClass} ${className}`}
            style={{ left: sticky ? `${left}px` : undefined, minWidth: width, width }}
        >
            {children || "—"}
        </td>
    );
}

function QtyInput({ value, strong = false, muted = false, isFirm = false }) {
    const bg = isFirm ? "bg-amber-50/20" : "bg-white";
    return (
        <td className={`border-b border-r border-gray-200 p-0 ${bg}`}>
            <input
                type="number"
                value={toNumber(value) || ""}
                disabled={true}
                className={`h-7 w-[52px] bg-transparent px-1 text-right text-[11px] outline-none transition ${strong ? "font-bold text-gray-900 animate-fade-in" : "text-gray-700"} ${muted ? "text-gray-400" : ""}`}
            />
        </td>
    );
}

function TotalTd({ value, isFirm = false }) {
    const bg = isFirm ? "bg-amber-50/50" : "bg-gray-150/40";
    return (
        <td className={`sticky bottom-0 z-20 border-b border-r border-gray-200 px-2 py-2 text-right text-[11px] font-bold text-slate-800 ${bg}`}>
            {formatNumber(value)}
        </td>
    );
}

function toNumber(value: any) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
}

function formatNumber(value: any) {
    return toNumber(value).toLocaleString("en-US");
}
