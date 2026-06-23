import { ExclamationTriangleIcon } from "@heroicons/react/24/outline";

interface ValidationAlertProps {
    errors: string[];
}

export default function ValidationAlert({ errors }: ValidationAlertProps) {
    return (
        <div className="mb-6 animate-slideDown">
            <div className="rounded-xl border border-gray-200 border-l-4 border-l-red-500 bg-white p-4 shadow-sm">
                <div className="flex items-start gap-3">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">
                        <ExclamationTriangleIcon className="h-5 w-5" />
                    </div>
                    <div className="flex-1">
                        <p className="text-sm font-semibold text-gray-900">Upload cannot be processed</p>
                        <div className="mt-2 space-y-1">
                            {errors.map((error, index) => (
                                <p key={index} className="text-sm text-gray-700">
                                    {error}
                                </p>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
