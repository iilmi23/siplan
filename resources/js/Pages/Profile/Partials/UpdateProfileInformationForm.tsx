import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircleIcon, PaperAirplaneIcon } from '@heroicons/react/24/outline';
import type { FormEvent } from 'react';
import type { AuthUser, SharedPageProps } from '@/types/global';

type ProfileUser = AuthUser & {
    email_verified_at?: string | null;
};

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage<SharedPageProps>().props.auth?.user as ProfileUser;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <form onSubmit={submit} className="space-y-5">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label htmlFor="name" className="text-xs font-bold uppercase text-gray-500">
                            Name
                        </label>

                        <input
                            id="name"
                            className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-[#1D6F42] focus:ring-[#1D6F42]"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            autoComplete="name"
                        />

                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div>
                        <label htmlFor="email" className="text-xs font-bold uppercase text-gray-500">
                            Email
                        </label>

                        <input
                            id="email"
                            type="email"
                            className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-[#1D6F42] focus:ring-[#1D6F42]"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p className="text-sm font-medium text-amber-900">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="ms-1 font-bold text-amber-800 underline underline-offset-2 hover:text-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                            >
                                Resend verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-semibold text-emerald-700">
                                A new verification link has been sent to your email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#1D6F42] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#175936] focus:outline-none focus:ring-2 focus:ring-[#1D6F42] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <PaperAirplaneIcon className="h-4 w-4" />
                        Save Changes
                    </button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600">
                            <CheckCircleIcon className="h-4 w-4" />
                            Saved
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
