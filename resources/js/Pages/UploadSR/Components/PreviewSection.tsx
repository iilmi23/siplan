import ExcelPreview from "@/Components/ExcelPreview";
import {
    DocumentArrowUpIcon,
    TableCellsIcon,
    EyeIcon,
    ArrowsPointingInIcon,
    ArrowsPointingOutIcon,
} from "@heroicons/react/24/outline";

interface PreviewSectionProps {
    workbook: any;
    sheetName: string;
    selectedSheet: string;
    sheets: string[];
    zoom: number;
    setZoom: (zoom: number) => void;
    showGridlines: boolean;
    setShowGridlines: (val: boolean | ((prev: boolean) => boolean)) => void;
    fullscreen: boolean;
    toggleFullscreen: () => void;
    previewContainerRef: any;
}

export default function PreviewSection({
    workbook,
    sheetName,
    selectedSheet,
    sheets,
    zoom,
    setZoom,
    showGridlines,
    setShowGridlines,
    fullscreen,
    toggleFullscreen,
    previewContainerRef,
}: PreviewSectionProps) {
    const hasWorkbook = Boolean(workbook && sheets.length > 0);
    const hasPreview = Boolean(workbook && selectedSheet !== "" && sheetName);

    if (!hasWorkbook) {
        return (
            <section className="rounded-xl border border-dashed border-gray-200 bg-white p-8 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                    <DocumentArrowUpIcon className="h-8 w-8 text-gray-400" />
                </div>
                <h2 className="mt-4 text-base font-semibold text-gray-800">Preview will appear here</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Select an Excel SR file to view the worksheet contents before uploading.
                </p>
            </section>
        );
    }

    if (!hasPreview) {
        return (
            <section className="rounded-xl border border-dashed border-gray-200 bg-white p-8 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
                    <TableCellsIcon className="h-8 w-8 text-gray-400" />
                </div>
                <h2 className="mt-4 text-base font-semibold text-gray-800">Select worksheet for preview</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Once a sheet is selected, the Excel table will be displayed below for verification before processing.
                </p>
            </section>
        );
    }

    return (
        <section className="rounded-xl border border-gray-200 bg-white overflow-hidden animate-slideDown">
            <div className="flex flex-col gap-3 border-b border-gray-100 bg-gray-50/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1D6F42]/10 text-[#1D6F42]">
                        <EyeIcon className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">Excel Preview</h2>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Sheet: <span className="font-semibold text-[#1D6F42]">{sheetName}</span>
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <label className="flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-600">
                        Zoom
                        <select
                            value={zoom}
                            onChange={(event) => setZoom(Number(event.target.value))}
                            className="bg-transparent text-sm font-semibold text-gray-800 focus:outline-none"
                        >
                            <option value={75}>75%</option>
                            <option value={100}>100%</option>
                            <option value={125}>125%</option>
                            <option value={150}>150%</option>
                            <option value={200}>200%</option>
                        </select>
                    </label>

                    <button
                        type="button"
                        onClick={() => setShowGridlines((current) => !current)}
                        className={`inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-semibold transition-colors ${showGridlines
                            ? "border-[#1D6F42]/30 bg-[#1D6F42]/10 text-[#1D6F42]"
                            : "border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
                            }`}
                    >
                        <TableCellsIcon className="h-4 w-4" />
                        Grid
                    </button>

                    <button
                        type="button"
                        onClick={toggleFullscreen}
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50"
                    >
                        {fullscreen ? (
                            <ArrowsPointingInIcon className="h-4 w-4" />
                        ) : (
                            <ArrowsPointingOutIcon className="h-4 w-4" />
                        )}
                        {fullscreen ? "Exit" : "Fullscreen"}
                    </button>
                </div>
            </div>

            <div ref={previewContainerRef} className="bg-white">
                <ExcelPreview
                    workbook={workbook}
                    sheetName={sheetName}
                    zoom={zoom}
                    showGridlines={showGridlines}
                />
            </div>
        </section>
    );
}
