import { Head, Link, usePage } from '@inertiajs/react';

/**
 * Wave 9 — terminal page rendered when an invite link is invalid, expired,
 * or already used. Same response shape for all three failure modes so
 * attackers cannot distinguish them via timing or copy.
 */
export default function InviteExpired({ platformName }) {
    const { app_name: appName = platformName } = usePage().props;

    return (
        <>
            <Head title={`Convite expirado — ${appName}`} />
            <div className="min-h-screen flex items-center justify-center p-8 bg-gray-50">
                <div className="w-full max-w-md">
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center">
                        <div className="w-12 h-12 mx-auto mb-4 rounded-2xl bg-amber-50 flex items-center justify-center">
                            <svg
                                className="w-6 h-6 text-amber-600"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>

                        <h1 className="text-xl font-bold text-gray-900">Convite expirado</h1>
                        <p className="text-sm text-gray-500 mt-2">
                            Este link de convite expirou ou já foi utilizado. Peça um novo convite ao administrador da sua escola.
                        </p>

                        <div className="mt-6">
                            <Link
                                href={route('login')}
                                className="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors"
                            >
                                Ir para login
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
