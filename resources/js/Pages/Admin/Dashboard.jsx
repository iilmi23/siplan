import AdminLayout from '@/Layouts/AdminLayout';
import { FaUsers, FaAnchor, FaCheckCircle, FaShip, FaBolt, FaPlus, FaArrowRight, FaChartLine } from 'react-icons/fa';
import { Link, usePage } from '@inertiajs/react';

// ─── Constants ────────────────────────────────────────────────────────────────

const ROLE_LABELS = {
    admin: 'Administrator',
    ppc: 'PPC',
};

const formatNumber = (value) => Number(value || 0).toLocaleString();

const formatSigned = (value) => {
    const number = Number(value || 0);
    return `${number > 0 ? '+' : ''}${number.toLocaleString()}`;
};

// ─── Stat Card ────────────────────────────────────────────────────────────────

const StatCard = ({ stat, index }) => (
    <Link
        href={stat.link}
        className="group relative bg-white rounded-2xl overflow-hidden border border-gray-100 hover:border-transparent hover:shadow-xl transition-all duration-300 hover:-translate-y-1"
        style={{ animationDelay: `${index * 80}ms` }}
    >
        {/* Gradient top accent */}
        <div
            className="absolute top-0 left-0 right-0 h-1 opacity-80"
            style={{ background: stat.gradient }}
        />

        {/* Subtle bg glow */}
        <div
            className="absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-[0.06] transition-opacity duration-300 group-hover:opacity-[0.12]"
            style={{ background: stat.gradient }}
        />

        <div className="p-5 relative">
            {/* Icon */}
            <div
                className="inline-flex items-center justify-center w-10 h-10 rounded-xl mb-4 text-white text-sm"
                style={{ background: stat.gradient }}
            >
                {stat.icon}
            </div>

            {/* Value */}
            <div className="mb-1">
                <span className="text-3xl font-bold text-gray-900 tracking-tight">
                    {stat.value.toLocaleString()}
                </span>
            </div>

            {/* Title */}
            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest">
                {stat.title}
            </p>

            {/* Arrow */}
            <div className="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-200 translate-x-1 group-hover:translate-x-0">
                <FaArrowRight className="text-gray-300 text-xs" />
            </div>
        </div>
    </Link>
);

// ─── Section Card Shell ───────────────────────────────────────────────────────

const SectionCard = ({ title, subtitle, action, children }) => (
    <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
        <div className="px-5 py-4 flex items-center justify-between border-b border-gray-50">
            <div>
                <h2 className="text-sm font-bold text-gray-800 tracking-tight">{title}</h2>
                <p className="text-xs text-gray-400 mt-0.5">{subtitle}</p>
            </div>
            {action && (
                <Link
                    href={action.href}
                    className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 transition-colors"
                >
                    {action.label}
                    <FaArrowRight className="text-[10px]" />
                </Link>
            )}
        </div>
        <div>{children}</div>
    </div>
);

// ─── List Items ───────────────────────────────────────────────────────────────

