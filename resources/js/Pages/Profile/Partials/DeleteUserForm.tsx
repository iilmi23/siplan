import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import { useForm } from '@inertiajs/react';
import { ExclamationTriangleIcon, TrashIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { type FormEvent, useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }: { className?: string }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement | null>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-4 ${className}`}>
            <p className="text-sm leading-6 text-gray-600">
                Deleting this account removes access permanently and signs you out immediately.
            </p>

            <button
                type="button"
                onClick={confirmUserDeletion}
                className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
                <TrashIcon className="h-4 w-4" />
                Delete Account
            </button>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="lg">
                <form onSubmit={deleteUser} className="p-6">
                    <div className="flex items-start gap-4">
                        <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                            <ExclamationTriangleIcon className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">
                                Delete this account?
                            </h2>

                            <p className="mt-1 text-sm leading-6 text-gray-600">
                                Enter your password to confirm permanent deletion.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6">
                        <label htmlFor="password" className="text-xs font-bold uppercase text-gray-500">
                            Password
                        </label>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-2 block w-full rounded-lg border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition focus:border-red-500 focus:ring-red-500"
                            autoFocus
                            placeholder="Password"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                        >
                            <XMarkIcon className="h-4 w-4" />
                            Cancel
                        </button>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <TrashIcon className="h-4 w-4" />
                            Delete Account
                        </button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
