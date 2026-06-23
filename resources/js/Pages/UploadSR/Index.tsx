import AdminLayout from "@/Layouts/AdminLayout";
import { usePage, router } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import * as XLSX from "xlsx";
import {
    ArrowPathIcon,
    ChevronRightIcon,
    CloudArrowUpIcon,
    DocumentArrowUpIcon,
    TableCellsIcon,
    UserGroupIcon,
} from "@heroicons/react/24/outline";

// Import subcomponents
import StepPills from "./Components/StepPills";
import PreviewSection from "./Components/PreviewSection";
import UnknownAssyModal from "./Components/UnknownAssyModal";
import ValidationAlert from "./Components/ValidationAlert";
import Alert from "@/Components/Alert";

const ACCEPTED_EXTENSIONS = ["xlsx", "xls", "xlsm"];

export default function Index() {
    const { customers = [], carlines = [], flash = {}, errors = {} } = usePage<any>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const previewContainerRef = useRef<HTMLDivElement>(null);
    const timeoutRef = useRef<number | null>(null);

    const [selectedCustomer, setSelectedCustomer] = useState("");
    const [selectedPort, setSelectedPort] = useState("");
    const [file, setFile] = useState<File | null>(null);
    const [fileName, setFileName] = useState("");
    const [workbook, setWorkbook] = useState<XLSX.WorkBook | null>(null);
    const [sheets, setSheets] = useState<string[]>([]);
    const [selectedSheet, setSelectedSheet] = useState("");
    const [zoom, setZoom] = useState(100);
    const [showGridlines, setShowGridlines] = useState(true);
    const [fullscreen, setFullscreen] = useState(false);
    const [isReadingFile, setIsReadingFile] = useState(false);
    const [isCheckingPreview, setIsCheckingPreview] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [previewResult, setPreviewResult] = useState<any>(null);
    const [showAssyWarning, setShowAssyWarning] = useState(false);
    const [alert, setAlert] = useState({ show: false, type: "success", message: "" });

    const selectedCustomerData = useMemo(
        () => customers.find((customer: any) => String(customer.id) === String(selectedCustomer)),
        [customers, selectedCustomer]
    );

    const customerPorts = selectedCustomerData?.ports || [];
    const requiresPort = customerPorts.length > 0;
    const selectedSheetIndex = selectedSheet === "" ? null : Number(selectedSheet);
    const selectedSheetName = selectedSheetIndex !== null && !Number.isNaN(selectedSheetIndex)
        ? sheets[selectedSheetIndex]
        : "";
    const validationErrors = normalizeErrors(errors);
    const isBusy = isReadingFile || isCheckingPreview || isUploading;
    const canUpload = Boolean(
        selectedCustomer &&
        file &&
        selectedSheet !== "" &&
        !Number.isNaN(selectedSheetIndex) &&
        (!requiresPort || selectedPort) &&
        !isBusy
    );
    const customerStepDone = Boolean(selectedCustomer && (!requiresPort || selectedPort));
    const fileStepDone = Boolean(file && selectedSheet !== "" && !Number.isNaN(selectedSheetIndex));
    const previewStepDone = Boolean(previewResult);

    useEffect(() => {
        const successMessage = flash?.success;
        const warningMessage = flash?.warning;
        const errorMessage = flash?.error;
        const message = successMessage || warningMessage || errorMessage;

        if (!message) return;

        notify(successMessage ? "success" : warningMessage ? "warning" : "error", message);
    }, [flash?.success, flash?.warning, flash?.error]);

    useEffect(() => {
        const handleFullscreenChange = () => {
            setFullscreen(Boolean(document.fullscreenElement));
        };

        document.addEventListener("fullscreenchange", handleFullscreenChange);
        return () => document.removeEventListener("fullscreenchange", handleFullscreenChange);
    }, []);

    useEffect(() => {
        return () => {
            if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
        };
    }, []);

    const notify = (type: string, message: string) => {
        setAlert({ show: true, type, message });
        if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
        timeoutRef.current = window.setTimeout(() => {
            setAlert((current) => ({ ...current, show: false }));
        }, type === "error" ? 5000 : 4000);
    };

    const resetFileState = () => {
        setFile(null);
        setFileName("");
        setWorkbook(null);
        setSheets([]);
        setSelectedSheet("");
        setPreviewResult(null);
        setShowAssyWarning(false);

        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    };

    const resetForm = () => {
        setSelectedCustomer("");
        setSelectedPort("");
        setZoom(100);
        setShowGridlines(true);
        resetFileState();
    };

    const handleCustomerChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        setSelectedCustomer(event.target.value);
        setSelectedPort("");
        setPreviewResult(null);
        setShowAssyWarning(false);
    };

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const uploadedFile = event.target.files?.[0] ?? null;
        resetFileState();

        if (!uploadedFile) return;

        const extension = uploadedFile.name.split(".").pop()?.toLowerCase();
        if (!extension || !ACCEPTED_EXTENSIONS.includes(extension)) {
            notify("error", "Unsupported file format. Please use .xlsx, .xls, or .xlsm.");
            return;
        }

        setFile(uploadedFile);
        setFileName(uploadedFile.name);
        setIsReadingFile(true);

        const reader = new FileReader();

        reader.onload = (readerEvent) => {
            try {
                const result = readerEvent.target?.result;
                if (!(result instanceof ArrayBuffer)) {
                    throw new Error("Invalid file data.");
                }

                const data = new Uint8Array(result);
                const parsedWorkbook = XLSX.read(data, {
                    type: "array",
                });

                setWorkbook(parsedWorkbook);
                setSheets(parsedWorkbook.SheetNames || []);
                setSelectedSheet(parsedWorkbook.SheetNames?.length === 1 ? "0" : "");

                notify("success", "File read successfully. Choose a sheet to view preview.");
            } catch (error) {
                console.error("Read Excel error:", error);
                resetFileState();
                notify("error", "Could not read Excel file. Please ensure it is not corrupted and is in a valid format.");
            } finally {
                setIsReadingFile(false);
            }
        };

        reader.onerror = () => {
            setIsReadingFile(false);
            resetFileState();
            notify("error", "Failed to read file. Please select another file.");
        };

        reader.readAsArrayBuffer(uploadedFile);
    };

    const buildSrFormData = () => {
        const formData = new FormData();
        formData.append("customer", selectedCustomer);
        if (selectedPort) {
            formData.append("port", selectedPort);
        }
        if (file) {
            formData.append("file", file);
        }
        formData.append("sheet", String(selectedSheetIndex));

        return formData;
    };

    const validateUploadInput = () => {
        if (!selectedCustomer) {
            notify("error", "Please select a customer first.");
            return false;
        }

        if (requiresPort && !selectedPort) {
            notify("error", `Port is required for customer ${selectedCustomerData?.code || selectedCustomerData?.name}.`);
            return false;
        }

        if (!file) {
            notify("error", "Please choose an Excel file first.");
            return false;
        }

        if (selectedSheet === "" || Number.isNaN(selectedSheetIndex)) {
            notify("error", "Please select the worksheet to upload.");
            return false;
        }

        return true;
    };

    const handleSubmit = async () => {
        if (!validateUploadInput()) {
            return;
        }

        setIsCheckingPreview(true);

        try {
            const response = await fetch(window.route("sr.preview"), {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "Accept": "application/json",
                },
                body: buildSrFormData(),
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload?.success) {
                throw new Error(payload?.error || "Failed to process SR preview.");
            }

            const result = payload.data || {};
            setPreviewResult(result);

            if (result.has_unknown_assy_numbers) {
                setShowAssyWarning(true);
                notify("warning", "Some Assembly numbers are missing from Master Assembly. Review the list before proceeding.");
                return;
            }

            uploadSr();
        } catch (error: any) {
            notify("error", error.message || "Failed to process SR preview.");
        } finally {
            setIsCheckingPreview(false);
        }
    };

    const handleAssyRegistered = (registeredAssy: string) => {
        setPreviewResult((prev: any) => {
            if (!prev) return null;
            const updatedUnknown = (prev.unknown_assy_numbers || []).filter(
                (num: string) => num !== registeredAssy
            );
            const hasUnknown = updatedUnknown.length > 0;

            if (!hasUnknown) {
                setShowAssyWarning(false);
                notify("success", "All new Assembly numbers registered successfully. Continuing SR upload...");
                setTimeout(() => {
                    uploadSr();
                }, 100);
            }

            return {
                ...prev,
                unknown_assy_numbers: updatedUnknown,
                has_unknown_assy_numbers: hasUnknown,
            };
        });
    };

    const uploadSr = () => {
        if (!validateUploadInput()) {
            return;
        }

        setShowAssyWarning(false);
        setIsUploading(true);

        const uploadTimeout = window.setTimeout(() => {
            notify("warning", "Upload is still processing. Large files may take several minutes.");
        }, 120000);

        router.post(window.route("sr.upload"), buildSrFormData(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const serverFlash = page?.props?.flash || {};
                if (serverFlash?.error || serverFlash?.warning) {
                    return;
                }

                resetForm();
            },
            onError: (serverErrors) => {
                notify("error", formatServerErrors(serverErrors));
            },
            onFinish: () => {
                window.clearTimeout(uploadTimeout);
                setIsUploading(false);
            },
        });
    };

    const toggleFullscreen = async () => {
        try {
            if (!document.fullscreenElement && previewContainerRef.current) {
                await previewContainerRef.current.requestFullscreen();
                return;
            }

            if (document.fullscreenElement) {
                await document.exitFullscreen();
            }
        } catch (error) {
            console.error("Fullscreen error:", error);
            notify("error", "Fullscreen mode is not supported by this browser.");
        }
    };

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <nav className="flex items-center gap-2 mb-4 text-sm" aria-label="Breadcrumb">
                    <span className="text-gray-600">Shipping Release</span>
                    <ChevronRightIcon className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-900 font-medium">Upload SR</span>
                </nav>

                {alert.show && alert.message && (
                    <Alert
                        type={alert.type}
                        message={alert.message}
                        onClose={() => setAlert((current) => ({ ...current, show: false }))}
                    />
                )}

                {validationErrors.length > 0 && (
                    <ValidationAlert errors={validationErrors} />
                )}

                {(isCheckingPreview || isUploading) && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/20">
                        <div className="flex items-center gap-3 rounded-xl bg-white p-6 shadow-xl">
                            <div className="h-5 w-5 animate-spin rounded-full border-2 border-[#1D6F42] border-t-transparent" />
                            <span className="text-sm font-medium text-gray-700">
                                {isCheckingPreview ? "Checking master assy..." : "Uploading SR..."}
                            </span>
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div className="p-6 pb-4 border-b border-gray-100">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
                                    Upload Shipping Release
                                </h1>
                                <p className="mt-1 text-sm text-gray-500">
                                    Select customer, upload SR file, select worksheet, then review preview before saving data.
                                </p>
                            </div>

                            <div className="hidden h-12 w-12 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42] sm:flex">
                                <DocumentArrowUpIcon className="h-6 w-6" />
                            </div>
                        </div>
                    </div>

                    <div className="p-6 space-y-6">
                        <section className="rounded-xl border border-gray-200 bg-white overflow-hidden">
                            <div className="border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:px-6">
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42]">
                                            <CloudArrowUpIcon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h2 className="text-sm font-semibold text-gray-900">Setup upload</h2>
                                            <p className="mt-0.5 text-xs leading-5 text-gray-500">
                                                Complete from left to right. The SR file will be validated before final upload.
                                            </p>
                                        </div>
                                    </div>

                                    <StepPills
                                        customerStepDone={customerStepDone}
                                        fileStepDone={fileStepDone}
                                        previewStepDone={previewStepDone}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <label className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <UserGroupIcon className="h-4 w-4 text-gray-400" />
                                        Customer <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={selectedCustomer}
                                        onChange={handleCustomerChange}
                                        className={inputClass}
                                    >
                                        <option value="">Select Customer</option>
                                        {customers.map((customer: any) => (
                                            <option key={customer.id} value={customer.id}>
                                                {customer.code}
                                                {customer.name ? ` - ${customer.name}` : ""}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <UserGroupIcon className="h-4 w-4 text-gray-400" />
                                        Port {requiresPort && <span className="text-red-500">*</span>}
                                    </label>
                                    <select
                                        value={selectedPort}
                                        onChange={(event) => setSelectedPort(event.target.value)}
                                        disabled={!selectedCustomer || customerPorts.length === 0}
                                        className={`${inputClass} disabled:bg-gray-50 disabled:text-gray-400`}
                                    >
                                        {!selectedCustomer ? (
                                            <option value="">Select customer first</option>
                                        ) : customerPorts.length === 0 ? (
                                            <option value="">No port required</option>
                                        ) : (
                                            <>
                                                <option value="">Select Port</option>
                                                {customerPorts.map((port: any) => (
                                                    <option key={port.id} value={port.id}>
                                                        {port.name}
                                                    </option>
                                                ))}
                                            </>
                                        )}
                                    </select>
                                    {requiresPort && !selectedPort && (
                                        <p className="mt-1 text-xs text-amber-600">
                                            This customer requires a port selection before uploading.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <DocumentArrowUpIcon className="h-4 w-4 text-gray-400" />
                                        Excel File <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        ref={fileInputRef}
                                        id="sr-file-upload"
                                        type="file"
                                        accept=".xlsx,.xls,.xlsm"
                                        onChange={handleFileChange}
                                        className="hidden"
                                    />
                                    <label
                                        htmlFor="sr-file-upload"
                                        className="flex h-11 w-full cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-700 transition-all hover:bg-gray-50 focus-within:border-[#1D6F42]"
                                    >
                                        <DocumentArrowUpIcon className="h-5 w-5 shrink-0 text-gray-400" />
                                        <span className={`truncate ${fileName ? "font-medium text-gray-800" : "text-gray-400"}`}>
                                            {fileName || "Choose .xlsx, .xls, or .xlsm file"}
                                        </span>
                                    </label>
                                    {isReadingFile && (
                                        <p className="mt-2 text-xs text-gray-500">Reading workbook...</p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <TableCellsIcon className="h-4 w-4 text-gray-400" />
                                        Worksheet <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={selectedSheet}
                                        onChange={(event) => setSelectedSheet(event.target.value)}
                                        disabled={sheets.length === 0 || isReadingFile}
                                        className={`${inputClass} disabled:bg-gray-50 disabled:text-gray-400`}
                                    >
                                        {sheets.length === 0 ? (
                                            <option value="">Select file first</option>
                                        ) : (
                                            <>
                                                <option value="">Select Sheet</option>
                                                {sheets.map((sheet, index) => (
                                                    <option key={sheet} value={index}>
                                                        {sheet}
                                                    </option>
                                                ))}
                                            </>
                                        )}
                                    </select>
                                    {selectedCustomerData?.code?.toUpperCase() === "YC" && (
                                        <p className="mt-1 text-xs text-gray-500">
                                            For YC files, only the selected sheet will be processed.
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-col gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={!canUpload}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1D6F42] px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#185c38] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <CloudArrowUpIcon className="h-4 w-4" />
                                        {isCheckingPreview ? "Checking..." : isUploading ? "Uploading..." : "Upload SR"}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={resetForm}
                                        disabled={isBusy}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        <ArrowPathIcon className="h-4 w-4" />
                                        Reset
                                    </button>
                                </div>

                                <p className="text-xs text-gray-500">
                                    Supported customers: TYC, YNA, SAI, YC, or customers with active SR templates.
                                </p>
                            </div>
                        </section>

                        <PreviewSection
                            workbook={workbook}
                            sheetName={selectedSheetName}
                            selectedSheet={selectedSheet}
                            sheets={sheets}
                            zoom={zoom}
                            setZoom={setZoom}
                            showGridlines={showGridlines}
                            setShowGridlines={setShowGridlines}
                            fullscreen={fullscreen}
                            toggleFullscreen={toggleFullscreen}
                            previewContainerRef={previewContainerRef}
                        />
                    </div>
                </div>
            </div>

            {showAssyWarning && previewResult && (
                <UnknownAssyModal
                    preview={previewResult}
                    carlines={carlines}
                    onCancel={() => setShowAssyWarning(false)}
                    onConfirm={uploadSr}
                    onRegistered={handleAssyRegistered}
                    disabled={isUploading}
                />
            )}

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

function normalizeErrors(errors: any) {
    if (!errors || typeof errors !== "object") return [];

    return Object.values(errors)
        .flat()
        .filter(Boolean)
        .map((error) => String(error));
}

function formatServerErrors(serverErrors: any) {
    const normalized = normalizeErrors(serverErrors);
    if (normalized.length === 0) {
        return "Upload failed. Please check the data and try again.";
    }

    return normalized.join("\n");
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

const inputClass =
    "w-full h-11 px-4 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] bg-white transition-all";
