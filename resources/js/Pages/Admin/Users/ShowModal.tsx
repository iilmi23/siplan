import {
    CalendarDaysIcon,
    EnvelopeIcon,
    PencilSquareIcon,
    ShieldCheckIcon,
    UserCircleIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";

type Permission = {
    key: string;
    label: string;
};

type PermissionCatalog = Record<string, Permission[]>;

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    permissions?: string[];
    created_at: string;
};

const roleBadge = (role: string) => ({
    admin: "bg-red-50 text-red-700 border-red-100",
    ppc: "bg-blue-50 text-blue-700 border-blue-100",
}[role] || "bg-gray-50 text-gray-700 border-gray-100");

const roleLabel = (role: string) => ({
    admin: "Admin",
    ppc: "PPC",
}[role] || role);

export default function ShowModal({
    user,
    permissionCatalog = {},
    onClose,
    onEdit,
}: {
    user: ManagedUser;
    permissionCatalog?: PermissionCatalog;
    onClose: () => void;
    onEdit?: () => void;
}) {
    const selected = user.permissions || [];

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 backdrop-blur-[1px]">
            <div className="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-slideDown">
                
                {/* Header */}
                <div className="p-5 pb-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <div className="flex items-center gap-3">
                        <img
                            className="h-10 w-10 rounded-xl"
                            src={`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=1D6F42&color=fff&size=128`}
                            alt={user.name}
                        />
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 leading-none">{user.name}</h2>
                            <p className="text-xs text-gray-500 mt-1">User account details and access summary.</p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                        aria-label="Close modal"
                    >
                        <XMarkIcon className="w-5 h-5" />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-5 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div className="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5">
                            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                                <UserCircleIcon className="w-4 h-4 text-[#1D6F42]" />
                                Full Name
                            </div>
                            <p className="text-sm font-semibold text-gray-900">{user.name}</p>
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5">
                            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                                <EnvelopeIcon className="w-4 h-4 text-[#1D6F42]" />
                                Email
                            </div>
                            <p className="text-sm font-semibold text-gray-900 break-all">{user.email}</p>
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5">
                            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                                <ShieldCheckIcon className="w-4 h-4 text-[#1D6F42]" />
                                Role
                            </div>
                            <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-lg border ${roleBadge(user.role)}`}>
                                {roleLabel(user.role)}
                            </span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-3">
                            <div>
                                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">Permissions</h3>
                                <p className="text-[10px] text-gray-400 mt-0.5">{selected.length} sidebar permissions enabled.</p>
                            </div>
                        </div>
                        <div className="p-4 space-y-4">
                            {Object.entries(permissionCatalog || {}).map(([group, permissions]) => (
                                <div key={group}>
                                    <p className="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">{group}</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {permissions.map((permission) => {
                                            const active = selected.includes(permission.key);
                                            return (
                                                <span
                                                    key={permission.key}
                                                    className={`inline-flex items-center rounded-lg border px-2 py-1 text-[11px] font-semibold ${
                                                        active
                                                            ? "bg-[#1D6F42]/10 text-[#1D6F42] border-[#1D6F42]/20"
                                                            : "bg-gray-50 text-gray-400 border-gray-200"
                                                    }`}
                                                >
                                                    {permission.label}
                                                </span>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5">
                        <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                            <CalendarDaysIcon className="w-4 h-4 text-[#1D6F42]" />
                            Account Created
                        </div>
                        <p className="text-sm font-semibold text-gray-900">
                            {new Date(user.created_at).toLocaleDateString("en-GB", {
                                day: "2-digit",
                                month: "long",
                                year: "numeric",
                                hour: "2-digit",
                                minute: "2-digit",
                            })}
                        </p>
                    </div>
                </div>

                {/* Footer */}
                <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-3.5 shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="inline-flex items-center justify-center h-10 px-4 text-xs font-semibold rounded-xl border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 transition"
                    >
                        Close
                    </button>
                    {onEdit && (
                        <button
                            type="button"
                            onClick={onEdit}
                            className="inline-flex items-center justify-center gap-2 h-10 px-4 bg-[#1D6F42] text-white text-xs font-semibold rounded-xl hover:bg-[#185c38] transition"
                        >
                            <PencilSquareIcon className="w-4 h-4" />
                            Edit User
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
