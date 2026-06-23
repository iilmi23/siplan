import AdminLayout from "@/Layouts/AdminLayout";
import { router, usePage } from "@inertiajs/react";
import { useEffect, useMemo, useState, useRef } from "react";
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ArrowPathIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    ClipboardDocumentCheckIcon,
    ExclamationTriangleIcon,
    TableCellsIcon,
} from "@heroicons/react/24/outline";

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
        { id: "umh", label: "UMH", width: 70, align: "right", invalidIfBlank: true }
    ],
    TYC: [
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "std_pack", label: "Std Pack", width: 70, align: "right", invalidIfBlank: true },
        { id: "umh", label: "UMH", width: 70, align: "right", invalidIfBlank: true }
    ],
    YC: [
        { id: "carline", label: "CARLINE", width: 80, sticky: true },
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "umh", label: "UMH", width: 70, align: "right", invalidIfBlank: true }
    ],
    YNA: [
        { id: "pattern", label: "TYPE", width: 120, sticky: true },
        { id: "carline", label: "CARLINE", width: 80, sticky: true },
        { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
        { id: "level", label: "LEVEL", width: 80 },
        { id: "assy_code", label: "ASSY CODE", width: 80 },
        { id: "std_pack", label: "Std Pack", width: 70, align: "right", invalidIfBlank: true },
        { id: "umh", label: "UMH", width: 70, align: "right", invalidIfBlank: true }
    ]
};

const DEFAULT_COLUMNS = [
    { id: "carline", label: "CARLINE", width: 70, sticky: true },
    { id: "pattern", label: "TYPE", width: 120, sticky: true },
    { id: "assy_number", label: "HARNESS NO.", width: 150, sticky: true, className: "border-r border-r-gray-300 shadow-[1px_0_3px_rgba(0,0,0,0.06)]" },
    { id: "level", label: "LEVEL", width: 80 },
    { id: "assy_code", label: "ASSY CODE", width: 80 },
    { id: "std_pack", label: "Std Pack", width: 70, align: "right", invalidIfBlank: true },
    { id: "umh", label: "UMH", width: 70, align: "right", invalidIfBlank: true }
];

