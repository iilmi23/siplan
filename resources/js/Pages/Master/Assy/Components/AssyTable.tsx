import { Assy } from "@/types/models";
import { TableCellsIcon, PencilIcon, TrashIcon, ArrowPathIcon } from "@heroicons/react/24/outline";

interface AssyTableProps {
    assyRows: Assy[];
    currentPage: number;
    perPage: number;
    isProcessing: boolean;
    hasActiveFilters: boolean;
    onToggleStatus: (assyItem: Assy) => void;
    onEdit: (assyItem: Assy) => void;
    onDelete: (assyItem: Assy) => void;
    onResetFilters: () => void;
}

const formatDecimal = (value: any) => {
    if (value === null || value === undefined || value === "") return "-";

    const number = Number(value);
    if (!Number.isFinite(number)) return value;

    return number.toLocaleString("en-US", {
        maximumFractionDigits: 4,
    });
};

const formatInteger = (value: any) => {
    if (value === null || value === undefined || value === "") return "-";

    const number = Number(value);
    if (!Number.isFinite(number)) return value;

    return number.toLocaleString("en-US");
};

export default function AssyTable({
    assyRows,
    currentPage,
    perPage,
    isProcessing,
    hasActiveFilters,
    onToggleStatus,
    onEdit,
    onDelete,
    onResetFilters,
}: AssyTableProps) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-[1070px] w-full table-fixed">
                <colgroup>
                    <col className="w-[36px]" />
                    <col className="w-[90px]" />
                    <col className="w-[120px]" />
                    <col className="w-[130px]" />
                    <col className="w-[70px]" />
                    <col className="w-[65px]" />
                    <col className="w-[65px]" />
                    <col className="w-[90px]" />
                    <col className="w-[90px]" />
                    <col className="w-[75px]" />
                    <col className="w-[75px]" />
                    <col className="w-[80px]" />
                    <col className="w-[84px]" />
                </colgroup>
                <thead>
                    <tr className="bg-gray-100/80 border-b border-gray-200">
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            No
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Assy Code
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Carline
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Assy Number
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Level
                        </th>
                        <th colSpan={2} className="px-2 py-2 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-b border-gray-200 align-middle">
                            Standard Qty
                        </th>
                        <th colSpan={2} className="px-2 py-2 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-b border-gray-200 align-middle">
                            Max Package Qty
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Pattern
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            UMH
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide border-r border-gray-200 align-middle">
                            Status
                        </th>
                        <th rowSpan={2} className="px-2 py-3 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide align-middle">
                            Actions
                        </th>
                    </tr>
                    <tr className="bg-gray-50 border-b border-gray-200">
                        <th className="px-2 py-1.5 text-center text-[10px] font-bold text-gray-600 uppercase tracking-wide border-r border-gray-200">
                            SF
                        </th>
                        <th className="px-2 py-1.5 text-center text-[10px] font-bold text-gray-600 uppercase tracking-wide border-r border-gray-200">
                            AF
                        </th>
                        <th className="px-2 py-1.5 text-center text-[10px] font-bold text-gray-600 uppercase tracking-wide border-r border-gray-200">
                            SF
                        </th>
                        <th className="px-2 py-1.5 text-center text-[10px] font-bold text-gray-600 uppercase tracking-wide border-r border-gray-200">
                            AF
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {assyRows.length > 0 ? (
                        assyRows.map((assyItem, index) => {
                            const rowNumber = (currentPage - 1) * perPage + index + 1;
                            const isActiveRow = Boolean(assyItem.is_active);
                            const carlineLabel = assyItem.carline?.code || "-";

                            return (
                                <tr key={assyItem.id} className="hover:bg-gray-50/80 transition-colors">
                                    <td className="px-2 py-3 text-center text-xs text-gray-500 font-medium tabular-nums border-r border-gray-100">
                                        {rowNumber}
                                    </td>
                                    <td className="px-2 py-3 text-center text-xs text-gray-700 border-r border-gray-100 overflow-hidden">
                                        <span className="block truncate font-mono font-semibold" title={assyItem.assy_code || "-"}>
                                            {assyItem.assy_code || "-"}
                                        </span>
                                    </td>
                                    <td className="px-2 py-3 text-center border-r border-gray-100 overflow-hidden">
                                        <span className="inline-flex max-w-full px-1.5 py-1 rounded-lg bg-green-50 text-[#1D6F42] text-xs font-medium border border-green-100">
                                            <span className="truncate" title={carlineLabel}>{carlineLabel}</span>
                                        </span>
                                    </td>
                                    <td className="px-2 py-3 text-center text-xs text-gray-900 border-r border-gray-100 overflow-hidden">
                                        <span className="block truncate font-mono font-semibold" title={assyItem.assy_number || "-"}>
                                            {assyItem.assy_number || "-"}
                                        </span>
                                    </td>
                                    <td className="px-2 py-3 text-center text-xs text-gray-600 border-r border-gray-100 overflow-hidden">
                                        <span className="inline-flex max-w-full justify-center rounded-lg bg-gray-100 px-1.5 py-1 text-xs font-semibold text-gray-700">
                                            <span className="truncate">
                                                {assyItem.level || "-"}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="px-2 py-3 text-center font-mono text-xs text-gray-700 border-r border-gray-100 truncate">
                                        {formatInteger(assyItem.standard_sea_quantity)}
                                    </td>
                                    <td className="px-2 py-3 text-center font-mono text-xs text-gray-700 border-r border-gray-100 truncate">
                                        {formatInteger(assyItem.standard_air_quantity)}
                                    </td>
                                    <td className="px-2 py-3 text-center font-mono text-xs text-gray-700 border-r border-gray-100 truncate">
                                        {formatInteger(assyItem.max_quantity_sea)}
                                    </td>
                                    <td className="px-2 py-3 text-center font-mono text-xs text-gray-700 border-r border-gray-100 truncate">
                                        {formatInteger(assyItem.max_quantity_air)}
                                    </td>
                                    <td className="px-2 py-3 text-center text-xs text-gray-600 border-r border-gray-100 overflow-hidden">
                                        <span className="block truncate" title={assyItem.pattern || "-"}>
                                            {assyItem.pattern || "-"}
                                        </span>
                                    </td>
                                    <td className="px-2 py-3 text-center font-mono text-xs text-gray-700 border-r border-gray-100 truncate">
                                        {formatDecimal(assyItem.umh)}
                                    </td>
                                    <td className="px-2 py-3 text-center border-r border-gray-100">
                                        <button
                                            type="button"
                                            onClick={() => onToggleStatus(assyItem)}
                                            disabled={isProcessing}
                                            className={`inline-flex w-full max-w-[72px] items-center justify-center rounded-full px-1.5 py-1 text-[11px] font-semibold transition-colors ${isActiveRow
                                                ? "bg-green-50 text-[#1D6F42] hover:bg-green-100"
                                                : "bg-gray-100 text-gray-500 hover:bg-gray-200"
                                                }`}
                                        >
                                            {isActiveRow ? "Active" : "Inactive"}
                                        </button>
                                    </td>
                                    <td className="px-2 py-3">
                                        <div className="flex items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => onEdit(assyItem)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-colors"
                                                title="Edit"
                                                aria-label={`Edit ${assyItem.assy_number || "assy"}`}
                                            >
                                                <PencilIcon className="w-4 h-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => onDelete(assyItem)}
                                                disabled={isProcessing}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 bg-white border border-red-200 hover:bg-red-50 hover:border-red-300 transition-colors disabled:opacity-60"
                                                title="Delete"
                                                aria-label={`Delete ${assyItem.assy_number || "assy"}`}
                                            >
                                                <TrashIcon className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan={14} className="py-16 text-center">
                                <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-gray-200">
                                    <TableCellsIcon className="w-8 h-8 text-gray-400" />
                                </div>
                                <h3 className="text-base font-semibold text-gray-800 mb-1">No assy data found</h3>
                                <p className="text-sm text-gray-400 mt-1">
                                    {hasActiveFilters
                                        ? "No data matched the current filters."
                                        : "Add a new assy or import data from Excel to start."}
                                </p>
                                {hasActiveFilters && (
                                    <button
                                        type="button"
                                        onClick={onResetFilters}
                                        className="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors"
                                    >
                                        <ArrowPathIcon className="w-4 h-4" />
                                        Reset filter
                                    </button>
                                )}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
