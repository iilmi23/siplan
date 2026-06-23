import { CheckCircleIcon, ExclamationTriangleIcon, XMarkIcon } from "@heroicons/react/24/outline";

export interface AlertProps {
    type: "success" | "warning" | "error" | string;
    message: string;
    onClose: () => void;
}

export default function Alert({ type, message, onClose }: AlertProps) {
    const isSuccess = type === "success";
    const isWarning = type === "warning";
    const Icon = isSuccess ? CheckCircleIcon : ExclamationTriangleIcon;

    return (
        <div className="mb-6 animate-slideDown">
            <div className={`flex items-center gap-3.5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-l-4 ${
                isSuccess
                    ? "border-l-[#1D6F42]"
                    : isWarning
                        ? "border-l-amber-500"
                        : "border-l-red-500"
            }`}>
                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                    isSuccess
                        ? "bg-green-50 text-[#1D6F42]"
                        : isWarning
                            ? "bg-amber-50 text-amber-600"
                            : "bg-red-50 text-red-500"
                }`}>
                    <Icon className="h-5 w-5" />
                </div>
                <p className="flex-1 text-sm font-medium text-gray-800 leading-normal">{message}</p>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-200"
                    aria-label="Close alert"
                >
                    <XMarkIcon className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
