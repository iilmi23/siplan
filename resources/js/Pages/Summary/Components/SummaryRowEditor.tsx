interface SummaryEditDialogProps {
    form: Record<string, any>;
    isSaving: boolean;
    onChange: (field: string, value: unknown) => void;
    onCancel: () => void;
    onSave: () => void;
}

export default function SummaryRowEditor({
    form,
    isSaving,
    onChange,
    onCancel,
    onSave,
}: SummaryEditDialogProps) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="border-b border-slate-100 px-6 py-5">
                    <h2 className="text-lg font-semibold text-slate-950">Edit Summary Row</h2>
                    <p className="mt-1 text-sm text-slate-500">Edit schedule and mapping to generate SPP. Qty remains locked from the original SR.</p>
                </div>

                <div className="grid gap-4 p-6 md:grid-cols-2">
                    <EditField label="Assy Number" value={form.assy_number} onChange={(value) => onChange("assy_number", value)} required />
                    <EditSelect label="Order Type" value={form.order_type} onChange={(value) => onChange("order_type", value)} options={["", "FIRM", "FORECAST"]} />
                    <EditField label="Car Model" value={form.model} onChange={(value) => onChange("model", value)} />
                    <EditField label="Family" value={form.family} onChange={(value) => onChange("family", value)} />
                    <EditField label="Month" value={form.month} onChange={(value) => onChange("month", value)} placeholder="JUN / 2026-06" />
                    <EditField label="Week" value={form.week} onChange={(value) => onChange("week", value)} />
                    <EditField label="ETD" type="date" value={form.etd} onChange={(value) => onChange("etd", value)} />
                    <EditField label="ETA" type="date" value={form.eta} onChange={(value) => onChange("eta", value)} />
                    <div className="md:col-span-2">
                        <EditField label="Port" value={form.port} onChange={(value) => onChange("port", value)} />
                    </div>
                </div>

                <div className="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={isSaving}
                        className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onSave}
                        disabled={isSaving || !String(form.assy_number || "").trim()}
                        className="h-10 rounded-lg bg-[#1D6F42] px-4 text-sm font-semibold text-white hover:bg-[#185c38] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSaving ? "Saving..." : "Save Review"}
                    </button>
                </div>
            </div>
        </div>
    );
}

export function EditField({ label, value, onChange, type = "text", placeholder = "", required = false }: {
    label: string;
    value: any;
    onChange: (val: any) => void;
    type?: string;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">{label}{required ? " *" : ""}</span>
            <input
                type={type}
                value={value || ""}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-900 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
            />
        </label>
    );
}

export function EditSelect({ label, value, onChange, options }: {
    label: string;
    value: any;
    onChange: (val: any) => void;
    options: string[];
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</span>
            <select
                value={value || ""}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-900 focus:border-[#1D6F42] focus:outline-none focus:ring-2 focus:ring-[#1D6F42]/20"
            >
                {options.map((option) => (
                    <option key={option || "blank"} value={option}>{option || "-"}</option>
                ))}
            </select>
        </label>
    );
}
