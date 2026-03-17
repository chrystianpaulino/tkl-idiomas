import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { app_name: appName } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e) {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    }

    return (
        <>
            <Head title={`Entrar — ${appName}`} />
            <div className="min-h-screen flex">
                {/* Left Panel — Brand */}
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
                            Gerencie sua escola<br />
                            <span className="text-indigo-400">com eficiência</span>
                        </h1>
                        <p className="text-slate-400 text-lg">
                            Controle de pacotes, aulas, turmas e materiais em um só lugar.
                        </p>
                    </div>
                    <div className="flex items-center gap-6">
                        {['Pacotes de Aulas', 'Turmas', 'Materiais'].map(f => (
                            <div key={f} className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-indigo-400 rounded-full" />
                                <span className="text-slate-400 text-sm">{f}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Right Panel — Form */}
                <div className="flex-1 flex items-center justify-center p-8 bg-gray-50">
                    <div className="w-full max-w-md">
                        {/* Mobile logo */}
                        <div className="flex items-center gap-3 mb-8 lg:hidden">
                            <div className="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold">{appName.charAt(0).toUpperCase()}</span>
                            </div>
                            <span className="font-bold text-gray-900 text-lg">{appName}</span>
                        </div>

                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                            <div className="mb-8">
                                <h2 className="text-2xl font-bold text-gray-900">Bem-vindo de volta</h2>
                                <p className="text-gray-500 mt-1 text-sm">Entre com suas credenciais para continuar</p>
                            </div>

                            {status && (
                                <div className="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-700 text-sm">
                                    {status}
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        autoComplete="username"
                                        placeholder="seu@email.com"
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.email && <p className="mt-1.5 text-xs text-rose-600">{errors.email}</p>}
                                </div>

                                <div>
                                    <div className="flex items-center justify-between mb-1.5">
                                        <label className="block text-sm font-medium text-gray-700">Senha</label>
                                        {canResetPassword && (
                                            <Link href={route('password.request')} className="text-xs text-indigo-600 hover:text-indigo-700">
                                                Esqueci minha senha
                                            </Link>
                                        )}
                                    </div>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition placeholder-gray-400"
                                    />
                                    {errors.password && <p className="mt-1.5 text-xs text-rose-600">{errors.password}</p>}
                                </div>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={e => setData('remember', e.target.checked)}
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="text-sm text-gray-600">Lembrar de mim</span>
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Entrando...' : 'Entrar'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
