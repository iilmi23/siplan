import AdminLayout from '@/Layouts/AdminLayout';
import { usePage } from '@inertiajs/react';
import {
    EnvelopeIcon,
    IdentificationIcon,
    KeyIcon,
    ShieldCheckIcon,
    UserCircleIcon,
} from '@heroicons/react/24/outline';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const ROLE_LABELS = {
    admin: 'Administrator',
    ppc: 'PPC',
};

const getInitials = (name = 'User') => {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || 'U';
};

function PageHeader({ user }) {
    const roleName = ROLE_LABELS[user?.role] ?? 'User';

    return (
        <div className="overflow-hidden rounded-lg bg-gradient-to-r from-[#0f5132] via-[#1D6F42] to-[#2d9b5e] shadow-sm">
            <div className="flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div className="flex min-w-0 items-center gap-4">
                    <div className="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg border border-white/20 bg-white/15 text-xl font-bold text-white shadow-sm">
                        {getInitials(user?.name)}
                    </div>

                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase text-emerald-100">
                            Account Profile
                        </p>
                        <h1 className="mt-1 truncate text-2xl font-bold text-white">
                            {user?.name ?? 'User'}
                        </h1>
                        <p className="mt-1 truncate text-sm font-medium text-emerald-50/90">
                            {user?.email ?? '-'}
                        </p>
                    </div>
                </div>

                <div className="inline-flex w-fit items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white">
                    <ShieldCheckIcon className="h-4 w-4" />
                    {roleName}
                </div>
            </div>
        </div>
    );
}

function ProfilePanel({ icon: Icon, title, subtitle, tone = 'emerald', children }) {
    const toneClass = {
        emerald: 'bg-emerald-50 text-[#1D6F42]',
        blue: 'bg-blue-50 text-blue-600',
        red: 'bg-red-50 text-red-600',
    }[tone];

    return (
        <section className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
            <div className="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                <div className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg ${toneClass}`}>
                    <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                    <h2 className="text-sm font-bold text-gray-900">{title}</h2>
                    <p className="mt-0.5 text-xs text-gray-500">{subtitle}</p>
                </div>
            </div>
            <div className="p-5 sm:p-6">{children}</div>
        </section>
    );
}

function AccountSummary({ user }) {
    const roleName = ROLE_LABELS[user?.role] ?? 'User';

    const rows = [
        {
            icon: UserCircleIcon,
            label: 'Name',
            value: user?.name ?? '-',
        },
        {
            icon: EnvelopeIcon,
            label: 'Email',
            value: user?.email ?? '-',
        },
        {
            icon: IdentificationIcon,
            label: 'Role',
            value: roleName,
        },
    ];

    return (
        <section className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
            <div className="border-b border-gray-100 px-5 py-4">
                <h2 className="text-sm font-bold text-gray-900">Account Detail</h2>
                <p className="mt-0.5 text-xs text-gray-500">Current signed-in user</p>
            </div>

            <div className="divide-y divide-gray-100">
                {rows.map(({ icon: Icon, label, value }) => (
                    <div key={label} className="flex items-start gap-3 px-5 py-4">
                        <div className="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500">
                            <Icon className="h-4 w-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase text-gray-400">
                                {label}
                            </p>
                            <p className="mt-1 break-words text-sm font-semibold text-gray-800">
                                {value}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    return (
        <AdminLayout title="Profile">
            <div className="min-h-screen bg-[#f7f8fc] px-5 pb-10 pt-4 md:px-8 space-y-5">
                <PageHeader user={user} />

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="space-y-5">
                        <ProfilePanel
                            icon={UserCircleIcon}
                            title="Profile Information"
                            subtitle="Name and email used across the system"
                        >
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                            />
                        </ProfilePanel>

                        <ProfilePanel
                            icon={KeyIcon}
                            title="Password"
                            subtitle="Keep account access secure"
                            tone="blue"
                        >
                            <UpdatePasswordForm />
                        </ProfilePanel>
                    </div>

                    <aside className="space-y-5">
                        <AccountSummary user={user} />
                    </aside>
                </div>
            </div>
        </AdminLayout>
    );
}
