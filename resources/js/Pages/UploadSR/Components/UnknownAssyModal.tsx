import { useEffect, useState } from "react";
import { ExclamationTriangleIcon, CloudArrowUpIcon } from "@heroicons/react/24/outline";

interface UnknownAssyModalProps {
    preview: {
        total_records?: number;
        unique_assy_numbers?: number;
        unknown_assy_numbers?: string[];
    };
    carlines: Array<{ id: number | string; code: string; name?: string }>;
    onCancel: () => void;
    onConfirm: () => void;
    onRegistered: (assyNumber: string) => void;
    disabled: boolean;
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

function formatNumber(value: any) {
    return Number(value || 0).toLocaleString("en-US");
}

function PreviewMetric({ label, value, tone = "default" }: { label: string; value: any; tone?: string }) {
    const isWarning = tone === "warning";

    return (
        <div className={`rounded-xl border px-4 py-3 ${isWarning ? "border-amber-200 bg-amber-50" : "border-gray-200 bg-white"}`}>
            <p className={`text-xs font-medium ${isWarning ? "text-amber-700" : "text-gray-500"}`}>{label}</p>
            <p className={`mt-1 text-xl font-semibold ${isWarning ? "text-amber-800" : "text-gray-900"}`}>
                {formatNumber(value)}
            </p>
        </div>
    );
}

export default function UnknownAssyModal({
    preview,
    carlines = [],
    onCancel,
    onConfirm,
    onRegistered,
    disabled,
}: UnknownAssyModalProps) {
    const unknownAssyNumbers = preview?.unknown_assy_numbers || [];

    const [rowsState, setRowsState] = useState<Array<{
        assy_number: string;
        assy_code: string;
        carline_id: string;
        level: string;
        pattern: string;
        standard_sea_quantity: string;
        standard_air_quantity: string;
        max_quantity_sea: string;
        max_quantity_air: string;
        umh: string;
        error?: string;
    }>>([]);

    const [globalCarline, setGlobalCarline] = useState("");
    const [isSaving, setIsSaving] = useState(false);
    const [errorMsg, setErrorMsg] = useState("");
    const [successMsg, setSuccessMsg] = useState("");

    useEffect(() => {
        if (unknownAssyNumbers.length > 0) {
            const initial = unknownAssyNumbers.map((num: string) => {
                const suffix = num.includes("-") ? num.split("-")[1] : "";
                return {
                    assy_number: num,
                    assy_code: suffix || "",
                    carline_id: "",
                    level: "1",
                    pattern: "",
                    standard_sea_quantity: "",
                    standard_air_quantity: "",
                    max_quantity_sea: "",
                    max_quantity_air: "",
                    umh: "0",
                };
            });
            setRowsState(initial);
        }
    }, [preview]);

    const updateRowField = (index: number, field: string, value: string) => {
        setRowsState((current) => {
            const copy = [...current];
            copy[index] = {
                ...copy[index],
                [field]: value,
                error: undefined,
            };
            return copy;
        });
    };

    const applyGlobalCarline = () => {
        if (!globalCarline) return;
        setRowsState((current) =>
            current.map((row) => ({ ...row, carline_id: globalCarline }))
        );
    };

    const handleRegisterAll = async (e: React.FormEvent) => {
        e.preventDefault();

        const hasMissingFields = rowsState.some(
            (row) => !row.assy_code || !row.carline_id || !row.level || !row.umh
        );
        if (hasMissingFields) {
            setErrorMsg("Please fill in all input fields for each row.");
            return;
        }

        setIsSaving(true);
        setErrorMsg("");
        setSuccessMsg("");

        try {
            const payload = {
                items: rowsState.map((row) => ({
                    assy_number: row.assy_number,
                    assy_code: row.assy_code,
                    carline_id: row.carline_id,
                    level: row.level,
                    pattern: row.pattern || null,
                    standard_sea_quantity: row.standard_sea_quantity === "" ? null : parseInt(row.standard_sea_quantity, 10),
                    standard_air_quantity: row.standard_air_quantity === "" ? null : parseInt(row.standard_air_quantity, 10),
                    max_quantity_sea: row.max_quantity_sea === "" ? null : parseInt(row.max_quantity_sea, 10),
                    max_quantity_air: row.max_quantity_air === "" ? null : parseInt(row.max_quantity_air, 10),
                    umh: row.umh,
                })),
            };

            const response = await fetch(window.route("assy.bulk-store"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || !result.success) {
                if (result && result.errors) {
                    const copy = [...rowsState];
                    let genericErrText = "";
                    Object.keys(result.errors).forEach((key) => {
                        const match = key.match(/^items\.(\d+)\.(.+)$/);
                        if (match) {
                            const idx = parseInt(match[1]);
                            const field = match[2];
                            const errText = result.errors[key].join(" ");
                            copy[idx] = {
                                ...copy[idx],
                                error: `${field}: ${errText}`,
                            };
                        } else {
                            genericErrText += " " + result.errors[key].join(" ");
                        }
                    });
                    setRowsState(copy);
                    throw new Error(result.message || genericErrText || "Failed to save bulk data.");
                }
                throw new Error(result?.message || "Failed to register Assemblies in bulk.");
            }

            setSuccessMsg(`Successfully registered ${result.data?.length} Assemblies!`);

            setTimeout(() => {
                setSuccessMsg("");
                rowsState.forEach((row) => onRegistered(row.assy_number));
            }, 1200);

        } catch (err: any) {
            setErrorMsg(err.message || "Failed to save data.");
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4">
            <div className="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-100 flex flex-col max-h-[85vh] animate-slideDown">
                
                {/* Header Banner */}
                <div className="border-b border-amber-100 bg-amber-50/40 px-6 py-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100/70 text-amber-700">
                            <ExclamationTriangleIcon className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-slate-800">Assembly Not Registered in Master</h2>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Fill in the data below to register all of them in bulk, or skip by clicking <span className="font-semibold text-emerald-700">Continue upload</span>.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Content body */}
                <div className="flex-1 overflow-y-auto space-y-5 px-6 py-5 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
                    
                    {/* Stats */}
                    <div className="grid gap-4 grid-cols-3">
                        <PreviewMetric label="Total records" value={preview.total_records} />
                        <PreviewMetric label="Unique assy" value={preview.unique_assy_numbers} />
                        <PreviewMetric label="Unmapped assy" value={rowsState.length} tone="warning" />
                    </div>

                    {rowsState.length > 0 && (
                        <div className="space-y-4">
                            
                            {/* Global Action Toolbar */}
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-slate-50/70 border border-slate-200/50 rounded-xl">
                                <div className="flex items-center gap-3">
                                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Set Global Carline:</span>
                                    <select
                                        value={globalCarline}
                                        onChange={(e) => setGlobalCarline(e.target.value)}
                                        className="h-8.5 min-w-[150px] px-2.5 border border-slate-200 rounded-lg text-xs bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#1D6F42]"
                                    >
                                        <option value="">Select Carline...</option>
                                        {carlines.map((c: any) => (
                                            <option key={c.id} value={c.id}>{c.code}</option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={applyGlobalCarline}
                                        disabled={!globalCarline}
                                        className="h-8.5 px-4 text-xs font-semibold text-white bg-[#1D6F42] hover:bg-[#185c38] rounded-lg transition-all shadow-sm active:scale-95 disabled:opacity-50 disabled:pointer-events-none"
                                    >
                                        Apply to All
                                    </button>
                                </div>
                                <span className="text-xs font-semibold text-slate-400">{rowsState.length} items remaining</span>
                            </div>

                            {errorMsg && <p className="text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-100 p-3 rounded-xl">{errorMsg}</p>}
                            {successMsg && <p className="text-xs font-semibold text-green-700 bg-green-50 border border-green-100 p-3 rounded-xl">{successMsg}</p>}

                            {/* Bulk Edit Grid */}
                            <div className="overflow-hidden rounded-xl border border-slate-200 shadow-sm bg-white">
                                <div className="max-h-[350px] overflow-auto scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
                                    <table className="w-full text-left text-xs border-collapse table-fixed min-w-[1050px]">
                                        <colgroup>
                                            <col className="w-[120px]" />
                                            <col className="w-[140px]" />
                                            <col className="w-[160px]" />
                                            <col className="w-[80px]" />
                                            <col className="w-[85px]" />
                                            <col className="w-[85px]" />
                                            <col className="w-[85px]" />
                                            <col className="w-[85px]" />
                                            <col className="w-[120px]" />
                                            <col className="w-[90px]" />
                                        </colgroup>
                                        <thead className="sticky top-0 bg-slate-50 border-b border-slate-200 z-10 text-center">
                                            <tr className="border-b border-slate-200">
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 align-middle">Assy Code *</th>
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 align-middle">Carline *</th>
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 align-middle">Assy Number</th>
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 align-middle">Level *</th>
                                                <th colSpan={2} className="px-3 py-2 font-bold text-slate-500 uppercase tracking-wider border-r border-b border-slate-200 align-middle">Standard Qty</th>
                                                <th colSpan={2} className="px-3 py-2 font-bold text-slate-500 uppercase tracking-wider border-r border-b border-slate-200 align-middle">Max Package Qty</th>
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 align-middle">Pattern</th>
                                                <th rowSpan={2} className="px-3 py-3 font-bold text-slate-500 uppercase tracking-wider align-middle">UMH *</th>
                                            </tr>
                                            <tr className="bg-slate-50/80 border-b border-slate-200">
                                                <th className="px-3 py-1.5 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200">SF</th>
                                                <th className="px-3 py-1.5 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200">AF</th>
                                                <th className="px-3 py-1.5 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200">SF</th>
                                                <th className="px-3 py-1.5 font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200">AF</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {rowsState.map((row, idx) => (
                                                <tr key={row.assy_number} className="hover:bg-slate-50/50 transition-colors">
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            required
                                                            value={row.assy_code}
                                                            onChange={(e) => updateRowField(idx, 'assy_code', e.target.value)}
                                                            className={`w-full h-8.5 px-2.5 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('assy_code') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <select
                                                            required
                                                            value={row.carline_id}
                                                            onChange={(e) => updateRowField(idx, 'carline_id', e.target.value)}
                                                            className={`w-full h-8.5 px-2 border rounded-lg text-xs bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('carline_id') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        >
                                                            <option value="">Select Carline...</option>
                                                            {carlines.map((c: any) => (
                                                                <option key={c.id} value={c.id}>{c.code}</option>
                                                             ))}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100 text-center">
                                                        <span className="font-mono font-semibold text-slate-700 bg-slate-50 border border-slate-200/60 px-2 py-0.5 rounded-md text-[11px] inline-block select-all">
                                                            {row.assy_number}
                                                        </span>
                                                        {row.error && (
                                                            <span className="mt-1 text-[10px] font-semibold text-rose-500 block truncate text-left" title={row.error}>
                                                                {row.error}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            required
                                                            value={row.level}
                                                            onChange={(e) => updateRowField(idx, 'level', e.target.value)}
                                                            className={`w-full h-8.5 px-2.5 border rounded-lg text-xs bg-white text-center focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('level') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            value={row.standard_sea_quantity}
                                                            onChange={(e) => updateRowField(idx, 'standard_sea_quantity', e.target.value)}
                                                            placeholder="0"
                                                            className={`w-full h-8.5 px-2 bg-white border rounded-lg text-xs text-right focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('standard_sea_quantity') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            value={row.standard_air_quantity}
                                                            onChange={(e) => updateRowField(idx, 'standard_air_quantity', e.target.value)}
                                                            placeholder="0"
                                                            className={`w-full h-8.5 px-2 bg-white border rounded-lg text-xs text-right focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('standard_air_quantity') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            value={row.max_quantity_sea}
                                                            onChange={(e) => updateRowField(idx, 'max_quantity_sea', e.target.value)}
                                                            placeholder="0"
                                                            className={`w-full h-8.5 px-2 bg-white border rounded-lg text-xs text-right focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('max_quantity_sea') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            value={row.max_quantity_air}
                                                            onChange={(e) => updateRowField(idx, 'max_quantity_air', e.target.value)}
                                                            placeholder="0"
                                                            className={`w-full h-8.5 px-2 bg-white border rounded-lg text-xs text-right focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('max_quantity_air') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5 border-r border-slate-100">
                                                        <input
                                                            value={row.pattern}
                                                            onChange={(e) => updateRowField(idx, 'pattern', e.target.value)}
                                                            placeholder="e.g. A, B"
                                                            className={`w-full h-8.5 px-2.5 border rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('pattern') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <input
                                                            required
                                                            type="number"
                                                            step="0.000001"
                                                            value={row.umh}
                                                            onChange={(e) => updateRowField(idx, 'umh', e.target.value)}
                                                            className={`w-full h-8.5 px-2 bg-white border rounded-lg text-xs text-right focus:outline-none focus:ring-1 focus:ring-[#1D6F42] focus:border-[#1D6F42] transition-colors ${row.error && row.error.includes('umh') ? 'border-rose-300 ring-rose-200 focus:ring-rose-500' : 'border-slate-200'}`}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer Buttons */}
                <div className="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4.5 sm:flex-row sm:justify-between">
                    <div>
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={disabled || isSaving}
                            className="inline-flex h-11 w-full sm:w-auto items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-3">
                        {rowsState.length > 0 && (
                            <button
                                type="button"
                                onClick={handleRegisterAll}
                                disabled={disabled || isSaving}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] hover:bg-[#185c38] px-5 text-sm font-semibold text-white transition shadow-sm disabled:opacity-50 active:scale-98"
                            >
                                {isSaving ? "Registering..." : "Register All Assembly"}
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onConfirm}
                            disabled={disabled || isSaving}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-emerald-600 text-emerald-700 hover:bg-emerald-50/50 px-5 text-sm font-semibold transition disabled:opacity-50"
                        >
                            <CloudArrowUpIcon className="h-4 w-4" />
                            {disabled ? "Uploading..." : "Continue upload"}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
