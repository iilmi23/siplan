import { CheckCircleIcon } from "@heroicons/react/24/outline";

interface StepPillProps {
    number: string;
    label: string;
    done: boolean;
    active: boolean;
}

function StepPill({ number, label, done, active }: StepPillProps) {
    return (
        <div
            className={`flex h-10 items-center gap-2 rounded-xl border px-3 text-xs font-semibold transition-colors ${done
                ? "border-[#1D6F42]/20 bg-[#1D6F42]/10 text-[#1D6F42]"
                : active
                    ? "border-amber-200 bg-amber-50 text-amber-700"
                    : "border-gray-200 bg-white text-gray-500"
                }`}
        >
            <span
                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] ${done
                    ? "bg-[#1D6F42] text-white"
                    : active
                        ? "bg-amber-500 text-white"
                        : "bg-gray-100 text-gray-500"
                    }`}
            >
                {done ? <CheckCircleIcon className="h-3.5 w-3.5" /> : number}
            </span>
            <span className="truncate">{label}</span>
        </div>
    );
}

interface StepPillsProps {
    customerStepDone: boolean;
    fileStepDone: boolean;
    previewStepDone: boolean;
}

export default function StepPills({
    customerStepDone,
    fileStepDone,
    previewStepDone,
}: StepPillsProps) {
    return (
        <div className="grid gap-2 sm:grid-cols-3">
            <StepPill
                number="1"
                label="Customer"
                done={customerStepDone}
                active={!customerStepDone}
            />
            <StepPill
                number="2"
                label="File & sheet"
                done={fileStepDone}
                active={customerStepDone && !fileStepDone}
            />
            <StepPill
                number="3"
                label="Preview"
                done={previewStepDone}
                active={fileStepDone && !previewStepDone}
            />
        </div>
    );
}
