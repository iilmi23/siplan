import { ExclamationTriangleIcon, XMarkIcon } from "@heroicons/react/24/outline";

interface DeleteConfirmModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    assyNumber?: string;
    isProcessing: boolean;
}

export default function DeleteConfirmModal({
    isOpen,
    onClose,
    onConfirm,
    assyNumber,
    isProcessing,
}: DeleteConfirmModalProps) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="flex min-h-screen items-center justify-center p-4 text-center sm:p-0 backdrop-blur-[1px]">
                <div
                    className="fixed inset-0 bg-black/40 transition-opacity"
                    onClick={onClose}
                    aria-hidden="true"
                />

                <div className="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-200 animate-slideDown">
                    <div className="absolute top-0 right-0 pt-4 pr-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                        >
                            <XMarkIcon className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="bg-white px-6 pt-6 pb-4">
                        <div className="sm:flex sm:items-start gap-4">
                            <div className="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 sm:mx-0">
                                <ExclamationTriangleIcon className="w-6 h-6" />
                            </div>
                            <div className="mt-3 text-center sm:mt-0 sm:text-left">
                                <h3 className="text-lg font-semibold text-gray-900 leading-none">Delete Assy</h3>
                                <p className="text-xs text-gray-500 mt-2">
                                    Are you sure you want to delete Assy number{" "}
                                    <span className="font-semibold text-gray-900">{assyNumber}</span>?
                                    This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="border-t border-gray-100 bg-gray-55/50 px-6 py-4 flex items-center justify-end gap-3 rounded-b-2xl">
                        <button
                            type="button"
                            onClick={onClose}
                            className="h-10 px-4 border border-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-colors bg-white"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={onConfirm}
                            disabled={isProcessing}
                            className="h-10 px-5 bg-red-600 text-white text-xs font-semibold rounded-xl hover:bg-red-700 transition-colors shadow-sm disabled:opacity-50"
                        >
                            {isProcessing ? "Deleting..." : "Delete"}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
