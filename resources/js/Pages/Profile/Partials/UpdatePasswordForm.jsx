import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { CheckCircleIcon, KeyIcon } from '@heroicons/react/24/outline';
import { useRef } from 'react';

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <form onSubmit={updatePassword} className="space-y-5">
                <div>
                    <label
                        htmlFor="current_password"
                        className="text-xs font-bold uppercase text-gray-500"
                    >
                        Current Password
                    </label>

                    <input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                        autoComplete="current-password"
                    />

                    <InputError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label htmlFor="password" className="text-xs font-bold uppercase text-gray-500">
                            New Password
                        </label>

                        <input
                            id="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            type="password"
                            className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                            autoComplete="new-password"
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <label
                            htmlFor="password_confirmation"
                            className="text-xs font-bold uppercase text-gray-500"
                        >
                            Confirm Password
                        </label>

                        <input
                            id="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            type="password"
                            className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                            autoComplete="new-password"
                        />

                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <KeyIcon className="h-4 w-4" />
                        Update Password
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
