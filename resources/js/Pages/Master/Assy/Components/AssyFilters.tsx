import { SyntheticEvent } from "react";
import { Carline } from "@/types/models";
import { Link, router } from "@inertiajs/react";
import {
    ArrowPathIcon,
    DocumentArrowUpIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

interface AssyFiltersProps {
    search: string;
    setSearch: (val: string) => void;
    carlineId: string;
    setCarlineId: (val: string) => void;
    isActive: string;
    setIsActive: (val: string) => void;
    carlines: Carline[];
    filterOpen: boolean;
    setFilterOpen: (val: boolean | ((prev: boolean) => boolean)) => void;
    hasActiveFilters: boolean;
    isProcessing: boolean;
    onApply: (e?: SyntheticEvent) => void;
    onReset: () => void;
    onSyncSirep: () => void;
    perPage: string;
}

export default function AssyFilters({
    search,
    setSearch,
    carlineId,
    setCarlineId,
    isActive,
    setIsActive,
    carlines,
    filterOpen,
    setFilterOpen,
    hasActiveFilters,
    isProcessing,
    onApply,
    onReset,
    onSyncSirep,
    perPage,
}: AssyFiltersProps) {
    const handleClearSearch = () => {
        setSearch("");
        router.get(window.route("assy.index"), {
            carline_id: carlineId || undefined,
            is_active: isActive === "" ? undefined : isActive,
            per_page: perPage || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <div className="px-6 py-4 border-b border-gray-100">
            <div className="flex flex-col lg:flex-row items-center justify-between gap-4">
                <form onSubmit={onApply} className="flex-1 max-w-md w-full">
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon className="w-5 h-5 text-gray-400" />
                        </div>
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="w-full h-11 pl-10 pr-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42] transition-all shadow-sm"
                            placeholder="Search and press Enter..."
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={handleClearSearch}
                                className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600"
                                aria-label="Clear search"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </form>

                <div className="flex items-center gap-3 w-full lg:w-auto shrink-0 justify-start lg:justify-end">
                    <button
                        type="button"
                        onClick={() => setFilterOpen((open) => !open)}
                        className={`inline-flex items-center justify-center gap-2 h-11 px-4 border rounded-xl text-sm font-medium transition-all ${filterOpen || carlineId || isActive !== ""
                            ? "bg-[#1D6F42]/10 border-[#1D6F42]/30 text-[#1D6F42]"
                            : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                            }`}
                    >
                        <FunnelIcon className="w-5 h-5" />
                        Filter
                    </button>

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={onReset}
                            className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50 hover:text-gray-900 transition-all"
                        >
                            <ArrowPathIcon className="w-5 h-5" />
                            Reset
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={onSyncSirep}
                        disabled={isProcessing}
                        className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-all shadow-sm active:scale-[0.98] disabled:opacity-60"
                    >
                        <ArrowPathIcon className={`w-5 h-5 ${isProcessing ? 'animate-spin' : ''}`} />
                        Sync SIREP
                    </button>

                    <Link
                        href={window.route("assy.importPage")}
                        className="inline-flex items-center justify-center gap-2 h-11 px-4 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 hover:text-[#1D6F42] hover:border-[#1D6F42]/30 transition-all shadow-sm active:scale-[0.98]"
                    >
                        <DocumentArrowUpIcon className="w-5 h-5" />
                        Export/Import
                    </Link>
                </div>
            </div>

            {filterOpen && (
                <div className="mt-4 bg-gray-50 p-4 rounded-xl border border-gray-200 animate-slideDown">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-sm font-semibold text-gray-800">Filter By</h3>
                    </div>

                    <div className="grid gap-3 md:grid-cols-[minmax(220px,1fr)_180px_auto] md:items-end">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1.5">
                                Car Line
                            </label>
                            <select
                                value={carlineId}
                                onChange={(event) => setCarlineId(event.target.value)}
                                className="w-full h-10 px-3 bg-white border border-gray-200 rounded-lg text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                            >
                                <option value="">All Car Lines</option>
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
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1.5">
                                Status
                            </label>
                            <select
                                value={isActive}
                                onChange={(event) => setIsActive(event.target.value)}
                                className="w-full h-10 px-3 bg-white border border-gray-200 rounded-lg text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
                            >
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={onApply}
                                className="h-10 px-4 bg-[#1D6F42] text-white text-sm font-medium rounded-lg hover:bg-[#185c38] transition-colors"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onClick={onReset}
                                className="h-10 px-4 bg-white text-gray-600 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