const CustomerItem = ({ customer }) => (
    <div className="group px-5 py-3.5 hover:bg-gray-50/80 transition-colors flex items-center justify-between">
        <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-rose-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                {customer.name?.charAt(0)?.toUpperCase() || '?'}
            </div>
            <div>
                <p className="text-sm font-semibold text-gray-800">{customer.name}</p>
                <p className="text-xs text-gray-400">
                    {customer.code || 'No code'} · {new Date(customer.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                </p>
            </div>
        </div>
        <Link
            href={`/customers/${customer.id}/ports`}
            className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 opacity-0 group-hover:opacity-100 transition-all"
        >
            Ports →
        </Link>
    </div>
);

const SRItem = ({ sr }) => (
    <div className="group px-5 py-3.5 hover:bg-gray-50/80 transition-colors flex items-center justify-between">
        <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white flex-shrink-0">
                <FaShip className="text-[10px]" />
            </div>
            <div>
                <p className="text-sm font-semibold text-gray-800 truncate max-w-[180px]">
                    {sr.source_file || `SR-${sr.id}`}
                </p>
                <p className="text-xs text-gray-400">
                    {new Date(sr.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                </p>
            </div>
        </div>
        <Link
            href={`/summary/${sr.id}`}
            className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 opacity-0 group-hover:opacity-100 transition-all"
        >
            Detail →
        </Link>
    </div>
);

const EmptyState = ({ icon: Icon, message }) => (
    <div className="py-10 text-center">
        <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-gray-100 mb-3">
            <Icon className="text-gray-300 text-lg" />
        </div>
        <p className="text-xs text-gray-400">{message}</p>
    </div>
);

// ─── Quick Action ─────────────────────────────────────────────────────────────

const QuickAction = ({ href, icon: Icon, label, gradient }) => (
    <Link
        href={href}
        className="group flex flex-col items-center gap-2.5 p-4 rounded-xl bg-gray-50 hover:bg-white border border-transparent hover:border-gray-200 hover:shadow-md transition-all duration-200"
    >
        <div
            className="w-10 h-10 rounded-xl flex items-center justify-center text-white text-sm transition-transform duration-200 group-hover:scale-110"
            style={{ background: gradient }}
        >
            <Icon />
        </div>
        <span className="text-xs font-semibold text-gray-600 group-hover:text-gray-900 transition-colors text-center leading-tight">
            {label}
        </span>
    </Link>
);

// ─── Greeting Banner ──────────────────────────────────────────────────────────

const VarianceMetric = ({ label, value, tone }) => {
    const toneClass = {
        up: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        down: 'bg-rose-50 text-rose-700 border-rose-100',
        flat: 'bg-gray-50 text-gray-700 border-gray-100',
    }[tone] || 'bg-gray-50 text-gray-700 border-gray-100';

    return (
        <div className={`rounded-xl border px-4 py-3 ${toneClass}`}>
            <p className="text-[11px] font-semibold uppercase tracking-widest opacity-70">{label}</p>
            <p className="mt-1 text-xl font-bold tracking-tight">{value}</p>
        </div>
    );
};

const VarianceChart = ({ data }) => {
    const summary = data?.summary || {};
    const customerRows = data?.by_customer || [];
    const topParts = data?.top_parts || [];
    const maxAbs = Math.max(...customerRows.map((row) => Math.abs(Number(row.variance_qty || 0))), 1);
    const totalDelta = Number(summary.variance_qty || 0);

    return (
        <SectionCard
            title="Variance Overview"
            subtitle="Latest completed SR batch compared with previous batch"
            action={{ href: '/variance', label: 'Open variance' }}
        >
            <div className="p-5">
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <VarianceMetric label="Delta Qty" value={formatSigned(totalDelta)} tone={totalDelta < 0 ? 'down' : totalDelta > 0 ? 'up' : 'flat'} />
                    <VarianceMetric label="Changed Parts" value={formatNumber(summary.changed_parts)} tone="flat" />
                    <VarianceMetric label="Increase" value={formatNumber(summary.increase_count)} tone="up" />
                    <VarianceMetric label="Decrease" value={formatNumber(summary.decrease_count)} tone="down" />
                </div>

                {customerRows.length > 0 ? (
                    <div className="grid grid-cols-1 xl:grid-cols-3 gap-5">
                        <div className="xl:col-span-2">
                            <div className="mb-3 flex items-center justify-between">
                                <p className="text-xs font-semibold uppercase tracking-widest text-gray-400">By Customer</p>
                                <div className="flex items-center gap-3 text-[11px] text-gray-400">
                                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-sm bg-emerald-500" />Up</span>
                                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-sm bg-rose-500" />Down</span>
                                </div>
                            </div>
                            <div className="space-y-3">
                                {customerRows.map((row) => {
                                    const delta = Number(row.variance_qty || 0);
                                    const width = `${Math.max(6, (Math.abs(delta) / maxAbs) * 100)}%`;
                                    const isUp = delta >= 0;

                                    return (
                                        <div key={row.customer} className="grid grid-cols-[72px_1fr_96px] items-center gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-bold text-gray-800">{row.customer}</p>
                                                <p className="truncate text-[11px] text-gray-400" title={row.current_file || ''}>{row.current_file || 'Latest batch'}</p>
                                            </div>
                                            <div className="relative h-8 overflow-hidden rounded-lg bg-gray-100">
                                                <div className="absolute left-1/2 top-0 h-full w-px bg-gray-300" />
                                                <div
                                                    className={`absolute top-1/2 h-3 -translate-y-1/2 rounded-full ${isUp ? 'left-1/2 bg-emerald-500' : 'right-1/2 bg-rose-500'}`}
                                                    style={{ width }}
                                                />
                                            </div>
                                            <div className={`text-right text-sm font-bold ${isUp ? 'text-emerald-600' : 'text-rose-600'}`}>
                                                {formatSigned(delta)}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <p className="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Top Changes</p>
                            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100">
                                {topParts.length > 0 ? topParts.map((row) => {
                                    const delta = Number(row.variance_qty || 0);
                                    return (
                                        <div key={`${row.customer}-${row.part_number}-${row.month}-${row.week}`} className="bg-white px-3 py-2.5">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="truncate text-xs font-bold text-gray-800" title={row.part_number}>{row.part_number}</p>
                                                <span className={`text-xs font-bold ${delta >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>{formatSigned(delta)}</span>
                                            </div>
                                            <p className="mt-0.5 text-[11px] text-gray-400">{row.customer} - {row.month || '-'} - Week {row.week || '-'}</p>
                                        </div>
                                    );
                                }) : (
                                    <div className="px-3 py-8 text-center text-xs text-gray-400">No changed parts</div>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    <EmptyState icon={FaChartLine} message="Need at least two completed SR batches per customer to show variance" />
                )}
            </div>
        </SectionCard>
    );
};

const GreetingBanner = ({ name, role }) => {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';

    return (
        <div className="relative bg-gradient-to-r from-[#0f5132] via-[#1D6F42] to-[#2d9b5e] rounded-2xl px-6 py-5 overflow-hidden mb-6">
            <div className="absolute -top-6 -right-6 w-32 h-32 rounded-full bg-white/5" />
            <div className="absolute -bottom-8 right-16 w-24 h-24 rounded-full bg-white/5" />
            <div className="absolute top-2 right-32 w-10 h-10 rounded-full bg-white/10" />

            <div className="relative flex items-center justify-between">
                <div>
                    <p className="text-emerald-200 text-xs font-medium tracking-wider uppercase mb-1">
                        {greeting}
                    </p>
                    <h1 className="text-white text-xl font-bold tracking-tight">
                        {name || 'SIPLAN'} 👋
                    </h1>
                    <p className="text-emerald-100/80 text-sm mt-1 font-medium">
                        Welcome to <span className="text-white font-bold">SIPLAN</span> Here's an overview of your data.
                    </p>
                </div>
                <div className="hidden sm:flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/20">
                    <FaChartLine className="text-emerald-200 text-sm" />
                    <span className="text-white text-xs font-semibold">Overview</span>
                </div>
            </div>
        </div>
    );
};

// ─── Main Dashboard ───────────────────────────────────────────────────────────

export default function Dashboard({ stats, recent_customers, recent_sr, recent_carlines, varianceChart, error }) {
    const { auth, flash } = usePage().props;
    const user = auth.user;
    const roleName = ROLE_LABELS[user?.role] ?? 'User';
    const isAdmin = user?.role === 'admin';
    const errorMessage = error || flash?.error;

    const statsData = [
        {
            title: 'Customers',
            value: stats?.total_customers || 0,
            icon: <FaUsers />,
            gradient: 'linear-gradient(135deg, #f97316, #ef4444)',
            link: '/customers',
        },
        {
            title: 'Ports',
            value: stats?.total_ports || 0,
            icon: <FaAnchor />,
            gradient: 'linear-gradient(135deg, #3b82f6, #6366f1)',
            link: '/ports',
        },
    ].filter(() => isAdmin).concat([
        {
            title: 'Shipping Releases',
            value: stats?.total_sr || 0,
            icon: <FaShip />,
            gradient: 'linear-gradient(135deg, #10b981, #059669)',
            link: '/sr/upload',
        },
        {
            title: 'Carlines',
            value: stats?.total_carlines || 0,
            icon: <FaCheckCircle />,
            gradient: 'linear-gradient(135deg, #8b5cf6, #a855f7)',
            link: '/carline',
        },
    ]);

    const quickActions = [
        ...(isAdmin ? [
            { href: '/customers/create', icon: FaPlus,   label: 'Add Customer', gradient: 'linear-gradient(135deg, #f97316, #ef4444)' },
            { href: '/ports',            icon: FaAnchor, label: 'Ports',        gradient: 'linear-gradient(135deg, #3b82f6, #6366f1)' },
        ] : []),
        { href: '/sr/upload', icon: FaBolt, label: 'Upload SR', gradient: 'linear-gradient(135deg, #10b981, #059669)' },
    ];

    return (
        <AdminLayout title="Dashboard">
            <div className="min-h-screen bg-[#f7f8fc] px-5 md:px-8 pt-4 pb-10">

                {/* Error */}
                {errorMessage && (
                    <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl">
                        <p className="text-sm text-red-600 font-medium">{errorMessage}</p>
                    </div>
                )}

                {/* Greeting Banner */}
                <GreetingBanner name={user?.name} role={roleName} />

                {/* Stats Grid */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    {statsData.map((stat, i) => (
                        <StatCard key={stat.title} stat={stat} index={i} />
                    ))}
                </div>

                <div className="mb-6">
                    <VarianceChart data={varianceChart} />
                </div>

                {/* Main Content — 2/3 + 1/3 layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    {isAdmin && (
                        <div className="lg:col-span-2">
                            <SectionCard
                                title="Recent Customers"
                                subtitle="Latest added customers"
                                action={{ href: '/customers', label: 'View all' }}
                            >
                                <div className="divide-y divide-gray-50">
                                    {recent_customers?.length > 0
                                        ? recent_customers.map(c => <CustomerItem key={c.id} customer={c} />)
                                        : <EmptyState icon={FaUsers} message="No customers yet" />
                                    }
                                </div>
                            </SectionCard>
                        </div>
                    )}

                    {/* Right col: Quick Actions + Recent SR */}
                    <div className={`flex flex-col gap-5 ${isAdmin ? '' : 'lg:col-span-3'}`}>
                        {/* Quick Actions */}
                        <SectionCard title="Quick Actions" subtitle="Shortcuts to main features">
                            <div className={`p-4 grid gap-3 ${isAdmin ? 'grid-cols-3' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'}`}>
                                {quickActions.map(action => (
                                    <QuickAction key={action.href} {...action} />
                                ))}
                            </div>
                        </SectionCard>

                        {/* Recent SR */}
                        <SectionCard
                            title="Recent SR"
                            subtitle="Latest shipping releases"
                            action={{ href: '/sr/upload', label: 'View all' }}
                        >
                            <div className="divide-y divide-gray-50">
                                {recent_sr?.length > 0
                                    ? recent_sr.slice(0, 4).map(sr => <SRItem key={sr.id} sr={sr} />)
                                    : <EmptyState icon={FaShip} message="No SR data yet" />
                                }
                            </div>
                        </SectionCard>
                    </div>
                </div>

            </div>
        </AdminLayout>
    );
}
