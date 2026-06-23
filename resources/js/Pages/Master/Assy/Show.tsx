import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, PencilIcon } from "@heroicons/react/24/outline";

type Assy = {
    id: number;
    assy_number?: string;
    assy_code?: string;
    level?: string;
    pattern?: string;
    umh?: number | string;
    is_active?: boolean;
    carline?: {
        code?: string;
    } | null;
};

export default function Show({ assy }: { assy: Assy }) {
    const rows = [
        ["Assy Number", assy.assy_number || "-"],
        ["Assy Code", assy.assy_code || "-"],
        ["Carline", assy.carline?.code || "-"],
        ["Level", assy.level || "-"],
        ["Pattern", assy.pattern || "-"],
        ["UMH", assy.umh ?? "-"],
        ["Status", assy.is_active ? "Active" : "Inactive"],
    ];

    return (
        <AdminLayout title="Assy Detail">
            <Head title="Assy Detail | SIPLAN" />
            <div className="min-h-screen bg-gray-50/40 px-5 pb-8 pt-2 font-sans md:px-8">
                <Breadcrumb items={[{ label: "Masters" }, { label: "Assy", href: route("assy.index") }, { label: "Detail" }]} />

                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-gray-900">{assy.assy_number || "Assy Detail"}</h1>
                            <p className="mt-1 text-sm text-gray-500">Master assy detail and status.</p>
                        </div>
                        <div className="flex gap-3">
                            <Link href={route("assy.index")} className="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <ArrowLeftIcon className="h-4 w-4" />
                                Back
                            </Link>
                            <Link href={route("assy.edit", assy.id)} className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#1D6F42] px-4 text-sm font-medium text-white hover:bg-[#185c38]">
                                <PencilIcon className="h-4 w-4" />
                                Edit
                            </Link>
                        </div>
                    </div>

                    <div className="grid gap-4 p-6 md:grid-cols-2">
                        {rows.map(([label, value]) => (
                            <div key={label} className="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">{label}</p>
                                <p className="mt-2 text-sm font-semibold text-gray-900">{value}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
