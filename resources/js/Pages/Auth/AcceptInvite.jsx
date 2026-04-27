import { Head, useForm, usePage } from '@inertiajs/react';

/**
 * Wave 9 — invite acceptance form.
 *
 * Layout intentionally mirrors Auth/Login (split panel, indigo accents) so
 * the invitee lands on a visually familiar surface. Validation messages
 * come back through Inertia errors; the client does a soft length pre-check
 * (12 chars) only as UX, the real strength rule is enforced server-side via
 * Password::defaults() in AcceptInviteRequest.
 */
export default function AcceptInvite({ token, invitee, school, platformName }) {
    const { app_name: appName = platformName } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const contextName = school?.name ?? platformName;

    function submit(e) {
        e.preventDefault();
        post(route('invite.store', { token }), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    const tooShort = data.password.length > 0 && data.password.length < 12;

    return (
        <>
            <Head title={`Definir senha — ${appName}`} />
            <div className="min-h-screen flex">
                <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 flex-col justify-between p-12">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold text-lg">{appName.charAt(0).toUpperCase()}</span>
                            </div>
                            <span className="text-white font-bold text-xl">{appName}</span>
                        </div>
                    </div>
                    <div className="space-y-4">
                        <h1 className="text-4xl font-bold text-white leading-tight">
                            Bem-vindo,<br />
                            <span className="text-indigo-400">{invitee.name.split(' ')[0]}!</span>
                        </h1>
                        <p className="text-slate-400 text-lg">
                            Defina sua senha para acessar o {contextName}.
                        </p>
                    </div>
                    <div className="text-slate-500 text-sm">
                        Acesso como {invitee.role_label}
                    </div>
                </div>

                <div className="flex-1 flex items-center justify-center p-8 bg-gray-50">
                    <div className="w-full max-w-md">
                        <div className="flex items-center gap-3 mb-8 lg:hidden">
                            <div className="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold">{appName.charAt(0).toUpperCase()}</span>
                            </div>
                            <span className="font-bold text-gray-900 text-lg">{appName}</span>
                        </div>

                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                            <div className="mb-6">
                                <h2 className="text-2xl font-bold text-gray-900">Definir minha senha</h2>
                                <p className="text-gray-500 mt-1 text-sm">
                                    Convite para <strong>{invitee.email}</strong>
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Nova senha</label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Mínimo 12 caracteres"
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.password && <p className="mt-1.5 text-xs text-rose-600">{errors.password}</p>}
                                    {!errors.password && tooShort && (
                                        <p className="mt-1.5 text-xs text-amber-600">
                                            A senha deve ter ao menos 12 caracteres, com maiúsculas, minúsculas, números e símbolos.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirmar senha</label>
                                    <input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Repita a senha"
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.password_confirmation && (
                                        <p className="mt-1.5 text-xs text-rose-600">{errors.password_confirmation}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Salvando...' : 'Definir senha e entrar'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
