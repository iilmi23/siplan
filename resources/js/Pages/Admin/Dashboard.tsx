import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedPageProps } from '@/types/global';
import { FaArrowRight, FaCarSide, FaChartLine, FaCogs, FaExclamationTriangle, FaShip, FaUsers } from 'react-icons/fa';

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrator',
    ppc: 'PPC',
};

const formatNumber = (value: unknown) => Number(value || 0).toLocaleString();

type StatItem = {
    title: string;
    value: number;
    icon: ReactNode;
    iconClass: string;
    link: string;
    adminOnly?: boolean;
};

const StatCard = ({ stat, index }: { stat: StatItem; index: number }) => (
    <Link
        href={stat.link}
        className="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-900/70"
        style={{ animationDelay: `${index * 80}ms` }}
    >
        <div className="flex items-start justify-between gap-4">
            <div className={`inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm ${stat.iconClass}`}>
                {stat.icon}
            </div>
            <div className="translate-x-1 opacity-0 transition-all duration-200 group-hover:translate-x-0 group-hover:opacity-100">
                <FaArrowRight className="mt-1 text-xs text-emerald-500 dark:text-emerald-400" />
            </div>
        </div>

        <div className="mt-5">
            <span className="text-3xl font-bold tracking-tight text-gray-900 dark:text-slate-100">
                {Number(stat.value || 0).toLocaleString()}
            </span>
            <p className="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">
                {stat.title}
            </p>
        </div>
    </Link>
);




const GreetingBanner = ({ name, role }: { name?: string; role: string }) => {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';

    return (
        <div className="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-[#0f5132] via-[#1D6F42] to-[#2d9b5e] px-6 py-5">
            <div className="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/5" />
            <div className="absolute -bottom-8 right-16 h-24 w-24 rounded-full bg-white/5" />
            <div className="absolute right-32 top-2 h-10 w-10 rounded-full bg-white/10" />

            <div className="relative flex items-center justify-between">
                <div>
                    <p className="mb-1 text-xs font-medium uppercase tracking-wider text-emerald-200">
                        {greeting}
                    </p>
                    <h1 className="text-xl font-bold tracking-tight text-white">
                        {name || 'SIPLAN'}
                    </h1>
                    <p className="mt-1 text-sm font-medium text-emerald-100/80">
                        Welcome to <span className="font-bold text-white">SIPLAN</span>. Here's an overview of your data.
                    </p>
                </div>
                <div className="hidden items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 backdrop-blur-sm sm:flex">
                    <FaChartLine className="text-sm text-emerald-200" />
                    <span className="text-xs font-semibold text-white">{role}</span>
                </div>
            </div>
        </div>
    );
};



export default function Dashboard({ stats, error }: { stats?: Record<string, number>; error?: string }) {
    const { auth, flash } = usePage<SharedPageProps>().props;
    const user = auth.user;
    const roleName = ROLE_LABELS[user?.role] ?? 'User';
    const isAdmin = user?.role === 'admin';
    const errorMessage = error || flash?.error;

    const statsData = isAdmin
        ? [
              {
                  title: 'Customers',
                  value: stats?.total_customers || 0,
                  icon: <FaUsers />,
                  iconClass: 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300',
                  link: '/customers',
              },
              {
                  title: 'Carlines',
                  value: stats?.total_carlines || 0,
                  icon: <FaCarSide />,
                  iconClass: 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300',
                  link: '/carline',
              },
              {
                  title: 'Assy',
                  value: stats?.total_assy || 0,
                  icon: <FaCogs />,
                  iconClass: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300',
                  link: '/assy',
              },
              {
                  title: 'SR',
                  value: stats?.total_sr || 0,
                  icon: <FaShip />,
                  iconClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
                  link: '/sr/upload',
              },
          ]
        : [
              {
                  title: 'Unmapped Assy',
                  value: stats?.unmapped_assy || 0,
                  icon: <FaExclamationTriangle />,
                  iconClass: 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300',
                  link: '/unmapped-assy',
              },
              {
                  title: 'SR Uploads',
                  value: stats?.total_sr || 0,
                  icon: <FaShip />,
                  iconClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
                  link: '/history',
              },
              {
                  title: 'SPP Generated',
                  value: stats?.total_spp || 0,
                  icon: <FaCogs />,
                  iconClass: 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300',
                  link: '/spp',
              },
              {
                  title: 'Total Order Qty',
                  value: stats?.total_qty || 0,
                  icon: <FaChartLine />,
                  iconClass: 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300',
                  link: '/summary',
              },
          ];

    return (
        <AdminLayout title="Dashboard">
            <div className="min-h-screen bg-[#f6f8fb] px-5 pb-10 pt-4 transition-colors duration-300 dark:bg-slate-950 md:px-8">
                {errorMessage && (
                    <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/40">
                        <p className="text-sm font-medium text-red-600 dark:text-red-300">{errorMessage}</p>
                    </div>
                )}

                <GreetingBanner name={user?.name} role={roleName} />

                <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {statsData.map((stat, index) => (
                        <StatCard key={stat.title} stat={stat} index={index} />
                    ))}
                </div>


            </div>
        </AdminLayout>
    );
}
