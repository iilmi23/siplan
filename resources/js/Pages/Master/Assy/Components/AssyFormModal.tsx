import { SyntheticEvent } from "react";
import { Carline } from "@/types/models";
import { XMarkIcon } from "@heroicons/react/24/outline";

interface AssyFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    carlines: Carline[];
    submitLabel: string;
    data: any;
    setData: (key: any, value?: any) => void;
    errors: Record<string, string>;
    onSubmit: (e: SyntheticEvent) => void;
    processing: boolean;
    isEdit?: boolean;
}

export default function AssyFormModal({
    isOpen,
    onClose,
    title,
    carlines,
    submitLabel,
    data,
    setData,
    errors,
    onSubmit,
    processing,
    isEdit = false,
}: AssyFormModalProps) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div
                    className="fixed inset-0 bg-gray-500/75 transition-opacity"
                    onClick={onClose}
                    aria-hidden="true"
                />

                <div className="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl animate-scaleUp">
                    <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                        >
                            <XMarkIcon className="w-5 h-5" />
                        </button>
                    </div>

                    <form onSubmit={onSubmit}>
                        <div className="bg-white px-6 py-5 space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Car Line <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={data.carline_id}
                                        onChange={(e) => setData("carline_id", e.target.value)}
                                        className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        required
                                    >
                                        <option value="">Select Car Line</option>
                                        {carlines.map((carline) => (
                                            <option key={carline.id} value={carline.id}>
                                                {carline.code}
                                                {carline.description
                                                    ? ` - ${carline.description}`
                                                    : carline.name
                                                        ? ` - ${carline.name}`
                                                        : ""}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.carline_id && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.carline_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Level <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.level}
                                        onChange={(e) => setData("level", e.target.value)}
                                        className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        placeholder="e.g. LEVEL 1"
                                        required
                                    />
                                    {errors.level && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.level}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Assy Number <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.assy_number}
                                        onChange={(e) => setData("assy_number", e.target.value)}
                                        className={`w-full h-10 px-3 border rounded-xl text-sm ${
                                            isEdit
                                                ? "bg-gray-50 border-gray-200 text-gray-400 cursor-not-allowed"
                                                : "bg-white border-gray-200 text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        }`}
                                        placeholder="e.g. 82121-YZ123"
                                        required={!isEdit}
                                        readOnly={isEdit}
                                    />
                                    {isEdit && (
                                        <p className="text-xs text-gray-400 mt-1">Assy number cannot be changed</p>
                                    )}
                                    {errors.assy_number && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.assy_number}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Assy Code <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.assy_code}
                                        onChange={(e) => setData("assy_code", e.target.value)}
                                        className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        placeholder="e.g. A01"
                                        required
                                    />
                                    {errors.assy_code && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.assy_code}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        UMH <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.000001"
                                        value={data.umh}
                                        onChange={(e) => setData("umh", e.target.value)}
                                        className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        placeholder="e.g. 0.123456"
                                        required
                                    />
                                    {errors.umh && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.umh}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Pattern
                                    </label>
                                    <input
                                        type="text"
                                        value={data.pattern || ""}
                                        onChange={(e) => setData("pattern", e.target.value)}
                                        className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                        placeholder="e.g. PATTERN A"
                                    />
                                    {errors.pattern && (
                                        <p className="mt-1 text-xs text-red-600 font-medium">{errors.pattern}</p>
                                    )}
                                </div>
                            </div>

                            <div className="border-t border-gray-100 pt-4">
                                <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">
                                    Packaging & Standards Configuration
                                </h3>
                                
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Std Sea Quantity
                                        </label>
                                        <input
                                            type="number"
                                            value={data.standard_sea_quantity}
                                            onChange={(e) => setData("standard_sea_quantity", e.target.value)}
                                            className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            placeholder="Standard Sea Qty"
                                        />
                                        {errors.standard_sea_quantity && (
                                            <p className="mt-1 text-xs text-red-600 font-medium">{errors.standard_sea_quantity}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Std Air Quantity
                                        </label>
                                        <input
                                            type="number"
                                            value={data.standard_air_quantity}
                                            onChange={(e) => setData("standard_air_quantity", e.target.value)}
                                            className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            placeholder="Standard Air Qty"
                                        />
                                        {errors.standard_air_quantity && (
                                            <p className="mt-1 text-xs text-red-600 font-medium">{errors.standard_air_quantity}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Max Sea Quantity
                                        </label>
                                        <input
                                            type="number"
                                            value={data.max_quantity_sea}
                                            onChange={(e) => setData("max_quantity_sea", e.target.value)}
                                            className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            placeholder="Max Qty Sea"
                                        />
                                        {errors.max_quantity_sea && (
                                            <p className="mt-1 text-xs text-red-600 font-medium">{errors.max_quantity_sea}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Max Air Quantity
                                        </label>
                                        <input
                                            type="number"
                                            value={data.max_quantity_air}
                                            onChange={(e) => setData("max_quantity_air", e.target.value)}
                                            className="w-full h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                                            placeholder="Max Qty Air"
                                        />
                                        {errors.max_quantity_air && (
                                            <p className="mt-1 text-xs text-red-600 font-medium">{errors.max_quantity_air}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    type="checkbox"
                                    id="is_active_form"
                                    checked={data.is_active}
                                    onChange={(e) => setData("is_active", e.target.checked)}
                                    className="h-4.5 w-4.5 rounded border-gray-300 text-[#1D6F42] focus:ring-[#1D6F42]/20"
                                />
                                <label htmlFor="is_active_form" className="text-sm font-medium text-gray-700">
                                    Mark as Active
                                </label>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 bg-gray-50/60 px-6 py-4 flex items-center justify-end gap-3 rounded-b-2xl">
                            <button
                                type="button"
                                onClick={onClose}
                                className="h-10 px-4 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="h-10 px-5 bg-[#1D6F42] text-white text-sm font-semibold rounded-xl hover:bg-[#185c38] transition-colors shadow-sm disabled:opacity-50"
                            >
                                {submitLabel}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
