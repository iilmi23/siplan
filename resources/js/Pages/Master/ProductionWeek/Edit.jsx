import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Link, router, useForm } from "@inertiajs/react";
import { useMemo } from "react";
import { CalendarDaysIcon, ExclamationTriangleIcon } from "@heroicons/react/24/outline";

const MONTH_MAP = {
    1: "JAN",
    2: "FEB",
    3: "MAR",
    4: "APR",
    5: "MAY",
    6: "JUN",
    7: "JUL",
    8: "AUG",
    9: "SEP",
    10: "OCT",
    11: "NOV",
    12: "DEC",
};

export default function Edit({ productionWeek, customers = [] }) {
    const { data, setData, processing, errors } = useForm({
        customer_id: productionWeek.customer_id ?? "",
        year: productionWeek.year,
        month_number: productionWeek.month_number,
        month_name: productionWeek.month_name,
        start_date: productionWeek.start_date ?? "",
        end_date: productionWeek.end_date ?? "",
        num_weeks: productionWeek.num_weeks ?? productionWeek.total_weeks ?? 4,
    });

    const weekPreview = useMemo(() => {
        const totalWeeks = Number(data.num_weeks);
        if (!data.start_date || !totalWeeks) return [];

        return Array.from({ length: totalWeeks }, (_, index) => {
            const start = addDays(data.start_date, index * 7);
            const end = index === totalWeeks - 1 && data.end_date
                ? data.end_date
                : addDays(start, 6);

            return {
                week_no: index + 1,
                start,
                end,
            };
        });
    }, [data.start_date, data.end_date, data.num_weeks]);

    const handleMonthChange = (value) => {
        const monthNumber = parseInt(value, 10);
        setData((prev) => ({
            ...prev,
            month_number: value,
            month_name: MONTH_MAP[monthNumber] ?? "",
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const params = new URLSearchParams({
            year: String(productionWeek.year),
            month: String(productionWeek.month_number),
        });

        if (productionWeek.customer_id !== null && productionWeek.customer_id !== undefined && productionWeek.customer_id !== "") {
            params.set("customer_id", String(productionWeek.customer_id));
        }

        router.put(`/production-week/update?${params.toString()}`, data, {
            preserveScroll: true,
        });
    };

    const currentCustomer = customers.find((customer) => String(customer.id) === String(productionWeek.customer_id));
    const nextCustomer = customers.find((customer) => String(customer.id) === String(data.customer_id));
    const isLocked = Number(productionWeek.total_weeks ?? 0) > 0;

    const beforePeriod = `${productionWeek.month_name} ${productionWeek.year}`;
    const afterPeriod = `${data.month_name || "-"} ${data.year || "-"}`;
    const beforeCustomer = currentCustomer ? `${currentCustomer.name} (${currentCustomer.code})` : "Global";
    const afterCustomer = nextCustomer ? `${nextCustomer.name} (${nextCustomer.code})` : "Global";
    const beforeTotalWeeks = Number(productionWeek.total_weeks ?? productionWeek.num_weeks ?? 0);
    const afterTotalWeeks = Number(data.num_weeks || 0);

    return (
        <AdminLayout>
            <div className="min-h-screen bg-gray-50/40 pt-2 pb-8 px-5 md:px-8 font-sans">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Production Weeks", href: route("production-week.index") }, { label: "Edit Monthly" }]} />

                <div className="w-full">
                    {isLocked && (
                        <div className="mb-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                            <ExclamationTriangleIcon className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="text-sm font-medium text-amber-800">Monthly update</p>
                                <p className="text-xs text-amber-700 mt-0.5">
                                    Period and customer changes will be applied to every week in this month.
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 p-6 border-b border-gray-100">
                            <div className="flex items-center gap-3">
                                <div className="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                                    <CalendarDaysIcon className="w-5 h-5 text-amber-600" />
                                </div>
                                <div>
                                    <h1 className="text-xl font-semibold text-gray-900">Edit Monthly Production Week</h1>
                                    <p className="text-sm text-gray-500 mt-0.5">
                                        {productionWeek.month_name} {productionWeek.year} is used for {productionWeek.total_weeks} week{Number(productionWeek.total_weeks) === 1 ? "" : "s"}.
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <SummaryPill label="Current" value={beforePeriod} />
                                <SummaryPill label="Weeks" value={`${afterTotalWeeks} weeks`} />
                            </div>
                        </div>

                        <form onSubmit={handleSubmit}>
                            <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px]">
                                <div className="p-6 lg:p-8 space-y-6">
                                    <div>
                                        <h2 className="text-sm font-semibold text-gray-900">Data yang Diubah</h2>
                                        <p className="mt-1 text-sm text-gray-500">Update the customer, period, date range, or total weeks for this production month.</p>
                                    </div>

                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                        <div className="lg:col-span-2">
                                            <FormField label="Customer" error={errors.customer_id} hint="Leave empty for a global production week.">
                                                <select
                                                    value={data.customer_id}
                                                    onChange={(e) => setData("customer_id", e.target.value)}
                                                    className={inputCls(errors.customer_id)}
                                                >
                                                    <option value="">Global (no customer)</option>
                                                    {customers.map((customer) => (
                                                        <option key={customer.id} value={customer.id}>
                                                            {customer.name} ({customer.code})
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>
                                        </div>

                                        <FormField label="Year" required error={errors.year}>
                                            <input
                                                type="number"
                                                min={2020}
                                                max={2030}
                                                value={data.year}
                                                onChange={(e) => setData("year", e.target.value)}
                                                className={inputCls(errors.year)}
                                            />
                                        </FormField>

                                        <FormField label="Month" required error={errors.month_number}>
                                            <select
                                                value={data.month_number}
                                                onChange={(e) => handleMonthChange(e.target.value)}
                                                className={inputCls(errors.month_number)}
                                            >
                                                <option value="">Select month...</option>
                                                {Object.entries(MONTH_MAP).map(([num, name]) => (
                                                    <option key={num} value={num}>
                                                        {num} - {name}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormField>

                                        <FormField
                                            label="Start Date"
                                            required
                                            error={errors.start_date}
                                            hint="Tanggal awal untuk week pertama."
                                        >
                                            <input
                                                type="date"
                                                value={data.start_date}
                                                onChange={(e) => setData("start_date", e.target.value)}
                                                className={inputCls(errors.start_date)}
                                            />
                                        </FormField>

                                        <FormField
                                            label="End Date"
                                            required
                                            error={errors.end_date}
                                            hint="Tanggal akhir untuk range bulan ini."
                                        >
                                            <input
                                                type="date"
                                                value={data.end_date}
                                                onChange={(e) => setData("end_date", e.target.value)}
                                                className={inputCls(errors.end_date)}
                                            />
                                        </FormField>

                                        <FormField label="Total weeks in month" required error={errors.num_weeks}>
                                            <div className="grid grid-cols-3 gap-2">
                                                {[3, 4, 5].map((weekCount) => (
                                                    <button
                                                        key={weekCount}
                                                        type="button"
                                                        onClick={() => setData("num_weeks", weekCount)}
                                                        className={`h-10 rounded-lg text-sm font-semibold border transition-all ${
                                                            Number(data.num_weeks) === weekCount
                                                                ? "bg-[#1D6F42] text-white border-[#1D6F42]"
                                                                : "bg-white text-gray-600 border-gray-200 hover:border-[#1D6F42]/40"
                                                        }`}
                                                    >
                                                        {weekCount}
                                                    </button>
                                                ))}
                                            </div>
                                        </FormField>
                                    </div>

                                    <div className="border-t border-gray-100 pt-5">
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <InfoStrip label="Start Date" value={formatDate(data.start_date)} />
                                            <InfoStrip label="End Date" value={formatDate(data.end_date)} />
                                            <InfoStrip label="Total Weeks" value={String(afterTotalWeeks)} />
                                        </div>
                                    </div>
                                </div>

                                <aside className="border-t xl:border-t-0 xl:border-l border-gray-100 bg-gray-50/70 p-6 lg:p-8">
                                    <div>
                                        <h2 className="text-sm font-semibold text-gray-900">Change Summary</h2>
                                        <p className="mt-1 text-sm text-gray-500">Review perubahan sebelum disimpan.</p>
                                    </div>

                                    <div className="mt-5 divide-y divide-gray-200/80">
                                        <DiffRow label="Customer" before={beforeCustomer} after={afterCustomer} />
                                        <DiffRow label="Periode" before={beforePeriod} after={afterPeriod} />
                                        <DiffRow
                                            label="Date Range"
                                            before={`${formatDate(productionWeek.start_date)} ~ ${formatDate(productionWeek.end_date)}`}
                                            after={`${formatDate(data.start_date)} ~ ${formatDate(data.end_date)}`}
                                        />
                                        <DiffRow label="Total Weeks" before={String(beforeTotalWeeks)} after={String(afterTotalWeeks)} />
                                    </div>

                                    <div className="mt-6 border-t border-gray-200/80 pt-5">
                                        <h3 className="text-sm font-semibold text-gray-900">Preview Week</h3>
                                        <div className="mt-3 space-y-2">
                                            {weekPreview.map((week) => (
                                                <div key={week.week_no} className="flex items-center justify-between gap-3 border-b border-gray-200/70 py-2.5 last:border-b-0">
                                                    <p className="text-sm font-semibold text-[#1D6F42]">Week {week.week_no}</p>
                                                    <p className="text-right text-xs font-mono text-gray-700">
                                                        {formatDate(week.start)}<br />
                                                        {formatDate(week.end)}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </aside>
                            </div>

                            <div className="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 px-6 lg:px-8 py-5 border-t border-gray-100 bg-white">
                                <Link
                                    href={route("production-week.index")}
                                    className="inline-flex justify-center px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex justify-center items-center gap-2 px-6 py-2.5 bg-[#1D6F42] text-white text-sm font-medium rounded-xl hover:bg-[#185c38] disabled:opacity-50 transition-all shadow-sm active:scale-[0.98]"
                                >
                                    {processing ? "Saving..." : "Save Changes"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

const inputCls = (error) =>
    `w-full h-10 px-3 bg-white border rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 transition-all ${
        error
            ? "border-red-300 focus:ring-red-200 focus:border-red-400"
            : "border-gray-200 focus:ring-[#1D6F42]/20 focus:border-[#1D6F42]"
    }`;

function FormField({ label, required, error, hint, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                {label}
                {required && <span className="text-red-500 ml-0.5">*</span>}
            </label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

function SummaryPill({ label, value }) {
    return (
        <div className="min-w-28 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
            <p className="text-[11px] font-medium uppercase text-gray-400">{label}</p>
            <p className="mt-0.5 max-w-48 truncate text-sm font-semibold text-gray-900">{value}</p>
        </div>
    );
}

function InfoStrip({ label, value }) {
    return (
        <div className="border-l-2 border-amber-500 bg-gray-50 px-3 py-2">
            <p className="text-xs text-gray-500">{label}</p>
            <p className="mt-0.5 truncate text-sm font-semibold text-gray-900">{value}</p>
        </div>
    );
}

function DiffRow({ label, before, after }) {
    const changed = String(before ?? "") !== String(after ?? "");

    return (
        <div className="py-3">
            <p className="text-xs font-medium text-gray-400">{label}</p>
            {changed ? (
                <div className="mt-1 space-y-1">
                    <p className="text-sm text-red-500 line-through">{before ?? "-"}</p>
                    <p className="text-sm font-semibold text-[#1D6F42]">{after ?? "-"}</p>
                </div>
            ) : (
                <p className="mt-1 text-sm font-medium text-gray-700">{after ?? "-"}</p>
            )}
        </div>
    );
}

const addDays = (value, days) => {
    const date = new Date(`${value}T00:00:00`);
    date.setDate(date.getDate() + days);
    return toDateInput(date);
};

const pad = (value) => String(value).padStart(2, "0");

const toDateInput = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const formatDate = (value) => {
    if (!value) return "-";

    return new Date(`${value}T00:00:00`).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
};
