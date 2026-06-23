import { useRef, useState } from "react";
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    CloudArrowUpIcon,
    DocumentArrowUpIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    TableCellsIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

const REQUIRED_COLUMNS = [
    { key: "A", label: "code", example: "495D" },
];

interface ImportModalProps {
    onClose: () => void;
    onSuccess: (message: string) => void;
}

export default function ImportModal({ onClose, onSuccess }: ImportModalProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [sheets, setSheets] = useState<string[]>([]);
    const [selectedSheet, setSelectedSheet] = useState<string>("");
    const [previewData, setPreviewData] = useState<any[]>([]);
    const [showPreview, setShowPreview] = useState<boolean>(false);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string>("");
    const [showError, setShowError] = useState<boolean>(false);
    const [showImportConfirm, setShowImportConfirm] = useState<boolean>(false);

    const previewHeaders = Object.keys(previewData[0] || {});
    const canPreview = Boolean(selectedFile && selectedSheet && !isLoading);
    const canImport = Boolean(selectedFile && selectedSheet && showPreview && previewData.length > 0 && !isLoading);

    const notifyError = (message: string) => {
        setErrorMessage(message);
        setShowError(true);
        setTimeout(() => setShowError(false), 4000);
    };

    const getCsrfToken = () => {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error("CSRF token not found. Please refresh the page.");
        }
        return csrfToken;
    };

    const resetFileState = () => {
        setSelectedFile(null);
        setSheets([]);
        setSelectedSheet("");
        setPreviewData([]);
        setShowPreview(false);
    };

    const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        if (!file) {
            resetFileState();
            return;
        }

        const extension = file.name.split(".").pop()?.toLowerCase();

        if (!["xlsx", "xls", "csv"].includes(extension || "")) {
            notifyError("Please select a valid Excel file (.xlsx, .xls, or .csv).");
            event.target.value = "";
            resetFileState();
            return;
        }

        setSelectedFile(file);
        setSelectedSheet("");
        setSheets([]);
        setPreviewData([]);
        setShowPreview(false);

        const formData = new FormData();
        formData.append("file", file);

        setIsLoading(true);
        try {
            const response = await fetch(route("carline.getSheets"), {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
            });

            const result = await response.json();

            if (result.success) {
                setSheets(result.sheets || []);
                setSelectedSheet(result.sheets?.[0] || "");
            } else {
                notifyError(result.message || "Failed to read Excel file.");
            }
        } catch (error: any) {
            console.error("Error:", error);
            notifyError(error.message || "Failed to read Excel file. Please check the format.");
        } finally {
            setIsLoading(false);
        }
    };

    const handlePreviewSheet = async () => {
        if (!selectedFile || !selectedSheet) {
            notifyError("Please select file and sheet first.");
            return;
        }

        const formData = new FormData();
        formData.append("file", selectedFile);
        formData.append("sheet", selectedSheet);

        setIsLoading(true);
        try {
            const response = await fetch(route("carline.previewSheet"), {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
            });

            const result = await response.json();

            if (result.success) {
                setPreviewData(result.data || []);
                setShowPreview(true);
            } else {
                notifyError(result.message || "Failed to preview sheet.");
            }
        } catch (error: any) {
            console.error("Error:", error);
            notifyError(error.message || "Failed to preview sheet. Please try again.");
        } finally {
            setIsLoading(false);
        }
    };

    const handleImportExcel = () => {
        if (!selectedFile || !selectedSheet) {
            notifyError("Please select file and sheet first.");
            return;
        }

        if (!showPreview || previewData.length === 0) {
            notifyError("Please preview the data before importing.");
            return;
        }

        setShowImportConfirm(true);
    };

    const confirmImportExcel = async () => {
        setShowImportConfirm(false);
        setIsLoading(true);

        const formData = new FormData();
        formData.append("file", selectedFile!);
        formData.append("sheet", selectedSheet);

        try {
            const response = await fetch(route("carline.import"), {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
            });

            const result = await response.json();

            if (result.success) {
                onSuccess(result.message || "Carline data imported successfully.");
            } else {
                notifyError(result.message || "Failed to import carlines.");
            }
        } catch (error: any) {
            console.error("Import failed:", error);
            notifyError(error.message || "Failed to import carlines. Please try again.");
        } finally {
            setIsLoading(false);
        }
    };

    const resetForm = () => {
        setShowImportConfirm(false);
        resetFileState();

        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-xl flex flex-col relative">
                {/* Close Button */}
                <button
                    onClick={onClose}
                    className="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded-lg hover:bg-gray-100"
                    title="Close Modal"
                >
                    <XMarkIcon className="h-5 w-5" />
                </button>

                <div className="p-6 pb-4 border-b border-gray-100">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42]">
                            <DocumentArrowUpIcon className="h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold text-gray-900 tracking-tight">
                                Import Carline Data
                            </h1>
                            <p className="mt-0.5 text-sm text-gray-500">
                                Upload Excel, select sheet, preview data, then import carline codes.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="p-6 space-y-6">
                    {/* Error Alert inside Modal */}
                    {showError && errorMessage && (
                        <div className="animate-slideDown">
                            <div className="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-red-500 bg-white p-4 shadow-sm">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">
                                    <ExclamationTriangleIcon className="h-5 w-5" />
                                </div>
                                <p className="flex-1 text-sm font-medium text-gray-800">{errorMessage}</p>
                                <button
                                    type="button"
                                    onClick={() => setShowError(false)}
                                    className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                                >
                                    <XMarkIcon className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    <FormatGuide />

                    <section className="rounded-xl border border-gray-200 bg-white overflow-hidden">
                        <div className="border-b border-gray-100 bg-gray-50/70 px-5 py-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42]">
                                    <CloudArrowUpIcon className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-gray-900">Upload Setup</h2>
                                    <p className="text-xs text-gray-500">Choose file and worksheet before importing.</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-5 p-5 lg:grid-cols-2">
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-gray-700">
                                    Excel File <span className="text-red-500">*</span>
                                </label>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={handleFileChange}
                                    className="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 transition-all file:mr-3 file:rounded-lg file:border-0 file:bg-[#1D6F42] file:px-4 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-[#185c38] focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
                                />
                                {selectedFile && (
                                    <div className="mt-3 flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                                        <CheckCircleIcon className="h-4 w-4 shrink-0" />
                                        <span className="truncate">
                                            Selected: <span className="font-semibold">{selectedFile.name}</span>
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-gray-700">
                                    Worksheet <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={selectedSheet}
                                    onChange={(event) => {
                                        setSelectedSheet(event.target.value);
                                        setShowPreview(false);
                                        setPreviewData([]);
                                    }}
                                    disabled={sheets.length === 0}
                                    className={`${inputClass} disabled:bg-gray-50 disabled:text-gray-400`}
                                >
                                    {sheets.length === 0 ? (
                                        <option value="">Select file first</option>
                                    ) : (
                                        sheets.map((sheet, index) => (
                                            <option key={index} value={sheet}>
                                                {sheet}
                                            </option>
                                        ))
                                    )}
                                </select>
                                <p className="mt-1 text-xs text-gray-500">Preview reads the selected worksheet only.</p>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:flex-row sm:items-center">
                            <button
                                type="button"
                                onClick={handlePreviewSheet}
                                disabled={!canPreview}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition-all hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 active:scale-[0.98] shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <EyeIcon className="h-4 w-4" />
                                Preview Data
                            </button>
                            <button
                                type="button"
                                onClick={handleImportExcel}
                                disabled={!canImport}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#185c38] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <CloudArrowUpIcon className="h-4 w-4" />
                                Upload Carline
                            </button>
                            <button
                                type="button"
                                onClick={resetForm}
                                disabled={isLoading}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition-all hover:bg-gray-50 hover:text-red-600 hover:border-red-200 active:scale-[0.98] shadow-sm disabled:opacity-50"
                            >
                                <ArrowPathIcon className="h-4 w-4" />
                                Reset
                            </button>
                        </div>
                    </section>

                    <PreviewTable headers={previewHeaders} rows={previewData} showPreview={showPreview} />
                </div>

                <div className="p-6 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>

            {/* Loading Overlay inside Modal */}
            {isLoading && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/20">
                    <div className="flex items-center gap-3 rounded-xl bg-white p-6 shadow-xl">
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-[#1D6F42] border-t-transparent" />
                        <span className="text-sm font-medium text-gray-700">Processing...</span>
                    </div>
                </div>
            )}

            {/* Confirm Dialog */}
            {showImportConfirm && (
                <ConfirmImportDialog
                    fileName={selectedFile?.name}
                    sheetName={selectedSheet}
                    rowsCount={previewData.length}
                    onCancel={() => setShowImportConfirm(false)}
                    onConfirm={confirmImportExcel}
                />
            )}
        </div>
    );
}

function ConfirmImportDialog({ fileName, sheetName, rowsCount, onCancel, onConfirm }: { fileName?: string; sheetName: string; rowsCount: number; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 px-4">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl">
                <div className="flex items-start gap-3 border-b border-gray-100 p-5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42]">
                        <ExclamationTriangleIcon className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">Confirm Import</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            This will import the previewed carline data into the system.
                        </p>
                    </div>
                </div>

                <div className="space-y-3 p-5 text-sm">
                    <div className="rounded-xl bg-gray-50 px-4 py-3">
                        <p className="text-xs font-medium text-gray-400">File</p>
                        <p className="mt-0.5 truncate font-semibold text-gray-800">{fileName || "-"}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl bg-gray-50 px-4 py-3">
                            <p className="text-xs font-medium text-gray-400">Sheet</p>
                            <p className="mt-0.5 truncate font-semibold text-gray-800">{sheetName || "-"}</p>
                        </div>
                        <div className="rounded-xl bg-gray-50 px-4 py-3">
                            <p className="text-xs font-medium text-gray-400">Preview Rows</p>
                            <p className="mt-0.5 font-semibold text-gray-800">{rowsCount}</p>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col-reverse gap-3 border-t border-gray-100 bg-gray-50/70 p-5 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex h-10 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#185c38]"
                    >
                        <CloudArrowUpIcon className="h-4 w-4" />
                        Import Now
                    </button>
                </div>
            </div>
        </div>
    );
}

function FormatGuide() {
    return (
        <div className="rounded-xl border border-emerald-100 bg-[#1D6F42]/5 p-4">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#1D6F42] ring-1 ring-emerald-100">
                        <DocumentArrowUpIcon className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">Excel Format Requirements</h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Use a header row with a column named code. Duplicate carline codes will be skipped by the importer.
                        </p>
                        <div className="mt-3">
                            <a
                                href={route("carline.download-template")}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-[#1D6F42]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#1D6F42] shadow-sm transition-all hover:bg-[#1D6F42]/10 active:scale-[0.98]"
                            >
                                <ArrowDownTrayIcon className="h-3.5 w-3.5" />
                                Download Template
                            </a>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {REQUIRED_COLUMNS.map((column) => (
                        <div key={column.key} className="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                            <p className="text-[11px] font-semibold uppercase text-[#1D6F42]">Column {column.key}</p>
                            <p className="mt-0.5 truncate text-xs font-semibold text-gray-800">{column.label}</p>
                            <p className="mt-1 truncate text-[11px] text-gray-500">{column.example}</p>
                        </div>
                    ))}
                    <div className="rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <p className="text-[11px] font-semibold uppercase text-gray-500">Format</p>
                        <p className="mt-0.5 truncate text-xs font-semibold text-gray-800">.xlsx, .xls, .csv</p>
                        <p className="mt-1 truncate text-[11px] text-gray-500">Header row required</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PreviewTable({ headers, rows, showPreview }: { headers: string[]; rows: any[]; showPreview: boolean }) {
    if (!showPreview) {
        return (
            <section className="rounded-xl border border-gray-200 bg-white p-10 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                    <TableCellsIcon className="h-8 w-8 text-gray-400" />
                </div>
                <h2 className="mt-4 text-base font-semibold text-gray-800">Preview not available yet</h2>
                <p className="mt-1 text-sm text-gray-500">Select file and worksheet, then click Preview Data.</p>
            </section>
        );
    }

    return (
        <section className="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div className="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-sm font-semibold text-gray-900">Data Preview</h2>
                    <p className="mt-0.5 text-xs text-gray-500">Showing all {rows.length} rows from the selected sheet.</p>
                </div>
                <span className="w-fit rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    {rows.length} rows displayed
                </span>
            </div>

            <div className="overflow-x-auto max-h-[300px] overflow-y-auto">
                <table className="w-full min-w-[520px]">
                    <thead>
                        <tr className="bg-gray-100/80 border-b border-gray-200">
                            {headers.length > 0 ? headers.map((header, index) => (
                                <th key={`${header}-${index}`} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                    {header}
                                </th>
                            )) : (
                                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                    Data
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.length > 0 ? rows.map((row, rowIndex) => (
                            <tr key={rowIndex} className="hover:bg-gray-50/70">
                                {headers.map((header, colIndex) => (
                                    <td key={`${rowIndex}-${colIndex}`} className="px-4 py-3 text-sm text-gray-600">
                                        <span className="block max-w-[220px] truncate" title={String(row[header] ?? "")}>
                                            {formatCell(row[header])}
                                        </span>
                                    </td>
                                ))}
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={Math.max(headers.length, 1)} className="px-4 py-10 text-center text-sm text-gray-400">
                                    No data rows found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function formatCell(value: any) {
    if (value === null || value === undefined || value === "") {
        return "-";
    }
    return String(value);
}

const inputClass =
    "w-full h-11 px-4 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] bg-white transition-all";
