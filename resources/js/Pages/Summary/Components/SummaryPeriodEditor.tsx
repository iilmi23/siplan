import { PivotCellSelection } from "./PivotPreview";
import { EditField, EditSelect } from "./SummaryRowEditor";

interface PeriodEditDialogProps {
    selection: PivotCellSelection;
    form: Record<string, any>;
    isSaving: boolean;
    onChange: (field: string, value: unknown) => void;
    onCancel: () => void;
    onSave: () => void;
}

export default function SummaryPeriodEditor({
    selection,
    form,
    isSaving,
    onChange,
    onCancel,
    onSave,
}: PeriodEditDialogProps) {
    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]">
            <div className="w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <div className="border-b border-slate-100 px-6 py-5">
                    <h2 className="text-lg font-semibold text-slate-950">Review Period</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {selection.subtitle} - update {selection.rows.length} Summary rows. Qty remains locked from the SR.
                    </p>
                </div>

                <div className="grid gap-4 p-6 md:grid-cols-2">
                    <EditSelect label="Order Type" value={form.order_type} onChange={(value) => onChange("order_type", value)} options={["", "FIRM", "FORECAST"]} />
                    <EditField label="Month" value={form.month} onChange={(value) => onChange("month", value)} placeholder="APR / 2026-04" />
                    <EditField label="Week" value={form.week} onChange={(value) => onChange("week", value)} />
                    <EditField label="ETD" type="date" value={form.etd} onChange={(value) => onChange("etd", value)} />
                    <EditField label="ETA" type="date" value={form.eta} onChange={(value) => onChange("eta", value)} />
                    <EditField label="Port" value={form.port} onChange={(value) => onChange("port", value)} />
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
                        disabled={isSaving}
                        className="h-10 rounded-lg bg-[#1D6F42] px-4 text-sm font-semibold text-white hover:bg-[#185c38] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSaving ? "Saving..." : "Save Period"}
                    </button>
                </div>
            </div>
        </div>
    );
}