export default function SPPPreview({
    sr,
    summary = {},
    months = [],
    rows = [],
}: {
    sr: Record<string, any>;
    summary?: Record<string, any>;
    months?: SppMonth[];
    rows?: SppRow[];
}) {
    const { props } = usePage();
    const storageKey = `spp-draft-${sr.id}`;
    const originalStorageKey = `spp-draft-orig-${sr.id}`;
    const [editableRows, setEditableRows] = useState(rows);
    const [saved, setSaved] = useState(false);
    const [isSavingFixed, setIsSavingFixed] = useState(false);
    const [notice, setNotice] = useState("");
    const [noticeType, setNoticeType] = useState<"success" | "error">("success");
    const [showErrorsOnly, setShowErrorsOnly] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isImporting, setIsImporting] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    const activeColumns = useMemo(() => {
        const cols = COLUMN_CONFIGS[sr.customer] || DEFAULT_COLUMNS;
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
    }, [sr.customer]);

    const activeColumnsWidth = useMemo(() => {
        return activeColumns.reduce((sum, col) => sum + col.width, 0);
    }, [activeColumns]);

    const triggerImportFilePicker = () => {
        fileInputRef.current?.click();
    };

    const handleImportFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setIsImporting(true);
        setNotice("");

        const formData = new FormData();
        formData.append("file", file);
        formData.append("customer", sr.customer || "ALL");
        formData.append("period", months[1]?.period || months[0]?.period || "");
        formData.append("months", JSON.stringify(months));
        formData.append("rows", JSON.stringify(editableRows));

        const csrfToken = (props as any).csrf_token;

        try {
            const response = await fetch("/spp/import-draft", {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": csrfToken || "",
                    "Accept": "application/json",
                },
            });

            const result = await response.json();
            if (response.ok && result.success && Array.isArray(result.rows)) {
                setEditableRows(result.rows);
                window.localStorage.setItem(storageKey, JSON.stringify(result.rows));
                setSaved(false);
                setNoticeType("success");
                setNotice("Excel data successfully imported and merged to draft.");
            } else {
                setNoticeType("error");
                setNotice(result.message || "Failed to import Excel file. Check data format compatibility.");
            }
        } catch (error) {
            setNoticeType("error");
            setNotice("An error occurred while uploading the Excel file.");
        } finally {
            setIsImporting(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = "";
            }
        }
    };

    const initialLoadRef = useRef(false);

    useEffect(() => {
        const storedOrig = window.localStorage.getItem(originalStorageKey);
        const currentOrig = JSON.stringify(rows);

        if (storedOrig !== currentOrig) {
            window.localStorage.removeItem(storageKey);
            window.localStorage.setItem(originalStorageKey, currentOrig);
            setEditableRows(rows);
        } else {
            const raw = window.localStorage.getItem(storageKey);
            if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) setEditableRows(parsed);
                } catch {
                    window.localStorage.removeItem(storageKey);
                }
            }
        }
        initialLoadRef.current = true;
    }, [storageKey, originalStorageKey, rows]);

    useEffect(() => {
        if (!initialLoadRef.current) return;
        window.localStorage.setItem(storageKey, JSON.stringify(editableRows));
    }, [editableRows, storageKey]);

    const totals = useMemo(() => {
        return Object.fromEntries(
            months.map((month) => {
                const totalBal = editableRows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.bal), 0);
                const totalDel = editableRows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.del), 0);
                const totalProd = editableRows.reduce((sum, row) => sum + toNumber(row.months?.[month.period]?.prod), 0);
                return [month.period, { bal: totalBal, del: totalDel, prod: totalProd }];
            })
        );
    }, [editableRows, months]);

    const grandTotal = useMemo(
        () => Object.values(totals).reduce((sum, item) => sum + toNumber(item.del), 0),
        [totals]
    );

    const validation = useMemo(() => {
        const isColActive = (id: string) => activeColumns.some(col => col.id === id);

        const missingStdPack = isColActive("std_pack")
            ? editableRows.filter((row) => isBlank(row.std_pack)).length
            : 0;
        const missingUmh = isColActive("umh")
            ? editableRows.filter((row) => isBlank(row.umh)).length
            : 0;

        const hasLevel = isColActive("level");
        const hasAssyCode = isColActive("assy_code");
        const missingMaster = editableRows.filter((row) => {
            const levelBlank = hasLevel && isBlank(row.level);
            const assyCodeBlank = hasAssyCode && isBlank(row.assy_code);
            return levelBlank || assyCodeBlank;
        }).length;

        let stdPackMismatches = 0;
        if (isColActive("std_pack")) {
            editableRows.forEach((row) => {
                const std = toNumber(row.std_pack);
                if (std > 0) {
                    Object.values(row.months || {}).forEach((cell) => {
                        const prod = toNumber(cell.prod);
                        if (prod > 0 && prod % std !== 0) {
                            stdPackMismatches++;
                        }
                    });
                }
            });
        }

        const emptyPlan = editableRows.length === 0 || grandTotal === 0;
        const totalIssues = missingStdPack + missingUmh + missingMaster + stdPackMismatches + (emptyPlan ? 1 : 0);
        return {
            missingStdPack,
            missingUmh,
            missingMaster,
            stdPackMismatches,
            emptyPlan,
            totalIssues,
            ready: missingStdPack === 0 && missingUmh === 0 && missingMaster === 0 && !emptyPlan
        };
    }, [editableRows, grandTotal, activeColumns]);

    const visibleRows = useMemo(() => {
        const mappedWithIndex = editableRows.map((row, originalIndex) => ({ ...row, originalIndex } as any));
        if (!showErrorsOnly) return mappedWithIndex;

        const isColActive = (id: string) => activeColumns.some(col => col.id === id);
        const hasLevel = isColActive("level");
        const hasAssyCode = isColActive("assy_code");

        return mappedWithIndex.filter((row) => {
            const hasMissingStdPack = isColActive("std_pack") && isBlank(row.std_pack);
            const hasMissingUmh = isColActive("umh") && isBlank(row.umh);
            const hasMissingMaster = (hasLevel && isBlank(row.level)) || (hasAssyCode && isBlank(row.assy_code));

            let hasStdPackMismatch = false;
            if (isColActive("std_pack")) {
                const std = toNumber(row.std_pack);
                if (std > 0) {
                    Object.values(row.months || {}).forEach((cell) => {
                        const prod = toNumber((cell as any).prod);
                        if (prod > 0 && prod % std !== 0) {
                            hasStdPackMismatch = true;
                        }
                    });
                }
            }

            return hasMissingStdPack || hasMissingUmh || hasMissingMaster || hasStdPackMismatch;
        });
    }, [editableRows, showErrorsOnly, activeColumns]);

    const updateMeta = (rowIndex: number, field: string, value: unknown) => {
        setEditableRows((curr) => curr.map((row, i) => i === rowIndex ? { ...row, [field]: value } : row));
        setSaved(false);
    };

    const updateQty = (rowIndex: number, period: string, field: keyof SppCell, value: unknown) => {
        setEditableRows((curr) => curr.map((row, i) => {
            if (i !== rowIndex) return row;

            const currentVal = toNumber(value);
            const nextMonths = { ...row.months };
            const sortedPeriods = months.map(m => m.period);
            const targetIndex = sortedPeriods.indexOf(period);

            if (targetIndex === -1) return row;

            // Apply the edit to the target cell
            const currentCell = { ...(nextMonths[period] || { bal: 0, del: 0, prod: 0 }) };
            currentCell[field] = currentVal;

            // If we edited BAL or DEL, recalculate PROD for this month as max(0, DEL - BAL)
            if (field === "bal" || field === "del") {
                currentCell.prod = Math.max(0, toNumber(currentCell.del) - toNumber(currentCell.bal));
            }
            nextMonths[period] = currentCell;

            // Propagate the changes to all subsequent months
            for (let idx = targetIndex + 1; idx < sortedPeriods.length; idx++) {
                const p = sortedPeriods[idx];
                const prevPeriod = sortedPeriods[idx - 1];
                const prevCell = nextMonths[prevPeriod] || { bal: 0, del: 0, prod: 0 };

                // BAL(i) = BAL(i-1) + PROD(i-1) - DEL(i-1)
                const newBal = Math.max(0, toNumber(prevCell.bal) + toNumber(prevCell.prod) - toNumber(prevCell.del));

                const currentCellForMonth = { ...(nextMonths[p] || { bal: 0, del: 0, prod: 0 }) };
                currentCellForMonth.bal = newBal;

                // Recalculate PROD(i) = max(0, DEL(i) - BAL(i))
                currentCellForMonth.prod = Math.max(0, toNumber(currentCellForMonth.del) - newBal);

                nextMonths[p] = currentCellForMonth;
            }

            return {
                ...row,
                months: nextMonths,
                total_qty: Object.values(nextMonths).reduce((s, c) => s + toNumber(c.del), 0)
            };
        }));
        setSaved(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        const target = e.currentTarget;
        const row = parseInt(target.getAttribute('data-row') || '0', 10);
        const col = parseInt(target.getAttribute('data-col') || '0', 10);
        const colsCount = activeColumns.length + months.length * 3;
        const rowsCount = visibleRows.length;

        let nextRow = row;
        let nextCol = col;

        switch (e.key) {
            case 'ArrowUp':
                nextRow = Math.max(0, row - 1);
                break;
            case 'ArrowDown':
                nextRow = Math.min(rowsCount - 1, row + 1);
                break;
            case 'ArrowLeft':
                nextCol = Math.max(0, col - 1);
                break;
            case 'ArrowRight':
                nextCol = Math.min(colsCount - 1, col + 1);
                break;
            case 'Enter':
                nextRow = Math.min(rowsCount - 1, row + 1);
                break;
            default:
                return;
        }

        e.preventDefault();
        const nextInput = document.querySelector(`input[data-row="${nextRow}"][data-col="${nextCol}"]`) as HTMLInputElement;
        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    };

    const saveDraft = () => {
        window.localStorage.setItem(storageKey, JSON.stringify(editableRows));
        setSaved(true);
        setNotice("");
    };

    const saveFixed = () => {
        setIsSavingFixed(true);
        setNotice("");
        router.post(route("spp.store", sr.id), {
            months, rows: editableRows,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                window.localStorage.removeItem(storageKey);
                window.localStorage.removeItem(originalStorageKey);
                setSaved(false);
                setNoticeType("success");
                setNotice("Fixed SPP saved to database successfully.");
            },
            onError: () => {
                setNoticeType("error");
                setNotice("Failed to save fixed SPP. Check data and try again.");
            },
            onFinish: () => setIsSavingFixed(false),
        });
    };

    const resetDraft = () => {
        window.localStorage.removeItem(storageKey);
        window.localStorage.removeItem(originalStorageKey);
        setEditableRows(rows);
        setSaved(false);
    };

    const exportExcel = async () => {
        setIsExporting(true);
        setNotice("");
        // Auto-save changes to local storage before exporting to prevent data loss
        window.localStorage.setItem(storageKey, JSON.stringify(editableRows));
        setSaved(true);

        const csrfToken = (props as any).csrf_token;
        const targetPeriod = months[1]?.period || months[0]?.period || "";

        try {
            const formData = new FormData();
            formData.append("customer", sr.customer || "ALL");
            formData.append("period", targetPeriod);
            formData.append("months", JSON.stringify(months));
            formData.append("rows", JSON.stringify(editableRows));

            const response = await fetch("/spp/export-draft", {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": csrfToken || "",
                    "Accept": "application/json",
                },
            });

            if (!response.ok) {
                throw new Error("Failed to export draft.");
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `SPP_DRAFT_${sr.customer || "ALL"}_${targetPeriod}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            setNoticeType("error");
            setNotice("An error occurred while exporting draft.");
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <AdminLayout title="SPP Preview">
            <div className="min-h-screen bg-gray-50/40 px-4 pb-8 pt-2 font-sans md:px-6">

                {/* Workflow Stepper - Sleek & Compact */}
                <div className="mb-4 bg-white rounded-xl border border-gray-200 px-5 py-3 shadow-xs">
                    <div className="flex items-center justify-center gap-4 text-xs md:text-sm font-semibold max-w-2xl mx-auto">
                        <div className="flex items-center gap-2 text-[#1D6F42]">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-green-50 border border-green-200 text-[#1D6F42] text-[10px] font-bold">✓</span>
                            <span>Select Document</span>
                        </div>
                        <div className="w-8 h-0.5 bg-green-200" />
                        <div className="flex items-center gap-2 text-gray-900">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#1D6F42] text-white text-[10px] font-bold shadow-xs ring-2 ring-green-100">2</span>
                            <span className="font-bold text-gray-950">Validation Mapper</span>
                        </div>
                        <div className="w-8 h-0.5 bg-gray-200" />
                        <div className="flex items-center gap-2 text-gray-400 opacity-60">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-gray-150 border border-gray-200 text-gray-500 text-[10px] font-bold">3</span>
                            <span>Matrix Results</span>
                        </div>
                    </div>
                </div>

                {/* Clean Page Header Row */}
                <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    {/* Left: Metadata & Back Button */}
                    <div className="flex items-start gap-3">
                        <button
                            type="button"
                            onClick={() => router.get(route("spp"))}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:bg-gray-50 hover:text-gray-700"
                            title="Back"
                        >
                            <ArrowLeftIcon className="h-5 w-5" />
                        </button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-lg font-bold text-gray-900">Six Month Production Plan</h1>
                                <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 border border-slate-200">
                                    {sr.customer || "ALL"}
                                </span>
                            </div>
                            <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500">
                                <span className="font-semibold text-gray-700 truncate max-w-[280px] md:max-w-md" title={sr.source_file || ""}>
                                    {sr.source_file || "—"}
                                </span>
                                {sr.sheet_name && <span className="text-gray-400">({sr.sheet_name})</span>}
                                <span className="text-gray-300">•</span>
                                <span>Period: {summary.period_range || "—"}</span>
                                <span className="text-gray-300">•</span>
                                <span>Rows: {formatNumber(summary.total_records)}</span>
                                <span className="text-gray-300">•</span>
                                <span>Assy: {formatNumber(summary.unique_assy_numbers)}</span>
                                <span className="text-gray-350">•</span>
                                <span className="inline-flex items-center gap-1 text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded text-[10px]">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Draft Auto-Saved
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Right: Action Buttons Group */}
                    <div className="flex items-center gap-2 md:justify-end whitespace-nowrap">
                        <button
                            type="button"
                            onClick={resetDraft}
                            className="inline-flex h-9 items-center gap-1 rounded-lg border border-red-200 bg-white px-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 hover:text-red-700 transition shadow-xs"
                            title="Revert to initial data"
                        >
                            <ArrowPathIcon className="h-3.5 w-3.5" />
                            Reset
                        </button>
                        <div className="h-5 w-px bg-gray-200 mx-0.5"></div>
                        <button
                            type="button"
                            onClick={exportExcel}
                            disabled={isExporting}
                            className="inline-flex h-9 items-center gap-1 rounded-lg border border-green-255 bg-green-50/45 px-2.5 text-xs font-semibold text-[#1D6F42] hover:bg-green-50 hover:text-[#185c38] transition shadow-xs disabled:opacity-50"
                        >
                            {isExporting ? (
                                <ArrowPathIcon className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <ArrowDownTrayIcon className="h-3.5 w-3.5" />
                            )}
                            Export
                        </button>
                        <button
                            type="button"
                            onClick={triggerImportFilePicker}
                            disabled={isImporting}
                            className="inline-flex h-9 items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition shadow-xs"
                        >
                            {isImporting ? (
                                <ArrowPathIcon className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <ArrowUpTrayIcon className="h-3.5 w-3.5" />
                            )}
                            Import
                        </button>
                        <button
                            type="button"
                            onClick={saveFixed}
                            disabled={isSavingFixed || !validation.ready}
                            className="inline-flex h-9 items-center gap-1 rounded-lg bg-[#1D6F42] px-3.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#185c38] disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
                        >
                            <CheckCircleIcon className="h-3.5 w-3.5" />
                            {isSavingFixed ? "Saving..." : "Save Fixed"}
                        </button>
                        <input
                            type="file"
                            ref={fileInputRef}
                            onChange={handleImportFileChange}
                            accept=".xlsx,.xls"
                            className="hidden"
                        />
                    </div>
                </div>

                {/* Clean Action-oriented Alert Banners */}
                {!validation.ready && (
                    <div className="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4 shadow-xs text-sm">
                        <div className="flex items-start gap-2.5 text-amber-850">
                            <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                            <div>
                                <p className="font-semibold text-amber-900">Action Required: Detected {validation.totalIssues} incomplete/invalid data fields</p>
                                <p className="text-xs text-amber-705 mt-0.5">
                                    {[
                                        validation.missingUmh > 0 && `${validation.missingUmh} empty UMH`,
                                        validation.missingStdPack > 0 && `${validation.missingStdPack} empty Standard Pack`,
                                        validation.missingMaster > 0 && `${validation.missingMaster} empty Level/Assy Code`,
                                        validation.stdPackMismatches > 0 && `${validation.stdPackMismatches} production quantity not a multiple of Standard Pack`
                                    ].filter(Boolean).join(", ")}. Please complete the empty red-colored cells below.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowErrorsOnly(!showErrorsOnly)}
                            className={`inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg px-4 text-xs font-bold transition border shadow-xs ${showErrorsOnly
                                    ? "border-amber-500 bg-amber-100 text-amber-950 hover:bg-amber-150"
                                    : "border-amber-305 bg-white text-amber-800 hover:bg-amber-50"
                                }`}
                        >
                            {showErrorsOnly ? "Show All Rows" : "Focus on Rows with Issues"}
                        </button>
                    </div>
                )}

                {validation.ready && (
                    <div className="mb-4 flex items-center gap-2.5 rounded-xl border border-green-200 bg-green-50/50 p-4 text-sm text-emerald-800 shadow-xs">
                        <CheckCircleIcon className="h-5 w-5 text-emerald-600 shrink-0" />
                        <div>
                            <p className="font-semibold text-emerald-950">All Data Valid & Complete</p>
                            <p className="text-xs text-emerald-700/95 mt-0.5">All UMH and Standard Pack data are mapped. You are ready to save this production plan with the **Save Fixed** button.</p>
                        </div>
                    </div>
                )}

                {/* Local Storage Auto-Saved Notices */}
                {saved && (
                    <div className="mb-3 flex items-center gap-2 rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-sm font-medium text-[#1D6F42]">
                        <CheckCircleIcon className="h-4 w-4 shrink-0" />
                        Draft adjustments saved in the browser.
                    </div>
                )}
                {notice && (
                    <div className={`mb-3 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium ${noticeType === "success" ? "border-green-100 bg-green-50 text-[#1D6F42]" : "border-red-100 bg-red-50 text-red-700"}`}>
                        {noticeType === "success" ? <CheckCircleIcon className="h-4 w-4 shrink-0" /> : <ExclamationTriangleIcon className="h-4 w-4 shrink-0" />}
                        {notice}
                    </div>
                )}

                {/* Spreadsheet */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                    {/* Sleek Table toolbar */}
                    <div className="flex flex-col gap-2 border-b border-gray-200 bg-gray-50/50 px-4 py-2 sm:flex-row sm:items-center sm:justify-between text-xs text-gray-500">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setShowErrorsOnly(!showErrorsOnly)}
                                className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold transition shadow-xs border ${showErrorsOnly
                                    ? "border-amber-400 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                    : "border-gray-200 bg-white text-gray-650 hover:bg-gray-50"
                                    }`}
                            >
                                <ExclamationTriangleIcon className={`h-3.5 w-3.5 ${showErrorsOnly ? "text-amber-500" : "text-gray-400"}`} />
                                <span>Filter Rows with Issues</span>
                                <span className={`ml-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold ${showErrorsOnly ? "bg-amber-100 text-amber-800" : "bg-gray-100 text-gray-500"}`}>
                                    {showErrorsOnly ? "ON" : "OFF"}
                                </span>
                            </button>
                        </div>

                        <div className="flex items-center gap-4 text-xs font-medium text-gray-500 sm:justify-end">
                            <div>
                                <span>Total 6 Months: </span>
                                <span className="text-sm font-bold text-[#1D6F42]">{formatNumber(grandTotal)}</span>
                                {summary.total_qty && summary.total_qty !== grandTotal && (
                                    <span className="text-[10px] text-gray-400 ml-1">(Raw: {formatNumber(summary.total_qty)})</span>
                                )}
                            </div>
                            <div className="h-4 w-px bg-gray-200"></div>
                            <div>Print Date: {new Date().toLocaleDateString()}</div>
                        </div>
                    </div>

                    {/* Scrollable table */}
                    <div className="overflow-auto scrollbar-thin" style={{ maxHeight: "calc(100vh - 280px)" }}>
                        <table className="border-separate border-spacing-0 border-t border-l border-gray-200 text-[11px]" style={{ minWidth: `${activeColumnsWidth + months.length * 156}px` }}>
                            <thead>
                                <tr>
                                    {activeColumns.map((col) => (
                                        <ExcelTh
                                            key={col.id}
                                            rowSpan={3}
                                            sticky={col.sticky}
                                            left={col.left}
                                            width={col.width}
                                            className={col.className}
                                        >
                                            {col.label}
                                        </ExcelTh>
                                    ))}
                                    {months.map((month) => (
                                        <th
                                            key={`month-${month.period}`}
                                            colSpan={3}
                                            className={`sticky top-0 z-20 border-r border-b border-gray-200 px-2 py-1.5 text-center text-[10px] uppercase tracking-wider font-bold ${month.bucket === "firm"
                                                ? "bg-amber-50/80 text-amber-900 border-b-amber-200"
                                                : "bg-gray-50 text-gray-500"
                                                }`}
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
                            <tbody>
                                {visibleRows.length === 0 ? (
                                    <tr>
                                        <td colSpan={activeColumns.length + months.length * 3 + 1} className="border-b border-r border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                            No {showErrorsOnly ? "invalid " : ""}SPP data for this upload.
                                        </td>
                                    </tr>
                                ) : visibleRows.map((row, index) => {
                                    const bgClass = index % 2 === 0 ? "bg-white" : "bg-gray-50";
                                    return (
                                        <tr key={`${row.assy_number}-${index}`} className={bgClass}>
                                            {activeColumns.map((col, colIdx) => {
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
                                                const isInvalid = col.invalidIfBlank && isBlank(value);
                                                return (
                                                    <EditableSticky
                                                        key={col.id}
                                                        value={value}
                                                        sticky={col.sticky}
                                                        left={col.left}
                                                        width={col.width}
                                                        align={col.align || "left"}
                                                        invalid={isInvalid}
                                                        onChange={(v) => updateMeta(row.originalIndex, col.id, v)}
                                                        bgClass={bgClass}
                                                        data-row={index}
                                                        data-col={colIdx}
                                                        onKeyDown={handleKeyDown}
                                                    />
                                                );
                                            })}
                                            {months.flatMap((month, monthIdx) => {
                                                const cell = row.months?.[month.period] || {};
                                                const isFirm = month.bucket === "firm";
                                                return [
                                                    <QtyInput key={`${row.assy_number}-${month.period}-bal`} value={cell.bal} onChange={(v) => updateQty(row.originalIndex, month.period, "bal", v)} muted={monthIdx > 0} disabled={monthIdx > 0} isFirm={isFirm} data-row={index} data-col={activeColumns.length + monthIdx * 3 + 0} onKeyDown={handleKeyDown} />,
                                                    <QtyInput key={`${row.assy_number}-${month.period}-del`} value={cell.del} onChange={(v) => updateQty(row.originalIndex, month.period, "del", v)} strong disabled isFirm={isFirm} data-row={index} data-col={activeColumns.length + monthIdx * 3 + 1} onKeyDown={handleKeyDown} />,
                                                    <QtyInput key={`${row.assy_number}-${month.period}-prod`} value={cell.prod} onChange={(v) => updateQty(row.originalIndex, month.period, "prod", v)} isFirm={isFirm} data-row={index} data-col={activeColumns.length + monthIdx * 3 + 2} onKeyDown={handleKeyDown} />,
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

                    {/* Footer hint */}
                    <div className="flex items-center gap-2 border-t border-gray-100 bg-gray-50 px-5 py-2.5 text-[10px] text-gray-400">
                        <TableCellsIcon className="h-3.5 w-3.5 shrink-0" />
                        Click a white cell to make adjustments. The DEL column automatically synchronizes with PROD.
                    </div>
                </div>
                {/* ── Export Loading Overlay ── */}
                {isExporting && (
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

                {/* ── Import Loading Overlay ── */}
                {isImporting && (
                    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/50 backdrop-blur-sm">
                        <div className="flex flex-col items-center gap-4 rounded-2xl bg-white px-10 py-8 shadow-2xl">
                            <svg className="h-10 w-10 animate-spin text-[#1D6F42]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <div className="text-center">
                                <p className="text-base font-semibold text-gray-900">Uploading & Parsing Excel…</p>
                                <p className="mt-1 text-sm text-gray-500">Merging spreadsheet data into your draft supply plan.</p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

/* ─── Sub-components ─── */

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

function EditableSticky({ value, onChange, sticky = true, left = 0, width = 80, align = "left", invalid = false, bgClass = "bg-white", className = "", ...props }) {
    return (
        <td
            className={`${sticky ? "sticky z-10" : ""} border-b border-r border-gray-200 p-0 ${invalid ? "bg-red-50/50 border-red-200" : bgClass} ${className}`}
            style={{ left: sticky ? `${left}px` : undefined, minWidth: width, width }}
        >
            <input
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`h-7 w-full bg-transparent px-2 text-${align} text-[11px] outline-none transition focus:bg-white focus:ring-2 focus:ring-slate-500/20 ${invalid ? "font-semibold text-red-600" : "text-gray-700"}`}
                {...props}
            />
        </td>
    );
}

function QtyInput({ value, onChange, strong = false, muted = false, disabled = false, isFirm = false, ...props }) {
    const bg = disabled
        ? "bg-gray-50/50"
        : isFirm
            ? "bg-amber-50/20"
            : "bg-white";
    return (
        <td className={`border-b border-r border-gray-200 p-0 ${bg}`}>
            <input
                type="number"
                value={toNumber(value) || ""}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                className={`h-7 w-[52px] bg-transparent px-1 text-right text-[11px] outline-none transition focus:bg-slate-50 focus:ring-2 focus:ring-slate-500/20 ${strong ? "font-bold text-gray-900" : "text-gray-700"} ${muted || disabled ? "text-gray-400" : ""}`}
                {...props}
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



/* ─── Helpers ─── */

function toNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
}

function isBlank(value) {
    return value === null || value === undefined || String(value).trim() === "";
}

function formatNumber(value) {
    return toNumber(value).toLocaleString("en-US");
}

function csvCell(value) {
    const text = String(value ?? "");
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function safeName(value) {
    return String(value).replace(/[^a-z0-9_-]+/gi, "_").replace(/^_+|_+$/g, "");
}
