import AdminLayout from "@/Layouts/AdminLayout";
import Breadcrumb from "@/Components/Admin/Breadcrumb";
import { Head } from "@inertiajs/react";

export default function Settings() {
    return (
        <AdminLayout title="Settings">
            <Head title="Settings | SIPLAN" />
            <div className="min-h-screen bg-gray-50/40 px-5 pb-8 pt-2 font-sans md:px-8">
                <Breadcrumb items={[{ label: "System" }, { label: "Settings" }]} />

                <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold tracking-tight text-gray-900">Settings</h1>
                    <p className="mt-1 text-sm text-gray-500">System settings can be added here when needed.</p>
                </div>
            </div>
        </AdminLayout>
    );
}
